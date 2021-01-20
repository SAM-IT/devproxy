#!/bin/sh

echo Starting stuff
mkdir -p /config
# Create private key, used for CA as well as all certificates.
cd /config
if [ ! -f private.key ]; then
  echo "Creating new private key";
  openssl genrsa -out private.key 4096
fi

if [ ! -f ca.crt ]; then
  echo "Creating new CA certificate";
  openssl req -x509 -new -nodes -key private.key -sha256 -days 1024 -out ca.crt -subj "/C=NL/CN=DevProxy"
fi

openssl x509 -in certs/devproxy.crt -text -noout | grep DNS
nginx &
sleep 5
/create-nginx-config.php
echo "Waiting for docker events"
docker events --filter event=start --format=containerstart &
docker events --filter event=start --format=containerstart | xargs -t -n 1 /create-nginx-config.php
