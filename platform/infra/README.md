# infra

Terraform (Azure Verified Modules) for the AKS clusters is maintained
separately and will be added here later. This page is the contract between
that code and the rest of the platform repository.

## Per cluster

| Item | Requirement |
|---|---|
| Node pools | `system` pool (taint `CriticalAddonsOnly=true:NoSchedule`) plus one or more pools per country. Each country pool has the label **and** the taint `platform.example.com/node-pool=<code>` (`fi`, `se`, `be`, ...) |
| Network policy | Engine enabled (e.g. Azure CNI powered by Cilium), required by the tenant `NetworkPolicy` objects |
| Workload identity | OIDC issuer + workload identity enabled |
| ACR | Kubelet identity has `AcrPull` on the shared registry |
| Gateway API | CRDs and a gateway controller installed. Its GatewayClass name goes into `cluster-vars.yaml` (`gateway_class_name`) |
| External Secrets Operator | Installed (API `external-secrets.io/v1`) |
| Key Vault (platform) | Holds certificate `wildcard-apps` for `*.<cluster_domain>`. A managed identity with *Key Vault Secrets User*, federated with `system:serviceaccount:gateway-system:platform-keyvault`. Client id goes into `cluster-vars.yaml` |
| Key Vault (tenant, optional) | Per tenant: identity federated with `system:serviceaccount:<namespace>:keyvault`, values in the tenant instance |

## Flux (microsoft.flux extension)

| Setting | Value |
|---|---|
| Extension | `microsoft.flux`, multi-tenancy enforcement on (default), workload identity enabled |
| Source-controller identity | Entra identity with read access to the repositories of the Azure DevOps platform project. Used by `GitRepository.spec.provider: azure` |
| Flux configuration | name `platform`, namespace `flux-system`, scope `cluster` |
| Source | Git `https://dev.azure.com/contoso/Platform/_git/platform`, branch `main` |
| Kustomization | `cluster`: path `./clusters/<cluster>`, prune `true` |

The names matter: the repository refers to `GitRepository flux-system/platform`.

If the extension's multi-tenancy settings make Flux impersonate a default
service account, the platform Kustomizations in `flux-system` (which set no
`serviceAccountName`) run as that account. It needs cluster-admin, which the
extension's `flux-applier` account has.
