#!/bin/sh
set -o xtrace

while [[ -z "$TRAEFIK" ]]; do
    echo "Could not resolve IP of traefik, waiting for it to become available, retrying in 3s"
    TRAEFIK=$(dig +short traefik)
    sleep 3
done

exec dnsmasq --address /.test/$TRAEFIK -d