#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"

node --check "${ROOT_DIR}/services/mock-erp-api/src/server.js"
