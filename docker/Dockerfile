FROM alpine
RUN apk add --update \
  openssl \
  nginx \
  php-cli \
  docker
RUN mkdir -p /run/nginx
VOLUME /config
ADD entrypoint.sh /
ADD openssl.cnf /etc/ssl
RUN echo 'include /tmp/nginxblocks/*.conf;' >> /etc/nginx/conf.d/dynamic.conf
ADD create-nginx-config.php /
ENTRYPOINT ["/entrypoint.sh"]