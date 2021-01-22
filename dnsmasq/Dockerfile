FROM alpine:edge
RUN apk add --update --no-cache dnsmasq
ENTRYPOINT ["/usr/sbin/dnsmasq", "--address", "/.test/127.0.0.3", "-d"]