#!/usr/bin/env bash
# Disposable local Antragsgrün stack for browser verification of the plugin.
#
#   scripts/test-stack.sh up     start, create schema and site, select the layout
#   scripts/test-stack.sh seed   add the /test consultation (fixture data)
#   scripts/test-stack.sh reload recreate the web container, keeping the data
#   scripts/test-stack.sh down   remove containers and all data
#
# Same image digests as production (scripts/test-stack/compose.yml), the plugin
# mounted read-only, throwaway credentials, database on tmpfs. Serves
# http://127.0.0.1:${TEST_STACK_PORT:-18190}; admin login admin@example.org /
# test-admin-pw. TEST_STACK_APP_IMAGE overrides the application image to try an
# upgrade candidate. Never point it at production data.

set -euo pipefail

ROOT_DIR=$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)
COMPOSE=(docker compose -f "$ROOT_DIR/scripts/test-stack/compose.yml")
PORT=${TEST_STACK_PORT:-18190}

sql() {
  "${COMPOSE[@]}" exec -T mysql mariadb -uantragsgruen -ptest-only antragsgruen -e "$1"
}

# Schema lookups are cached in Redis. Any request made before the schema
# existed leaves "table missing" cached, so flush after every structural step.
flush_cache() {
  "${COMPOSE[@]}" exec -T redis redis-cli FLUSHALL >/dev/null
}

# The plugin is bind-mounted and read by www-data. A checkout made under a
# restrictive umask (077) leaves it unreadable and every page returns 500.
require_readable_plugin() {
  local unreadable
  unreadable=$(find "$ROOT_DIR/plugins" ! -perm -o=r | head -3)
  if [[ -n "$unreadable" ]]; then
    printf 'plugin files are not world-readable (umask?), e.g.:\n%s\nfix: chmod -R a+rX %s/plugins\n' \
      "$unreadable" "$ROOT_DIR" >&2
    exit 1
  fi
}

case "${1:-}" in
  up)
    require_readable_plugin
    "${COMPOSE[@]}" up -d --wait
    "${COMPOSE[@]}" exec -T web sh -euc '
      cd /var/www/html
      printf "{\"plugins\": [\"spd_layout\"]}\n" > config/config.json
      touch config/DEBUG
      chown -R www-data:www-data config
      php yii database/create
      php yii site/create admin@example.org --title="SPD Testparteitag" \
        --contact="Test, Beispielstr. 1" --language=de --password=test-admin-pw \
        --forcePasswordChange=0 --subdomain=std >/dev/null
      rm -f config/DEBUG'
    sql "UPDATE site SET settings = JSON_SET(COALESCE(settings,'{}'), '\$.siteLayout', 'layout-plugin-spd_layout-std') WHERE id = 1;"
    flush_cache
    printf 'test stack up: http://127.0.0.1:%s/std\n' "$PORT"
    ;;
  seed)
    "${COMPOSE[@]}" exec -T -e YII_ENV=test -e ANTRAGSGRUEN_FIXTURE_MODE=1 web \
      php /var/www/html/scripts/seed_test_consultation.php --fixture --confirm
    flush_cache
    ;;
  reload)
    # Bind mounts follow the directory inode. Git can replace a plugin
    # directory on checkout, which leaves the container looking at an empty
    # one ("Class ... not found"). Recreating web remounts it.
    require_readable_plugin
    "${COMPOSE[@]}" up -d --force-recreate --wait web
    flush_cache
    ;;
  down)
    "${COMPOSE[@]}" down -v
    ;;
  *)
    echo "usage: $0 up|seed|reload|down" >&2
    exit 2
    ;;
esac
