FROM composer:latest
ADD dns/src /project/src
ADD dns/composer.* /project/
WORKDIR /project
RUN composer install

FROM php:8.1-alpine
COPY --from=0 /project /project
RUN apk add tini
ENTRYPOINT ["/sbin/tini", "--", "/usr/local/bin/php", "/project/src/serve.php"]