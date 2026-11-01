#!/usr/bin/env bash
# ------------------------------------------------------------
#  Masar — backup of the database and uploaded records
#
#  Usage:   bash tools/backup.sh [destination-dir]
#  Default: <project>/backups  (blocked from web access)
#
#  Keeps the newest 14 backups. Suggested daily cron entry
#  (crontab -e as a user that can read .env and storage/):
#    15 3 * * * bash /var/www/html/masar/tools/backup.sh
# ------------------------------------------------------------

set -euo pipefail

PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DEST_DIR="${1:-$PROJECT_DIR/backups}"
KEEP=14
STAMP="$(date +%Y-%m-%d_%H%M)"

# Read one value from .env without executing the file.
env_value() {
    local key="$1" default="${2:-}"
    local line
    line="$(grep -E "^[[:space:]]*${key}=" "$PROJECT_DIR/.env" 2>/dev/null | tail -n 1 || true)"
    line="${line#*=}"
    line="${line%\"}"; line="${line#\"}"
    line="${line%\'}"; line="${line#\'}"
    echo "${line:-$default}"
}

DB_HOST="$(env_value DB_HOST localhost)"
DB_NAME="$(env_value DB_NAME masar_db)"
DB_USER="$(env_value DB_USER root)"
DB_PASSWORD="$(env_value DB_PASSWORD)"

mkdir -p "$DEST_DIR"
chmod 700 "$DEST_DIR"

# Pass the password through a temporary option file, not the command line.
OPTIONS_FILE="$(mktemp)"
trap 'rm -f "$OPTIONS_FILE"' EXIT
chmod 600 "$OPTIONS_FILE"
printf '[client]\nhost=%s\nuser=%s\npassword=%s\n' \
    "$DB_HOST" "$DB_USER" "$DB_PASSWORD" > "$OPTIONS_FILE"

mysqldump --defaults-extra-file="$OPTIONS_FILE" \
    --single-transaction --routines --triggers "$DB_NAME" \
    | gzip > "$DEST_DIR/masar_db_$STAMP.sql.gz"

tar -czf "$DEST_DIR/masar_records_$STAMP.tar.gz" \
    -C "$PROJECT_DIR/storage" academic-records

# Rotate: keep the newest $KEEP of each kind.
for pattern in 'masar_db_*.sql.gz' 'masar_records_*.tar.gz'; do
    find "$DEST_DIR" -maxdepth 1 -name "$pattern" -printf '%T@ %p\n' \
        | sort -rn | tail -n +$((KEEP + 1)) | cut -d' ' -f2- \
        | xargs -r rm -f
done

echo "Backup written to $DEST_DIR ($STAMP)"
