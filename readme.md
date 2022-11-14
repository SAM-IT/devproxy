# DevProxy V2

This is a default configuration that will:
- Set up Smallstep CA as a local ACME server
- Set up docker-proxy to securely allow access to your docker socket
- Set up traefik to use the docker-proxy and ACME server for serving you local containers

Set up steps:
1. Clone this repo
2. Run `docker-compose run --rm bootstrap`
3. Install the generated CA certificate: `sudo cp ca-generated/certs/root_ca.crt /usr/local/share/ca-certificates/devproxyv2.crt; sudo update-ca-certificates`
4. Install the generated CA certificate in your browser
5. Run `docker-compose up -d traefik` it will automatically be restarted on boot
6. Configure DNS
6. Go to `https://traefik.devproxy.test` and you should see the container working.


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
All requests for the `.test` tld will be forwarded to our DNS container, which will resolve it to the traefik container.
Because bridge networks are accessible from the host we do not need to expose any ports on the Traefik container. Technically we don't even need to expose the dns server on localhost, were it not for the fact that we don't know its container IP.


## Configuring routing on windows
On Linux most things work out of the box, on Windows additional magic will be needed. You'll probably want to create a `docker-compose.override.yml` that exposes the Traefik container ports on localhost; Docker Desktop should than make that available to you from windows as well.

