#!/usr/bin/env bash

protoc_bin="/usr/local/protobuf-3.6.1/bin/protoc"
php_out="/data/php/chat/src/proto/"

proto_file=(
./chat.proto
)


if [[ ! -e ${php_out} ]]; then
    mkdir ${php_out}
fi

${protoc_bin} --php_out=${php_out} ${proto_file[*]}