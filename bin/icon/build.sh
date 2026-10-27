#!/usr/bin/env bash
# Builds the WordPress.org icon into .wordpress-org/: icon.svg, icon-128x128.png, icon-256x256.png.
#
# Needs Python with fontTools (pip install fonttools) and Google Chrome for rendering the PNGs.
# The font, Figtree (SIL Open Font License), is downloaded from Google's font repository.
#
#   PYTHON=/path/to/venv/bin/python bin/icon/build.sh
set -euo pipefail

cd "$(dirname "$0")/../.."
PYTHON=${PYTHON:-python3}
CHROME=${CHROME:-"/Applications/Google Chrome.app/Contents/MacOS/Google Chrome"}
OUT=.wordpress-org
TMP=$(mktemp -d)
trap 'rm -rf "$TMP"' EXIT

curl -sSfL -o "$TMP/Figtree.ttf" "https://github.com/google/fonts/raw/main/ofl/figtree/Figtree%5Bwght%5D.ttf"
"$PYTHON" -I bin/icon/glyphs.py "$TMP/Figtree.ttf" Gg 600 82 > "$TMP/glyphs.json"

mkdir -p "$OUT"
php bin/icon/make-icon.php "$TMP/glyphs.json" > "$OUT/icon.svg"

for size in 128 256; do
	cat > "$TMP/render.html" <<EOF
<!doctype html><html><body style="margin:0;background:transparent"><img src="file://$PWD/$OUT/icon.svg" width="$size" height="$size" style="display:block"></body></html>
EOF
	"$CHROME" --headless=new --disable-gpu --hide-scrollbars --default-background-color=00000000 \
		--window-size="$size,$size" --screenshot="$OUT/icon-${size}x${size}.png" "file://$TMP/render.html" >/dev/null 2>&1
done

ls -la "$OUT"
