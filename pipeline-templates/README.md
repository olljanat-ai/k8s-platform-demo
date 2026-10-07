# pipeline-templates

Azure DevOps YAML templates for application repositories that deploy to the
platform. The pipeline builds the container image once and then promotes it
by tagging the tenant repository.

```
templates/
  build-and-promote.yml     stages: Build -> Development -> Acceptance -> Production
  steps/tenant-repo.yml     clones the tenant repository, shared git helpers
examples/
  azure-pipelines.copied.yml  variant for a copied (customised) template
```

## Stages

| Stage | When | What it does |
|---|---|---|
| Build | `version` = `new` | Computes the version `yyyy.Mdd.run` from the build number and builds and pushes `<registry>/<imageRepository>:<version>` |
| Development | after Build | Sets the image in the tenant repository's `base/kustomization.yaml` on `main`, then tags that commit `dev-<version>` (only for builds of `main`) |
| Acceptance | after Development, or on a manual run with a version | Tags the `dev-<version>` commit `<version>-acc.1` |
| Production | after Acceptance, or on a manual run with a version | Tags the commit of the acceptance tag `<version>` |

Acceptance and Production are deployment jobs on the Azure DevOps environments
`<environmentPrefix>-acceptance` and `<environmentPrefix>-production`. Gating
them is done with **Approvals and checks** on those environments; YAML has no
approval setting. The environment history then shows which version went
where.

Safety checks in the promotion stages:

- Only versions deployed to development from the release branch can be
  promoted.
- Production requires the version to be in acceptance.
- Versions older than what the environment already has are rejected, because
  Flux follows the highest tag and would silently ignore them.
- Re-running a stage does nothing if the tag already exists. A tag that
  exists on a different commit fails the run.

### Runtime parameters

| Parameter | Default | Use |
|---|---|---|
| `version` | `new` | `new` builds from the selected branch. A version such as `2026.1007.3` skips the build and only promotes |
| `skipAcceptance` | `false` | With a version: go straight to production. The version must already be in acceptance |
| `devInstance` | `default` | Deploy a new build to an extra development instance, e.g. `dev2`, which writes `environments/dev2/kustomization.yaml`. These builds are never promoted, which makes this the way to test feature branches |

## Usage

### 1. Referenced (simple projects)

The application pipeline extends the template directly from this repository.
Fixes reach all users when they move to a new tag of this repository.

```yaml
name: $(Date:yyyyMMdd)$(Rev:.r)    # required: converted to the version

resources:
  repositories:
    - repository: templates
      type: git
      name: Platform/pipeline-templates
      ref: refs/tags/v1

extends:
  template: templates/build-and-promote.yml@templates
  parameters: ...
```

Full example: [guestbook-app/azure-pipelines.yml](../guestbook-app/azure-pipelines.yml).

### 2. Copied (complex projects)

Copy `templates/` into the application repository (for example as
`pipelines/`), customise it, and reference it without `@templates`:

```yaml
extends:
  template: pipelines/build-and-promote.yml
```

See [examples/azure-pipelines.copied.yml](examples/azure-pipelines.copied.yml).
The template also has a `preBuildSteps` parameter for small additions such as
tests, so a copy isn't needed for those.

### Template parameters

| Parameter | Example | Description |
|---|---|---|
| `containerRegistry` | `acr-contosoplatform` | Docker registry service connection to the ACR (workload identity federation) |
| `registryLoginServer` | `contosoplatform.azurecr.io` | written into the tenant repository |
| `imageRepository` | `marketing/guestbook-frontend` | repository in ACR |
| `dockerfile`, `buildContext` | `Dockerfile`, `.` | |
| `preBuildSteps` | | steps run before the image build |
| `releaseBranch` | `main` | only builds from this branch are promoted |
| `tenantProject`, `tenantRepository` | `Platform`, `fi-marketing-guestbook` | tenant repository |
| `tenantBranch` | `main` | |
| `kustomizeImageName` | `guestbook-frontend` | `images[].name` in the tenant kustomization |
| `tenantReleasePath` | `base` | kustomization holding the release version |
| `tenantInstancePath` | `environments` | parent folder of extra dev instance overlays |
| `environmentPrefix` | `fi-marketing-guestbook` | Azure DevOps environments `<prefix>-development/-acceptance/-production` |
| `pool` | `{vmImage: ubuntu-latest}` | agent pool. Needs `git`, `docker` and [`yq` v4](https://github.com/mikefarah/yq) (on Microsoft-hosted Ubuntu images by default) |

## Azure DevOps setup

Once per application project:

1. **Service connection** to the ACR (Docker registry, workload identity
   federation), with `AcrPush`.
2. **Environments** `<prefix>-development`, `<prefix>-acceptance` and
   `<prefix>-production`. Add approvals to acceptance and production. An
   *exclusive lock* check keeps parallel runs in order.
3. **Cross-project access:** the pipeline pushes to the tenant repository in
   the platform project with `System.AccessToken`, as the identity
   `<application project> Build Service (<organization>)`. In the platform
   project, on the tenant repository, grant it:
   - *Contribute* and *Create tag*
   - *Bypass policies when pushing*, if `main` has branch policies

   Then either turn off *Limit job authorization scope to current project for
   non-release pipelines* in the application project, or keep it on and grant
   the platform project's access explicitly. When the template is referenced,
   the identity also needs *Read* on `Platform/pipeline-templates`.
4. Pipeline permission to use the `Platform/pipeline-templates` repository
   resource (asked on the first run).

## Several applications in one tenant repository

Tags belong to the tenant repository, so all applications in it share one
release stream. If several application pipelines update the same tenant
repository, their date-based versions can collide. The pipeline then fails
with "tag already exists on another commit". Either give each pipeline
distinct versions (for example a different `name:` pattern), or let the
application pipelines update development only and promote the tenant
repository with one release pipeline.
