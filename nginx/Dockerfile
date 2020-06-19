FROM nginx:1.17-alpine

ENV LANG en_US.UTF-8
ENV LANGUAGE en_US.UTF-8
ENV LC_ALL=en_US.UTF-8

RUN sed -i 's/dl-cdn.alpinelinux.org/mirrors.ustc.edu.cn/g' /etc/apk/repositories && \
    echo "Asia/Shanghai" > /etc/timezone

ARG DOMAIN

RUN apk add openssl curl \
    && curl https://ssl-config.mozilla.org/ffdhe2048.txt > /etc/ssl/dhparam.pem \
    && mkdir /etc/ssl/certs/${DOMAIN:-example.com} \
    && openssl req -x509 -nodes -days 365 -newkey rsa:2048 -keyout /etc/ssl/certs/${DOMAIN:-example.com}/key.pem -out /etc/ssl/certs/${DOMAIN:-example.com}/full.pem \
      -subj "/C=CN/ST=Warwickshire/L=Leamington/O=OrgName/OU=IT Department/CN=${DOMAIN:-example.com}" \
    && rm /var/cache/apk/*

# container init
RUN wget -O /usr/local/bin/dumb-init https://github.com/Yelp/dumb-init/releases/download/v1.2.1/dumb-init_1.2.1_amd64 && \
    echo "057ecd4ac1d3c3be31f82fc0848bf77b1326a975b4f8423fe31607205a0fe945  /usr/local/bin/dumb-init" | sha256sum -c - && \
    chmod 755 /usr/local/bin/dumb-init

COPY start.sh /root/start.sh
RUN chmod +x /root/start.sh

ENTRYPOINT ["/usr/local/bin/dumb-init", "--"]
CMD [ "/root/start.sh" ]
