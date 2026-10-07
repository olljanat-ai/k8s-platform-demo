# fi-marketing-guestbook

Tenant repository for **node pool `fi`**, **group `marketing`** and **tenant
`guestbook`**. It holds the Kubernetes manifests of the
[guestbook](https://kubernetes.io/docs/tutorials/stateless-application/guestbook/)
application. Flux applies them; nobody runs `kubectl` against the clusters.

```
base/                       shared by all environments
  kustomization.yaml          release definition: frontend image + tag (updated by the pipeline)
  frontend/                   Deployment, Service, HTTPRoute
  redis/                      Redis leader + follower
components/node-pool/       nodeSelector + toleration for the namespace's node pool
environments/
  development/              namespace fi-marketing-guestbook       (development cluster)
  dev2/                     namespace fi-marketing-guestbook-dev2  (development cluster)
  acceptance/               namespace fi-marketing-guestbook       (acceptance cluster)
  production/               namespace fi-marketing-guestbook       (production cluster)
```

## What deploys where

| Cluster | Git ref | Path |
|---|---|---|
| development | `main` | `environments/development`, `environments/dev2` |
| acceptance | highest tag such as `2026.1007.3-acc.1` | `environments/acceptance` |
| production | highest tag such as `2026.1007.3` | `environments/production` |

- Configuration changes (replicas, environment variables, new resources) are
  normal commits to `main`. They reach development straight away, and
  acceptance and production with the next promoted version, because tags are
  set on the commit that introduced that version.
- `base/kustomization.yaml` `images` and the tags are written by the
  application pipeline. Don't edit them by hand.
- Tags are created by the pipeline only. They are the promotion mechanism.

## Variables provided by the platform

Use them as `${name}` in any manifest. Flux substitutes them from the
`platform-vars` ConfigMap in the namespace:

| Variable | Example (dev2 instance) |
|---|---|
| `namespace` | `fi-marketing-guestbook-dev2` |
| `node_pool`, `group`, `tenant` | `fi`, `marketing`, `guestbook` |
| `environment` | `development` |
| `cluster_name` | `aks-platform-dev-weu` |
| `cluster_domain` | `dev.apps.example.com` |
| `hostname` | `fi-marketing-guestbook-dev2.dev.apps.example.com` |
| `gateway_name`, `gateway_namespace` | `platform`, `gateway-system` |

To keep a literal `${...}` in a manifest, escape it as `$${...}`.

## Rules enforced by the platform

- Everything is applied into the instance's namespace. Cluster-scoped
  resources are not allowed.
- Workloads must run on the namespace's node pool. `components/node-pool` takes
  care of that for Deployments and StatefulSets; add the same for Jobs and
  CronJobs if you use them.
- HTTPRoutes must use `${hostname}` or host names the platform allowed for the
  namespace.
- Pod Security `baseline`, a resource quota, and default-deny ingress network
  policies (traffic within the namespace and from the shared gateway is
  allowed).

## Secrets

Secrets come from the tenant's Azure Key Vault through External Secrets
Operator. The platform provides the `SecretStore` named `keyvault` when the
instance enables it:

```yaml
apiVersion: external-secrets.io/v1
kind: ExternalSecret
metadata:
  name: example
spec:
  secretStoreRef:
    kind: SecretStore
    name: keyvault
  target:
    name: example
  data:
    - secretKey: password
      remoteRef:
        key: example-password
```
