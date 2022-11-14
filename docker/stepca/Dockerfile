FROM smallstep/step-ca
USER 0
RUN apk add --no-cache bind-tools
ADD entrypoint.sh /
ADD bootstrap.sh /
ENTRYPOINT ["/entrypoint.sh"]