#!/bin/bash
# ─────────────────────────────────────────────
#  Deploy Script — Build Vue SPA & sync to Laravel
# ─────────────────────────────────────────────
#  Usage: bash deploy.sh
#  Run from the project root (parent of flash/ and flash-vue/)
# ─────────────────────────────────────────────

set -e

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
VUE_DIR="$SCRIPT_DIR/flash-vue"
LARAVEL_DIR="$SCRIPT_DIR/flash"
BACKEND_ENV="$LARAVEL_DIR/.env"
FRONTEND_ENV="$VUE_DIR/.env"
BACKEND_PRODUCTION_ENV="$LARAVEL_DIR/.env.production"
FRONTEND_PRODUCTION_ENV="$VUE_DIR/.env.production.local"
DEPLOY_TS=$(date +%s)

assert_file_exists() {
	local path="$1"
	local label="$2"

	if [ ! -f "$path" ]; then
		echo "ERROR: Missing $label: $path"
		exit 1
	fi
}

merge_env_template() {
	local template_path="$1"
	local target_path="$2"
	local current_path="$3"
	local label="$4"
	local tmp_file="${target_path}.tmp.$$"
	local preserved_count=0

	assert_file_exists "$template_path" "$label"
	cp -f "$template_path" "$tmp_file"

	if [ -f "$current_path" ]; then
		while IFS= read -r line; do
			local key
			key="$(printf '%s\n' "$line" | sed -E 's/^[[:space:]]*([A-Za-z_][A-Za-z0-9_]*)=.*/\1/')"
			if ! grep -q -E "^[[:space:]]*${key}=" "$tmp_file"; then
				if [ "$preserved_count" -eq 0 ]; then
					printf '\n# Preserved from existing .env during deploy\n' >> "$tmp_file"
				fi
				printf '%s\n' "$line" >> "$tmp_file"
				preserved_count=$((preserved_count + 1))
			fi
		done < <(grep -E '^[[:space:]]*[A-Za-z_][A-Za-z0-9_]*=' "$current_path")
	fi

	mv -f "$tmp_file" "$target_path"

	if [ "$preserved_count" -gt 0 ]; then
		echo "  $label -> $(basename "$target_path") (preserved $preserved_count extra key(s))"
	else
		echo "  $label -> $(basename "$target_path")"
	fi
}

ensure_production_env() {
	echo "══════════════════════════════════════════"
	echo "  Preparing production env files..."
	echo "══════════════════════════════════════════"

	merge_env_template "$BACKEND_PRODUCTION_ENV" "$BACKEND_ENV" "$BACKEND_ENV" "Backend production env"
	merge_env_template "$FRONTEND_PRODUCTION_ENV" "$FRONTEND_ENV" "$FRONTEND_ENV" "Frontend production env"

	if ! grep -q '^APP_ENV=production$' "$BACKEND_ENV"; then
		echo "ERROR: Backend .env is not in production mode after sync."
		exit 1
	fi

	if ! grep -q '^VITE_API_BASE_URL=https://klek.studio/api$' "$FRONTEND_ENV"; then
		echo "ERROR: Frontend .env is not pointing at the production API after sync."
		exit 1
	fi

	echo ""
}

copy_optional_file() {
	local source_path="$1"
	local target_path="$2"

	if [ -f "$source_path" ]; then
		mkdir -p "$(dirname "$target_path")"
		cp -f "$source_path" "$target_path"
	fi
}

copy_optional_dir() {
	local source_path="$1"
	local target_path="$2"

	if [ -d "$source_path" ]; then
		mkdir -p "$(dirname "$target_path")"
		cp -r "$source_path" "$target_path"
	fi
}

ensure_production_env

echo "══════════════════════════════════════════"
echo "  🔨 Building Vue SPA..."
echo "══════════════════════════════════════════"
cd "$VUE_DIR"

# Clean old build output first
rm -rf dist

npm run build

if [ ! -d "$VUE_DIR/dist/assets" ]; then
	echo "ERROR: Missing dist/assets after build."
	exit 1
fi

if [ ! -f "$VUE_DIR/dist/asset-manifest.json" ]; then
	echo "ERROR: Missing dist/asset-manifest.json after build."
	exit 1
fi

echo ""
echo "══════════════════════════════════════════"
echo "  📦 Syncing build to Laravel public/..."
echo "══════════════════════════════════════════"

# Remove ALL old frontend assets to prevent stale cache
rm -rf "$LARAVEL_DIR/public/assets"
rm -rf "$LARAVEL_DIR/public/build"
rm -rf "$LARAVEL_DIR/public/icons"
cp -r "$VUE_DIR/dist/assets" "$LARAVEL_DIR/public/assets"
mkdir -p "$LARAVEL_DIR/public/build"
chmod 755 "$LARAVEL_DIR/public/build"
copy_optional_dir "$VUE_DIR/dist/icons" "$LARAVEL_DIR/public/icons"
cp -f "$VUE_DIR/dist/asset-manifest.json" "$LARAVEL_DIR/public/build/asset-manifest.json"

# Copy root public files
copy_optional_file "$VUE_DIR/dist/manifest.json" "$LARAVEL_DIR/public/manifest.json"
copy_optional_file "$VUE_DIR/dist/robots.txt" "$LARAVEL_DIR/public/robots.txt"
copy_optional_file "$VUE_DIR/dist/sitemap.xml" "$LARAVEL_DIR/public/sitemap.xml"
copy_optional_file "$VUE_DIR/dist/favicon.ico" "$LARAVEL_DIR/public/favicon.ico"
copy_optional_file "$VUE_DIR/dist/newlogo.png" "$LARAVEL_DIR/public/newlogo.png"
copy_optional_file "$VUE_DIR/dist/klek-ai-mark.svg" "$LARAVEL_DIR/public/klek-ai-mark.svg"
copy_optional_file "$VUE_DIR/dist/flash-ai-mark.svg" "$LARAVEL_DIR/public/flash-ai-mark.svg"

# Copy & stamp the service worker with deploy timestamp to bust SW cache
copy_optional_file "$VUE_DIR/dist/sw.js" "$LARAVEL_DIR/public/sw.js"
if [ -f "$LARAVEL_DIR/public/sw.js" ]; then
	sed -i "s/__DEPLOY_TIMESTAMP__/$DEPLOY_TS/g" "$LARAVEL_DIR/public/sw.js"
fi
printf '{"deployedAt":%s}\n' "$DEPLOY_TS" > "$LARAVEL_DIR/public/build/deploy-info.json"

echo ""
echo "══════════════════════════════════════════"
echo "  🧩 Syncing PHP dependencies..."
echo "══════════════════════════════════════════"
cd "$LARAVEL_DIR"

COMPOSER_INSTALLED_FILE="$LARAVEL_DIR/vendor/composer/installed.php"

if [ ! -f "$LARAVEL_DIR/vendor/autoload.php" ] || [ ! -f "$COMPOSER_INSTALLED_FILE" ] || [ "$LARAVEL_DIR/composer.lock" -nt "$COMPOSER_INSTALLED_FILE" ]; then
	if command -v composer >/dev/null 2>&1; then
		composer install --no-interaction --prefer-dist --optimize-autoloader
	else
		echo "  Composer not found in PATH. Run 'composer install --no-interaction --prefer-dist --optimize-autoloader' before caching Laravel."
	fi
else
	echo "  PHP dependencies are up to date."
fi

echo ""
echo "══════════════════════════════════════════"
echo "  Ensuring service account credentials..."
echo "══════════════════════════════════════════"
SA_DIR="$LARAVEL_DIR/storage/app/google"
SA_FILE="$SA_DIR/service-account.json"
SA_SOURCE="$SCRIPT_DIR/project-1c28556b-fd90-4d6b-a4f-a1db7dec9316.json"

mkdir -p "$SA_DIR"
if [ -f "$SA_SOURCE" ]; then
	cp -f "$SA_SOURCE" "$SA_FILE"
	chmod 600 "$SA_FILE"
	echo "  ✓ Service account copied to $SA_FILE"
elif [ -f "$SA_FILE" ]; then
	echo "  ✓ Service account already exists at $SA_FILE"
else
	echo "  ⚠ WARNING: No service account found! AI features will not work."
	echo "    Expected: $SA_SOURCE or $SA_FILE"
fi

echo ""
echo "══════════════════════════════════════════"
echo "  Optimizing Laravel for production..."
echo "══════════════════════════════════════════"
rm -f "$LARAVEL_DIR/bootstrap/cache/"*.php
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan event:clear
php artisan package:discover --ansi
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo ""
echo "══════════════════════════════════════════"
echo "  ✅ Deploy complete!"
echo "══════════════════════════════════════════"
echo "  Frontend → https://klek.studio/"
echo "  API      → https://klek.studio/api"
echo "══════════════════════════════════════════"
