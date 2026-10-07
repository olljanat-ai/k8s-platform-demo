# platform

Owned by the platform team. Defines what runs in every AKS cluster and which
tenants exist where.

```
infra/                         AKS + node pools + microsoft.flux extension (Terraform, see infra/README.md)
clusters/<cluster>/            Flux entry point per cluster
  cluster-vars.yaml              cluster specific values (release channel, domain, ...)
  infrastructure.yaml            Flux Kustomizations: policies, gateway
  tenants.yaml                   Flux Kustomization: tenant instances of this cluster
  tenants/kustomization.yaml     list of tenant instances in this cluster
infrastructure/
  policies/                    ValidatingAdmissionPolicies + tenant RBAC extensions
  gateway/                     shared Gateway, wildcard certificate from Key Vault
tenants/
  _template/
    base/                      namespace, RBAC, quota, network policy, platform-vars, Flux sync
    development|acceptance|production/
                               adds the GitRepository with the Git ref of that channel
    components/key-vault/      optional SecretStore for a tenant Key Vault
  <tenant>/<instance>/         one Flux Kustomization per tenant instance (= namespace)
```

## How it fits together

1. Terraform installs the `microsoft.flux` extension and a flux configuration
   named `platform`, which creates the GitRepository `flux-system/platform` and
   applies `./clusters/<cluster>`.
2. `clusters/<cluster>` creates the `cluster-vars` ConfigMap and the Flux
   Kustomizations `policies`, `gateway` and `tenants`. Tenants are reconciled
   only after the policies are ready.
3. Each tenant instance (`tenants/<tenant>/<instance>/instance.yaml`) is a Flux
   Kustomization that renders `tenants/_template/${environment}` with the
   instance's variables (namespace, node pool, tenant repository ...).
   `${environment}` is the release channel of the cluster, which picks the Git
   ref the tenant repository is followed from:

   | `environment` | GitRepository ref |
   |---|---|
   | development | `branch: main` (overridable per instance with `git_branch`) |
   | acceptance | `semver: ">=0.0.0-0"` (includes pre-releases such as `2026.1007.3-acc.1`) |
   | production | `semver: ">=0.0.0"` (releases only, such as `2026.1007.3`) |

4. Inside the tenant namespace the Flux Kustomization `tenant` applies
   `tenant_path` from the tenant repository, impersonating `flux-reconciler`,
   with `targetNamespace` set and variables from the `platform-vars` ConfigMap.

## Onboard a tenant

1. Create the tenant repository in the platform project, for example
   `fi-marketing-guestbook`, by copying the structure of the example.
2. Add `tenants/<name>/main/{kustomization.yaml,instance.yaml}`. Copy the
   guestbook instance and change the variables:

   | Variable | Required | Description |
   |---|---|---|
   | `namespace` | yes | `<node pool>-<group>-<tenant>[-<instance>]` |
   | `node_pool`, `group`, `tenant` | yes | labels, node pool placement |
   | `tenant_repo_url` | yes | Azure DevOps URL of the tenant repository |
   | `tenant_path` | yes | normally `./environments/${environment}` |
   | `git_branch` | no | branch followed in development (default `main`) |
   | `quota_requests_cpu`, `quota_requests_memory`, `quota_limits_memory`, `quota_pods` | no | quota overrides |
   | `allowed_hostnames` | no | extra HTTPRoute host names, comma separated |
   | `keyvault_url`, `keyvault_client_id` | with `key-vault` component | tenant Key Vault |

3. List the instance in `clusters/<cluster>/tenants/kustomization.yaml` for
   every cluster it should run in.
4. Grant the application project's build service identity *Contribute* and
   *Create tag* on the tenant repository (see
   [pipeline-templates](../pipeline-templates/README.md#azure-devops-setup)).
   Only that identity should be able to create tags, because tags are what
   promote releases.

**Extra development environments:** add another instance such as
`tenants/<name>/dev2` with `namespace: <name>-dev2`,
`tenant_path: ./environments/dev2` and optionally another `git_branch`, then
list it only in `clusters/development/tenants`.

**Large applications:** several instances may point to the same tenant
repository with different `tenant_path`s (for example `./web/environments/...`
and `./api/environments/...`) and different node pools. They are promoted
together because they share the repository's tags.

## Add a cluster

1. Create the cluster with Terraform and point its flux configuration to
   `./clusters/<new-cluster>`.
2. Copy an existing `clusters/<cluster>` folder and adjust `cluster-vars.yaml`.
   Set `environment` to the release channel the cluster belongs to (for example
   `production` for a second production region).
3. List the tenant instances that should run there.

A new release channel (for example `staging`) means adding
`tenants/_template/staging/` with its Git ref, an `environments/staging`
overlay in the tenant repositories, and a stage in the pipeline template.

## Add a node pool (country)

Create the AKS node pool(s) with the label and taint
`platform.example.com/node-pool=<code>`. Several AKS pools (for example VM
sizes or zones) may share one label value. Then onboard tenant instances with
`node_pool: <code>`. Nothing else in the platform repository changes.

## Contract with infrastructure (Terraform)

See [infra/README.md](infra/README.md).

## Admission policies

| Policy | Effect |
|---|---|
| `platform-node-pool-placement` | Pods and workload templates in namespaces labelled `platform.example.com/node-pool` must select that pool. They may not use wildcard tolerations, tolerations for other pools, or `nodeName` |
| `platform-flux-tenant-impersonation` | Flux `Kustomization`/`HelmRelease` outside `flux-system` need `serviceAccountName` and may not use `kubeConfig` |
| `platform-managed-objects` | Objects labelled `platform.example.com/managed=true` can only be changed by Flux in `flux-system`, Kubernetes controllers or `system:masters` |
| `platform-httproute-hostnames` | HTTPRoute host names must start with `<namespace>.` or be listed in the namespace annotation `platform.example.com/allowed-hostnames` |

These are plain `ValidatingAdmissionPolicy` objects (GA since Kubernetes 1.30).
Once `MutatingAdmissionPolicy` is available on AKS, the node pool
`nodeSelector`/toleration could be injected instead of required.
