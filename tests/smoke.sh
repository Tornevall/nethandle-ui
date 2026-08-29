#!/usr/bin/env bash
set -euo pipefail

BASE_URL="${BASE_URL:-https://gateway.example.net/}"
TOKEN="${NETHANDLE_API_TOKEN:?Set NETHANDLE_API_TOKEN before running the smoke test}"
TARGET="${NETHANDLE_TEST_TARGET:-testuser}"

request() {
    local action="$1"

    curl --fail-with-body --silent --show-error \
        --request POST \
        --header "X-API-TOKEN: ${TOKEN}" \
        --header "Content-Type: application/json" \
        --data "{\"target\":\"${TARGET}\",\"action\":\"${action}\"}" \
        "${BASE_URL}"

    printf '\n'
}

request status
