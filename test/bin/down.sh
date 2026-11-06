#!/usr/bin/env bash
# Stop the test stack and remove containers/volumes.
set -euo pipefail

cd "$(dirname "$0")/../.."

docker compose -f test/docker-compose.yml down -v
