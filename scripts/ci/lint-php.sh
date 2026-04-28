#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"

mapfile -d '' PHP_FILES < <(
    find "${ROOT_DIR}/src/app/code/Portfolio" \
        -path '*/frontend/node_modules' -prune -o \
        -name '*.php' -print0
)

if [ "${#PHP_FILES[@]}" -eq 0 ]; then
    echo "No custom PHP files found."
    exit 0
fi

status=0

for file in "${PHP_FILES[@]}"; do
    php -l "${file}" || status=1
done

exit "${status}"
