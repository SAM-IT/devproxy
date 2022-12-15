FROM traefik
ADD static.yml /static.yml
ENV LEGO_CA_CERTIFICATES=/stepca-data/certs/root_ca.crt
CMD ["traefik", "--configfile=/static.yml"]