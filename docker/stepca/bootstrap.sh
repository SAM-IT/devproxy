#!/bin/sh

if [[ -f "/home/step/certs/root_ca.crt" ]]; then
    echo "Root CA certificate found; did you already run bootstrap?"
    exit 1
fi

DNS=$(dig +short dnsserve)
while [[ -z "$DNS" ]]; do
    echo "Could not resolve IP of dnsserve, waiting for it to become available, retrying in 3s"
    sleep 3
    DNS=$(dig +short dnsserve)
done

su step -c "step ca init --deployment-type=standalone --address=:443 --name=DevProxyV2 --dns $DNS:53 --provisioner=admin --pki --password-file=/home/step/config/password"