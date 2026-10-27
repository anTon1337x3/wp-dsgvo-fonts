#!/usr/bin/env bash
# Starts WordPress with the fixtures, activates the plugin and checks that no page or stylesheet
# references Google Fonts or Font Awesome anymore, and that every local copy is served.
#
#   tests/e2e/run.sh          run the checks (keeps the site running on http://localhost:8089, admin/admin)
#   tests/e2e/run.sh down     remove containers and volumes
set -euo pipefail

cd "$(dirname "$0")"
SITE=http://localhost:8089

if [[ "${1:-}" == "down" ]]; then
	docker compose down -v
	exit 0
fi

docker compose up -d --wait db wordpress >/dev/null 2>&1

until curl -fs -o /dev/null "$SITE/wp-admin/install.php"; do sleep 1; done

wp() { docker compose run --rm -T cli "$@" 2>/dev/null; }

if ! wp core is-installed; then
	wp core install --url="$SITE" --title="WPDF E2E" --admin_user=admin --admin_password=admin --admin_email=admin@example.com --skip-email >/dev/null
	wp rewrite structure '/%postname%/' >/dev/null
fi

wp plugin activate wp-dsgvo-fonts >/dev/null
wp dsgvo-fonts flush >/dev/null

status=0
php check.php "$SITE" || status=1

echo "Seite prüfen (WP-CLI):"
if wp dsgvo-fonts scan; then echo "  ✓ Scanner findet keine Verweise"; else echo "  ✗ Scanner meldet Verweise"; status=1; fi

echo "Hinweise des Plugins:"
wp option get wpdf_errors --format=json || echo "  (keine)"

exit $status
