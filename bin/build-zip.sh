#!/usr/bin/env bash
# Builds dist/dsgvo-fonts.zip for WordPress.org: compiles the translations and leaves out everything
# listed in .distignore.
set -euo pipefail

cd "$(dirname "$0")/.."
SLUG=dsgvo-fonts
BUILD=$(mktemp -d)

for po in languages/*.po; do
	msgfmt -o "${po%.po}.mo" "$po"
done

rsync -a --exclude-from=.distignore ./ "$BUILD/$SLUG/"
mkdir -p dist
rm -f "dist/$SLUG.zip"
(cd "$BUILD" && zip -qr - "$SLUG") > "dist/$SLUG.zip"
rm -rf "$BUILD"

echo "dist/$SLUG.zip"
unzip -l "dist/$SLUG.zip" | tail -1
