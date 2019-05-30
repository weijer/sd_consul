#!/usr/bin/env bash
rm -rf consul_1.4.4_linux_amd64.zip \
&& wget -P `pwd`/bin/exec https://releases.hashicorp.com/consul/1.4.4/consul_1.4.4_linux_amd64.zip \
&& unzip `pwd`/bin/exec/consul_1.4.4_linux_amd64.zip -d `pwd`/bin/exec/
