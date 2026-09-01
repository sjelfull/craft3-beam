#!/usr/bin/env bash
set -euo pipefail

if docker info >/dev/null 2>&1; then
    exit 0
fi

if ! sudo service docker start; then
    sudo dockerd >/tmp/dockerd.log 2>&1 &
fi

for _ in {1..30}; do
    if docker info >/dev/null 2>&1; then
        exit 0
    fi
    sleep 1
done

echo "Docker did not become ready. See /tmp/dockerd.log." >&2
exit 1
