# DevProxy V2

This is a default configuration that will:
- Set up Smallstep CA as a local ACME server
- Set up docker-proxy to securely allow access to your docker socket
- Set up traefik to use the docker-proxy and ACME server for serving you local containers

Set up steps:
1. Clone this repo
2. Run `docker-compose run --rm bootstrap`
3. Create the network for devproxy: `docker network create devproxy2`
4. Run `docker-compose up -d traefik` it will automatically be restarted on boot
4. `sudo curl -k "https://$(docker inspect $(docker compose ps -q step-ca) | jq '.[0].NetworkSettings.Networks.devproxy2.IPAddress' -r):443/roots.pem" -o /usr/local/share/ca-certificates/devproxyv2.crt`
5. `sudo update-ca-certificates`
6. Install the generated CA certificate in your browser (it's located in `/usr/local/share/ca-certificates/devproxyv2.crt`)
7. Configure DNS
8. Your CLI should now support the certificate: `curl https://traefik.devproxy.test` should show **Moved permanently**
9. Go to `https://traefik.devproxy.test` and you should see the container working.


## Configuring DNS
For local development the `.test` domain should be used (as it is reserved for that in the relevant RFCs).

### Windows
If you are developing on Windows then you should probably move to Linux.
If that's not an option then your best bet is probably to update your hosts file for every new test project.
Alternatively you could configure your dns server in windows to be `localhost:53535` this will result in a bad experience though since your DNS won't work after login until your docker VM has booted.
You could also install your own DNS server and configure that as defined below...

### Linux
On Linux it is easy to simply reroute your DNS, we include a simple dnsmasq server that is used in internally by the CA to
resolve the domains to the Traefik container. It is also exposed on `localhost:53535`.
By adding this to a dnsmasq config file:
```
server=/.test/127.0.0.1#53535
```
You can do this by running: `echo server=/.test/127.0.0.1#53535 | sudo tee /etc/NetworkManager/dnsmasq.d/test.conf`

All requests for the `.test` tld will be forwarded to our DNS container, which will resolve it to the traefik container.
Because bridge networks are accessible from the host we do not need to expose any ports on the Traefik container. Technically we don't even need to expose the dns server on localhost, were it not for the fact that we don't know its container IP (and the fact that this container IP is not static).

Note that if your system is not using dnsmasq you might have to activate that first. For Ubuntu check out this tutorial: https://anto.online/guides/how-to-enable-and-disable-systemd-resolved-on-ubuntu/

## Configuring routing on windows
On Linux most things work out of the box, on Windows additional magic will be needed. You'll probably want to create a `docker-compose.override.yml` that exposes the Traefik container ports on localhost; Docker Desktop should then make that available to you from windows as well.