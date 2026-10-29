#!/usr/bin/env bash
# Builds dist/knuspermedia-local-fonts-icons.zip for WordPress.org, leaving out everything listed in .distignore.
set -euo pipefail

cd "$(dirname "$0")/.."
SLUG=knuspermedia-local-fonts-icons
BUILD=$(mktemp -d)

rsync -a --exclude-from=.distignore ./ "$BUILD/$SLUG/"
mkdir -p dist
rm -f "dist/$SLUG.zip"
(cd "$BUILD" && zip -qr - "$SLUG") > "dist/$SLUG.zip"
rm -rf "$BUILD"

echo "dist/$SLUG.zip"
unzip -l "dist/$SLUG.zip" | tail -1
