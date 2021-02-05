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

cp ca.crt /www/ca.crt

echo "Creating initial certificates";
/create-nginx-config.php > /dev/null 2>&1
nginx &
sleep 1
pgrep nginx > /dev/null || { echo "Nginx did not start"; cat /var/log/nginx/error.log; exit; }
/create-nginx-config.php
echo "Waiting for docker events"
docker events --filter event=start --format=containerstart &
docker events --filter event=start --format=containerstart | xargs -t -n 1 /create-nginx-config.php
