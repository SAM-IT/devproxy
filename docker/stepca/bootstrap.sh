#!/bin/sh

if [[ -f "/home/step/certs/root_ca.crt" ]]; then
    echo "Root CA certificate found; did you already run bootstrap?"
    exit 1
fi

su step -c "step ca init --deployment-type=standalone --address=:443 --name=DevProxyV2 --dns step-ca --provisioner=admin --pki --password-file=/home/step/config/password"