#!/bin/sh

set -eu

TAILWIND_VERSION="$1"; ESBUILD_VERSION="$2"; DEST="$3"

case "$(uname -m)" in
  x86_64)        TW=tailwindcss-linux-x64;   ES=linux-x64 ;;
  aarch64|arm64) TW=tailwindcss-linux-arm64; ES=linux-arm64 ;;
  *) echo "Arquitectura no soportada: $(uname -m)" >&2; exit 1 ;;
esac

if [ "$TAILWIND_VERSION" = "latest" ]; then
  BASE="https://github.com/tailwindlabs/tailwindcss/releases/latest/download"
else
  BASE="https://github.com/tailwindlabs/tailwindcss/releases/download/$TAILWIND_VERSION"
fi

curl -fsSL "$BASE/$TW" -o "$DEST/tailwindcss"
chmod 0755 "$DEST/tailwindcss"

if curl -fsSL "$BASE/sha256sums.txt" -o /tmp/tw-sums.txt; then
  expected="$(grep -E "[[:space:]](\./)?$TW\$" /tmp/tw-sums.txt | awk '{print $1}' | head -n 1)"
  [ -n "$expected" ] || { echo "No hay SHA-256 publicado para $TW" >&2; exit 1; }
  echo "$expected  $DEST/tailwindcss" | sha256sum -c -
else
  echo "AVISO: no se pudo descargar sha256sums.txt; Tailwind queda sin verificar." >&2
fi

curl -fsSL "https://registry.npmjs.org/@esbuild/$ES/-/$ES-$ESBUILD_VERSION.tgz" | tar -xz -C /tmp package/bin/esbuild
install -m 0755 /tmp/package/bin/esbuild "$DEST/esbuild"
rm -rf /tmp/package /tmp/tw-sums.txt

"$DEST/tailwindcss" --help | head -n 1
"$DEST/esbuild" --version