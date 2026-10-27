#!/usr/bin/env bash
#
# Vérifications locales, équivalentes au workflow GitHub « Plugin Check » :
#   1. php -l sur tous les fichiers PHP
#   2. Plugin Check (WP-CLI) avec le slug WordPress.org
#
# Usage : bin/check.sh [--all]
#   --all   affiche aussi le détail de chaque avertissement
#
# Détecte automatiquement un site Local (by Flywheel) : PHP du site, socket MySQL et wp-cli.phar.
# Surcharges possibles : PHP_BIN, WP_CLI (commande wp complète, ex. "wp"), WP_PATH.
# Code de sortie 1 si une erreur est trouvée (même règle que la CI).

set -euo pipefail

PLUGIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PLUGIN_SLUG="$(basename "$PLUGIN_DIR")"
WP_ORG_SLUG="agency-client-portal"
EXCLUDE_DIRS="design,.github,.wordpress-org,bin,dist,node_modules,vendor"
EXCLUDE_FILES=".gitignore,.distignore,AGENTS.md,CLAUDE.md"
SHOW_ALL=0
[ "${1:-}" = "--all" ] && SHOW_ALL=1

LOCAL_SUPPORT="$HOME/Library/Application Support/Local"
LOCAL_WPCLI="/Applications/Local.app/Contents/Resources/extraResources/bin/wp-cli/wp-cli.phar"

TMP_DIR="$(mktemp -d)"
trap 'rm -rf "$TMP_DIR"' EXIT

# Racine WordPress : premier dossier parent contenant wp-config.php
WP_PATH="${WP_PATH:-}"
if [ -z "$WP_PATH" ]; then
	dir="$PLUGIN_DIR"
	while [ "$dir" != "/" ]; do
		if [ -f "$dir/wp-config.php" ]; then WP_PATH="$dir"; break; fi
		dir="$(dirname "$dir")"
	done
fi
if [ -z "$WP_PATH" ]; then
	echo "WordPress root not found (no wp-config.php above $PLUGIN_DIR). Set WP_PATH." >&2
	exit 2
fi

# N'importe quel PHP pour lire la config de Local
any_php() {
	if command -v php >/dev/null 2>&1; then command -v php; return; fi
	ls "$LOCAL_SUPPORT"/lightning-services/php-*/bin/darwin*/bin/php 2>/dev/null | tail -1
}

# Site Local correspondant : id + version de PHP, lus dans sites.json
SITE_ID=""
SITE_PHP_VERSION=""
if [ -f "$LOCAL_SUPPORT/sites.json" ] && [ -n "$(any_php)" ]; then
	read -r SITE_ID SITE_PHP_VERSION < <("$(any_php)" -r '
		$sites = json_decode(file_get_contents($argv[1]), true) ?: array();
		foreach ($sites as $site) {
			$path = str_replace("~", getenv("HOME"), $site["path"]) . "/app/public";
			if (realpath($path) === realpath($argv[2])) {
				echo $site["id"], " ", isset($site["services"]["php"]["version"]) ? $site["services"]["php"]["version"] : "", PHP_EOL;
				exit;
			}
		}' "$LOCAL_SUPPORT/sites.json" "$WP_PATH" || true)
fi

# Binaire PHP
if [ -z "${PHP_BIN:-}" ]; then
	if [ -n "$SITE_PHP_VERSION" ]; then
		PHP_BIN="$(ls "$LOCAL_SUPPORT"/lightning-services/php-"$SITE_PHP_VERSION"+*/bin/darwin*/bin/php 2>/dev/null | head -1 || true)"
	fi
	PHP_BIN="${PHP_BIN:-$(any_php)}"
fi
if [ -z "$PHP_BIN" ]; then
	echo "PHP not found. Set PHP_BIN." >&2
	exit 2
fi

# Socket MySQL du site Local (sinon : la config de wp-config.php s'applique telle quelle)
INI="$TMP_DIR/php.ini"
printf 'memory_limit=1024M\nerror_reporting=E_ALL & ~E_DEPRECATED\n' > "$INI"
if [ -n "$SITE_ID" ]; then
	SOCKET="$LOCAL_SUPPORT/run/$SITE_ID/mysql/mysqld.sock"
	if [ ! -S "$SOCKET" ]; then
		echo "MySQL socket not found: is the Local site running?" >&2
		exit 2
	fi
	printf 'mysqli.default_socket=%s\npdo_mysql.default_socket=%s\n' "$SOCKET" "$SOCKET" >> "$INI"
fi

# Commande WP-CLI
if [ -n "${WP_CLI:-}" ]; then
	read -r -a WP <<< "$WP_CLI"
elif [ -f "$LOCAL_WPCLI" ]; then
	WP=("$PHP_BIN" -c "$INI" "$LOCAL_WPCLI")
elif command -v wp >/dev/null 2>&1; then
	WP=(wp)
else
	echo "WP-CLI not found. Set WP_CLI." >&2
	exit 2
fi

echo "==> PHP lint ($("$PHP_BIN" -r 'echo PHP_VERSION;'))"
lint_failed=0
while IFS= read -r -d '' file; do
	if ! out="$("$PHP_BIN" -l "$file" 2>&1)"; then
		echo "$out"
		lint_failed=1
	fi
done < <(find "$PLUGIN_DIR" -name '*.php' -not -path '*/design/*' -not -path '*/vendor/*' -not -path '*/node_modules/*' -print0)
[ "$lint_failed" -eq 0 ] && echo "No syntax errors."

echo
echo "==> Plugin Check ($PLUGIN_SLUG, slug $WP_ORG_SLUG)"
"${WP[@]}" --path="$WP_PATH" plugin is-installed plugin-check 2>/dev/null || {
	echo "The Plugin Check plugin is not installed: wp plugin install plugin-check --activate" >&2
	exit 2
}
RESULTS="$TMP_DIR/results.csv"
"${WP[@]}" --path="$WP_PATH" plugin check "$PLUGIN_SLUG" \
	--slug="$WP_ORG_SLUG" \
	--exclude-directories="$EXCLUDE_DIRS" \
	--exclude-files="$EXCLUDE_FILES" \
	--format=csv --fields=line,type,code,message > "$RESULTS" 2>/dev/null || true

# Résumé : erreurs en détail, avertissements groupés par code
awk -v show_all="$SHOW_ALL" -F, '
	/^FILE: / { file = substr($0, 7); next }
	$2 == "ERROR"   { errors++;   print "  ERROR   " file ":" $1 "  " $3; next }
	$2 == "WARNING" { warnings++; codes[$3]++; if (show_all) print "  warning " file ":" $1 "  " $3 }
	END {
		if (!show_all && warnings) {
			print "  Warnings by code (bin/check.sh --all for details):"
			for (c in codes) printf "    %4d  %s\n", codes[c], c
		}
		printf "\n%d error(s), %d warning(s)\n", errors, warnings
	}' "$RESULTS"

if [ "$lint_failed" -ne 0 ] || grep -q ',ERROR,' "$RESULTS"; then
	exit 1
fi
