#!/usr/bin/env bash

set -euo pipefail
cd "$(dirname "${BASH_SOURCE[0]}")/.."

TAILWIND="${TAILWIND_BIN:-bin/tailwindcss}"
ESBUILD="${ESBUILD_BIN:-bin/esbuild}"
SCREENS=(create view mine auth tags moderation admin audit)

echo "→ CSS (Tailwind)"
"$TAILWIND" -i assets-src/theme.css -o public/assets/app.css --minify

echo "→ JS (esbuild): un solo fichero por pantalla, con todos sus módulos dentro"
rm -rf public/assets/dist
entries=()
for name in "${SCREENS[@]}"; do entries+=("assets-src/js/${name}.js"); done
"$ESBUILD" "${entries[@]}" --bundle --format=esm --target=es2022 --minify --legal-comments=none \
  --outdir=public/assets/dist --log-level=warning
"$ESBUILD" assets-src/js/theme.js --bundle --format=iife --target=es2022 --minify --legal-comments=none \
  --outfile=public/assets/theme.js --log-level=warning

echo "→ Hashes SRI en las plantillas"
php bin/console app:assets:sri