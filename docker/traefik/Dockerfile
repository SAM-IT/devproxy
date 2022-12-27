FROM traefik
ADD static.yml /static.yml
ENV LEGO_CA_CERTIFICATES=/stepca-data/certs/root_ca.crt
ENV HTTPREQ_ENDPOINT=http://dnsserve:8082
# ENV HTTPREQ_ENDPOINT=https://webhook.site/aa91478f-fd85-462f-af3b-7b5d49fd5691
CMD ["traefik", "--configfile=/static.yml"]