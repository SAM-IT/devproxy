#!/bin/sh

echo Starting stuff
mkdir -p /config/certs
# Create private key, used for CA as well as all certificates.
cd /config
if [ ! -f openssl.cnf ]; then
  cp /etc/ssl/openssl.cnf /config
fi
if [ ! -f private.key ]; then
  echo "Creating new private key";
  openssl genrsa -out private.key 4096
fi

if [ ! -f ca.crt ]; then
  echo "Creating new CA certificate";
  openssl req -x509 -new -nodes -key private.key -sha256 -days 1024 -out ca.crt -subj "/C=NL/CN=DevProxy"
fi

configFile=$(mktemp /tmp/csr.XXXXXX)
config="
[req]
req_extensions = req_ext
distinguished_name = dn

[req_ext]
subjectAltName=@alt_names

[dn]

[alt_names]
DNS.1=devproxy.test
DNS.2=*.test
"

#cat /etc/ssl/openssl.cnf > $configFile
echo "$config" > $configFile
echo Creating sample cert
openssl req \
  -new \
  -key private.key \
  -subj "/CN=devproxy.test" \
  -config $configFile \
  | openssl x509 -req -CA ca.crt -extfile $configFile -extensions req_ext -CAkey private.key -CAcreateserial -days 10 -out certs/devproxy.crt

 openssl x509 -in certs/devproxy.crt -text -noout | grep DNS

rm $configFile
nginx &
sleep 5
/create-nginx-config.php
echo "Waiting for docker events"
docker events --filter event=start --format=containerstart &
docker events --filter event=start --format=containerstart | xargs -t -n 1 /create-nginx-config.php
