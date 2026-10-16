#!/bin/sh
# Set up the local browser-test WordPress: e2e/setup.sh /path/to/Divi.zip
# Divi is unpacked to ~/.cache/d5svgf-e2e (outside the repo; it is licensed).
set -eu
cd "$(dirname "$0")"

ZIP="${1:?usage: e2e/setup.sh /path/to/Divi.zip}"
CACHE="${XDG_CACHE_HOME:-$HOME/.cache}/d5svgf-e2e"
export DIVI_THEME_DIR="$CACHE/Divi"
PORT="${E2E_PORT:-8086}"
URL="http://localhost:$PORT"

if [ ! -f "$DIVI_THEME_DIR/style.css" ]; then
	mkdir -p "$CACHE"
	unzip -q "$ZIP" -d "$CACHE"
fi
echo "Divi $(sed -n 's/^Version: *//p' "$DIVI_THEME_DIR/style.css")"

[ -f ../vendor/autoload.php ] || echo "note: no vendor/ (composer install) - updater off, fine for tests"

docker compose up -d
# Wait for WordPress with a working database (500 = DB still starting).
until curl -s -o /dev/null -w '%{http_code}' "$URL" | grep -q '^[23]'; do sleep 2; done

wp() { docker compose run --rm -T cli wp "$@"; }
wp core is-installed 2>/dev/null || wp core install --url="$URL" --title=E2E --admin_user=admin --admin_password=admin --admin_email=e2e@example.com --skip-email
wp rewrite structure '/%postname%/'
wp theme activate Divi
wp plugin activate divi5-svg-filters
wp eval-file /fixtures/preset.php

for f in fixtures/*.html; do
	slug=$(basename "$f" .html)
	id=$(wp post list --post_type=page --name="$slug" --field=ID)
	if [ -z "$id" ]; then
		wp post create "/fixtures/$slug.html" --post_type=page --post_status=publish --post_title="$slug" --post_name="$slug" --meta_input='{"_et_pb_use_builder":"on"}' --porcelain
	else
		wp post update "$id" "/fixtures/$slug.html"
	fi
done
# Start the tests with an empty PHP log (setup itself may log while the DB starts).
docker compose exec -T wp rm -f wp-content/debug.log
echo "Ready: $URL (admin/admin). Run: cd e2e && pnpm install && pnpm test"
