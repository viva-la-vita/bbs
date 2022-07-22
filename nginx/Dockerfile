FROM nginx:1.23-alpine AS development

RUN wget -O /usr/local/bin/dumb-init https://github.com/Yelp/dumb-init/releases/download/v1.2.5/dumb-init_1.2.5_x86_64 && chmod +x /usr/local/bin/dumb-init

ENTRYPOINT ["/usr/local/bin/dumb-init", "--"]

CMD ["nginx", "-g", "daemon off;"]

FROM development AS production

WORKDIR /etc/nginx

COPY . .
