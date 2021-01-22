FROM alpine
RUN apk add --update \
  openssl \
  nginx \
  php-cli \
  docker-cli
RUN mkdir -p /run/nginx
VOLUME /config
ADD entrypoint.sh /
ADD openssl.cnf /etc/ssl
ADD default.conf /etc/nginx/conf.d/default.conf.disabled
ADD www /www
RUN echo 'include /tmp/nginxblocks/*.conf;' >> /etc/nginx/conf.d/dynamic.conf
ADD create-nginx-config.php /
ENTRYPOINT ["/entrypoint.sh"]