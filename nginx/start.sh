#!/bin/sh -
CERT_DOMAIN=${DOMAIN:-example.com}

if [[ ! -e /etc/ssl/certs/$CERT_DOMAIN/key.pem ]]; then
    mkdir -p /etc/ssl/certs/$CERT_DOMAIN
    openssl req -x509 -nodes -days 365 -newkey rsa:2048 -keyout /etc/ssl/certs/$CERT_DOMAIN/key.pem -out /etc/ssl/certs/$CERT_DOMAIN/full.pem \
      -subj "/C=CN/ST=Warwickshire/L=Leamington/O=OrgName/OU=IT Department/CN=$CERT_DOMAIN"
fi

nginx -g "daemon off;"
