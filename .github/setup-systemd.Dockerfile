ARG UBUNTU_VERSION
FROM ubuntu:${UBUNTU_VERSION}

ENV container=docker

STOPSIGNAL SIGRTMIN+3

RUN apt-get update \
    && DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends \
        ca-certificates \
        dbus \
        systemd \
        systemd-sysv \
    && rm -rf /var/lib/apt/lists/*

CMD ["/sbin/init"]
