#!/usr/bin/env php
<?php

function get_eligible_containers(): array
{
    $ids = [];
    exec("docker ps -f label='com.awesam.proxy.domains' -q", $ids);
    return $ids;
}

/**
 * Get all the needed info from a container in a single call
 * @param string $container
 */
function get_container_info(string $container): array
{
    $elements = [
            'domains' => 'split (index .Config.Labels "com.awesam.proxy.domains") ","',
            'tld' => 'index .Config.Labels "com.awesam.proxy.tld"',
            'port' => 'index .Config.Labels "com.awesam.proxy.port"',
            'ip' => '.NetworkSettings.Networks.devproxy.IPAddress',
            'name' => 'slice .Name 1'
    ];

    $coded = [];
    foreach($elements as $key => $value) {
        $coded[] = json_encode($key) . " : {{ json ( $value ) }}";
    }
    $format = '{' . implode(",\n", $coded) . '}';
    $command = "docker inspect --format '$format' $container";

    $result = json_decode(shell_exec($command), true);

    $result['port'] = (int) (empty($result['port']) ? 80: $result['port']);
    $result['tld'] = empty($result['tld']) ? 'test' : $result['tld'];
    return $result;
}

function create_server_block(
    array $domains,
    string $name,
    string $ip,
    int $port,
    string $tld
): void {

    $serverName = implode(' ', array_map(function($hostname) use ($tld) {
        return "$hostname.{$tld}";
    }, $domains));
    $template = <<<NGINX
upstream $name {
  server $ip:$port;
  server localhost:81 backup;
}
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
      proxy_connect_timeout 2s;
      proxy_buffers 4 256k;
      proxy_busy_buffers_size 256k;
      proxy_set_header Host \$http_host;
      proxy_set_header X-Forwarded-Port \$server_port;
      proxy_set_header X-Forwarded-Proto "https";
      proxy_pass http://$name;
  }
}

NGINX;
    @mkdir("/tmp/nginxblocks", "0777", true);
    file_put_contents("/tmp/nginxblocks/$name.conf", $template);
}

function create_ssl_certificate(string $name, array $domains, string $tld)
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
        $domain = trim($domain);
        $config .= "DNS.$i=$domain.$tld\n";
        $i++;
        $config .= "DNS.$i=*.$domain.$tld\n";
        $i++;
    }
    fwrite($handle, $config);
    $configFile = stream_get_meta_data($handle)['uri'];
    @mkdir('/tmp/certs', '0777', true);

    $cmd = "openssl req -new -key /config/private.key -subj '/CN=$name' -config $configFile | "
        . " openssl x509 -req -CA /config/ca.crt -extfile $configFile -extensions req_ext "
        . "-CAkey /config/private.key -CAcreateserial -days 10 -out /tmp/certs/$name.crt"
        ;

    passthru($cmd, $result);
    if ($result !== 0) {
        die("Failed to create certificate");
    }
    
}


echo "Checking containers\n";
// Clear old blocks.
passthru('rm -rf /tmp/nginxblocks');

// Set up default site
create_ssl_certificate('devproxy', ['devproxy'], 'test');

foreach(get_eligible_containers() as $id) {
    $details = get_container_info($id);
    $name = $details['name'];
    echo "Creating config for $name ($id)\n";
    $ip = $details['ip'];
    echo "Found IP: $ip\n";
    $domains = $details['domains'];
    echo "Creating SSL config\n";
    create_ssl_certificate($name, $domains, $details['tld']);
    create_server_block($domains, $name, $ip, $details['port'], $details['tld']);
}



// Reload nginx config
if (file_exists('/run/nginx/nginx.pid')) {
    passthru('nginx -s reload');
}
