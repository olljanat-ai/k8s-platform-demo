# Guestbook frontend (PHP + Redis), based on the Kubernetes guestbook tutorial:
# https://kubernetes.io/docs/tutorials/stateless-application/guestbook/
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json ./
RUN composer install --no-dev --no-interaction --no-progress --optimize-autoloader

FROM php:8.3-apache
# Version is injected by the build pipeline (date + build run, e.g. 2026.1007.3)
ARG APP_VERSION=dev
ENV APP_VERSION=${APP_VERSION}
LABEL org.opencontainers.image.title="guestbook-frontend" \
      org.opencontainers.image.version="${APP_VERSION}"
COPY --from=vendor /app/vendor /var/www/html/vendor
COPY src/ /var/www/html/
EXPOSE 80
