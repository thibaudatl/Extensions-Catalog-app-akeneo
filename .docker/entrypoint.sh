#!/usr/bin/env bash
set -euo pipefail
cd /srv/app

echo ">> Waiting for database..."
if [ -n "${DATABASE_URL:-}" ]; then
  for i in $(seq 1 90); do
    if php -r 'try{$u=getenv("DATABASE_URL");$p=parse_url($u);$h=$p["host"]??"localhost";$port=$p["port"]??3306;$c=@fsockopen($h,$port,$e,$s,1);if($c){fclose($c);exit(0);}exit(1);}catch(Throwable $e){exit(1);}'; then
      break
    else
      echo "DB not ready yet..."
      sleep 2
    fi
  done
fi

if [ -d vendor ] && command -v composer >/dev/null 2>&1; then
  composer dump-autoload --classmap-authoritative --no-dev || true
fi

if [ -z "${APP_SECRET:-}" ]; then
  echo "ERROR: APP_SECRET environment variable is not set or is empty."
  exit 1
fi

echo ">> Symfony cache warmup"
php bin/console cache:clear --no-warmup --env=prod || true
php bin/console cache:warmup --env=prod || true

echo ">> Doctrine migrations"
if [ -d migrations ] || [ -d src/Migrations ]; then
  php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration || true
else
  echo ">> Skipping migrations: no migrations directory"
fi

echo ">> Fix permissions"
chown -R www-data:www-data var || true

echo ">> Start Apache"
exec apache2-foreground
