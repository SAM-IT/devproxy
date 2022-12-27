#!/bin/sh
set -o xtrace

DNS=$(dig +short dnsserve)
while [[ -z "$DNS" ]]; do
    echo "Could not resolve IP of dnsserve, waiting for it to become available, retrying in 3s"
    sleep 3
    DNS=$(dig +short dnsserve)
done

if [[ ! -f "/home/step/certs/root_ca.crt" ]]; then
    echo "Root CA certificate not found; did you run bootstrap?"
    exit 1
fi
su step -c "exec /usr/local/bin/step-ca --resolver $DNS:53"