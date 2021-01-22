#!/usr/bin/env php
<?php

function get_eligible_containers(): array
{
    $ids = [];
    exec("docker ps -f label='com.awesam.proxy.domains' -q", $ids);
    return $ids;
}

function get_domains(string $container): array
{
    return explode(',', trim(shell_exec("docker inspect --format '{{ index .Config.Labels \"com.awesam.proxy.domains\" }}' $container")));
}

function get_ip(string $container): string
{
    return trim(shell_exec("docker inspect --format '{{ .NetworkSettings.Networks.devproxy.IPAddress }}' $container"));
}

function get_name(string $container): string
{
    return trim(shell_exec("docker inspect --format '{{ .Name }}' '$container'"), "\n/");
}

function create_server_block(
    string $container
): void {

    $serverName = implode(' ', array_map(function($hostname) {
        return "$hostname.*";
    }, get_domains($container)));
    $ip = get_ip($container);
    $name = get_name($container);
    $template = <<<NGINX
server {
  listen 443 ssl;
  listen 4430 ssl;
  ssl_certificate /tmp/certs/$name.crt;
  ssl_certificate_key /config/private.key;
  server_name $serverName;
  client_max_body_size 20m;
  location / {
      proxy_set_header X-Forwarded-For \$remote_addr;
      proxy_buffer_size 128k;
      proxy_read_timeout 300;
      proxy_send_timeout 300;
      proxy_buffers 4 256k;
      proxy_busy_buffers_size 256k;
      proxy_set_header Host \$http_host;
      proxy_set_header X-Forwarded-Port \$server_port;
      proxy_set_header X-Forwarded-Proto "https";
      proxy_pass http://$ip;
  }
}

NGINX;
    @mkdir("/tmp/nginxblocks", "0777", true);
    file_put_contents("/tmp/nginxblocks/$name.conf", $template);
}

function create_ssl_certificate(string $name, array $domains)
{
    if (empty($name)) {
        throw new \Exception('Name must not be empty');
    }

    $handle = tmpfile();
    $config = <<<OPENSSL
[req]
req_extensions = req_ext
distinguished_name = dn

[req_ext]
subjectAltName=@alt_names

[dn]

[alt_names]

OPENSSL;

    $i = 1;
    foreach($domains as $domain) {
        $config .= "DNS.$i=$domain.test\n";
        $i++;
        $config .= "DNS.$i=*.$domain.test\n";
        $i++;
    }
    fwrite($handle, $config);
    $configFile = stream_get_meta_data($handle)['uri'];
    echo file_get_contents($configFile);

    @mkdir('/tmp/certs', '0777', true);

    $cmd = "openssl req -new -key /config/private.key -subj '/CN=$name' -config $configFile | "
        . " openssl x509 -req -CA /config/ca.crt -extfile $configFile -extensions req_ext "
        . "-CAkey /config/private.key -CAcreateserial -days 10 -out /tmp/certs/$name.crt"
        ;

    passthru($cmd);
    
}


echo "Checking containers\n";
// Clear old blocks.
passthru('rm -rf /tmp/nginxblocks');

// Set up default site
create_ssl_certificate('devproxy', ['devproxy']);
passthru('mv /etc/nginx/conf.d/default.conf.disabled /etc/nginx/conf.d/defaultsite.conf');

foreach(get_eligible_containers() as $id) {
    $name = get_name($id);
    echo "Creating config for $name ($id)\n";
    $ip = get_ip($id);
    echo "Found IP: $ip\n";
    $domains = get_domains($id);
    echo "Creating SSL config\n";
    create_ssl_certificate($name, $domains);
    create_server_block($id);
//    foreach( as $domain) {
//        echo "Domain: $domain\n";
//    }
}



// Reload nginx config
passthru('nginx -s reload');