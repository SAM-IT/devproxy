FROM alpine
RUN apk add --update \
  openssl \
  nginx \
  php-cli \
  php-json \
  docker-cli
RUN mkdir -p /run/nginx
VOLUME /config
ADD entrypoint.sh /
ADD openssl.cnf /etc/ssl
ADD default.conf /etc/nginx/http.d/default.conf
ADD www /www
ADD create-nginx-config.php /
ENTRYPOINT ["/entrypoint.sh"]