#!/bin/sh
# Canonical copy; runtime uses web/scripts/docker-web-start.sh via the mounted /app volume.
exec sh /app/scripts/docker-web-start.sh
