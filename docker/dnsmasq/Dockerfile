FROM alpine:edge
RUN apk add --update --no-cache dnsmasq bind-tools
ADD entrypoint.sh /
ENTRYPOINT ["/entrypoint.sh"]