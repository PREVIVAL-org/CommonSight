# PHP for CommonSight (Architecture 4.13), the same version as in operation. Build context: repository root.
# Target "fpm": web server PHP for the status endpoint.
# Target "scheduler": PHP CLI with cron for the fetcher and pmtiles for the tile job.

ARG PHP_VERSION=8.4

FROM php:${PHP_VERSION}-fpm-bookworm AS base

# curl, json, mbstring, xmlreader, dom and zlib come with the image.
RUN pecl install brotli \
 && docker-php-ext-enable brotli \
 && docker-php-ext-install opcache pdo_mysql

COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-commonsight.ini

# Hosting account: own user, home with application (commonsight/) and webroot (public_html/).
ARG CS_UID=1000
ARG CS_GID=1000
RUN groupadd -g "${CS_GID}" commonsight \
 && useradd -u "${CS_UID}" -g commonsight -d /home/commonsight -s /bin/bash commonsight \
 && ln -s /usr/local/bin/php /usr/bin/php

FROM base AS fpm
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/zz-commonsight.conf

FROM base AS scheduler
ARG PMTILES_VERSION=1.31.2
ARG TARGETARCH
RUN apt-get update \
 && apt-get install -y --no-install-recommends cron \
 && rm -rf /var/lib/apt/lists/* \
 && case "${TARGETARCH:-amd64}" in \
      amd64) arch=x86_64 ;; \
      arm64) arch=arm64 ;; \
      *) echo "Unsupported architecture: ${TARGETARCH}" >&2; exit 1 ;; \
    esac \
 && curl -fsSL "https://github.com/protomaps/go-pmtiles/releases/download/v${PMTILES_VERSION}/go-pmtiles_${PMTILES_VERSION}_Linux_${arch}.tar.gz" \
    | tar -xz -C /usr/local/bin pmtiles
# The same schedule as in operation (install/crontab.example), without the monthly tile job: it would download some
# 9 GB every month; locally tiles are built by hand (./dev.sh update-tiles).
COPY install/crontab.example /tmp/crontab.example
RUN sed -e '/update-tiles.sh/d' -e 's#@PHP@#/usr/bin/php#g' -e 's#@APP@#/home/commonsight/commonsight#g' -e 's#@WEBROOT@#/home/commonsight/public_html#g' \
      /tmp/crontab.example | crontab -u commonsight -
CMD ["cron", "-f"]
