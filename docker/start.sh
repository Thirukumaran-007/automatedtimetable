#!/usr/bin/env bash
set -euo pipefail

PORT="${PORT:-80}"

# Update Apache to listen on Render's assigned port
sed -ri "s/^Listen 80$/Listen ${PORT}/" /etc/apache2/ports.conf || true
sed -ri "s/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf || true

apache2-foreground
