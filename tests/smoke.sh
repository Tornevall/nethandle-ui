#!/usr/bin/env bash
set -euo pipefail

BASE_URL="${BASE_URL:-https://gateway.example.net/}"
TOKEN="${NETHANDLE_API_TOKEN:?Set NETHANDLE_API_TOKEN before running the smoke test}"

curl --fail-with-body --silent --show-error \
    --request POST \
    --header "X-API-TOKEN: ${TOKEN}" \
    --header "Content-Type: application/json" \
    --data '{"action":"status"}' \
    "${BASE_URL}"

printf '\n'
