#!/bin/bash

set -e

# Only this process (phx.server) should see dev — do not use Dockerfile ENV MIX_ENV=dev
# or `docker compose exec mix test` would load dev config (wrong DB pool for Sandbox).
export MIX_ENV=dev

mix deps.get

mix ecto.create 2>/dev/null || true

mix ecto.migrate

exec mix phx.server
