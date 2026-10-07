# guestbook (application repository)

The frontend of the Kubernetes
[guestbook tutorial](https://kubernetes.io/docs/tutorials/stateless-application/guestbook/):
PHP + Apache with Redis as storage. It shows the build version and environment
on the page so promotions are visible.

This repository lives in the application team's Azure DevOps project. It does
not contain Kubernetes manifests. Those are in the tenant repository
[`fi-marketing-guestbook`](../fi-marketing-guestbook/) in the platform project.

## Pipeline

[`azure-pipelines.yml`](azure-pipelines.yml) extends the shared template
[`build-and-promote.yml`](../pipeline-templates/templates/build-and-promote.yml):

1. A push to `main` builds `contosoplatform.azurecr.io/marketing/guestbook-frontend:<yyyy.Mdd.run>`
   and deploys it to `fi-marketing-guestbook` in the development cluster.
2. Approving the `fi-marketing-guestbook-acceptance` environment promotes it to
   acceptance.
3. Approving the `fi-marketing-guestbook-production` environment promotes it to
   production.

Other ways to run it:

- **Promote an earlier build:** *Run pipeline* with `version` set, for example
  `2026.1007.3`.
- **Test a feature branch:** *Run pipeline* on the branch with
  `devInstance = dev2`. The result is at
  `https://fi-marketing-guestbook-dev2.dev.apps.example.com`.

## Local run

```sh
docker build --build-arg APP_VERSION=local -t guestbook-frontend .
docker network create gb
docker run -d --network gb --name redis-leader redis:6.0.5
docker run -d --network gb --name redis-follower redis:6.0.5 redis-server --replicaof redis-leader 6379
docker run --rm --network gb -p 8080:80 guestbook-frontend   # http://localhost:8080
```
