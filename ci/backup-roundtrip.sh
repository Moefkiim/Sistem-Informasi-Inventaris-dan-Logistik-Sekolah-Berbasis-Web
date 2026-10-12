#!/usr/bin/env bash
#
# Uji round-trip cadangan pada MySQL/MariaDB untuk CI.
# Isi data sudah dibuat oleh "php artisan siils:install".
#
set -euo pipefail

DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"
DB_DATABASE="${DB_DATABASE:-siils_ci}"
DB_USERNAME="${DB_USERNAME:-root}"
DB_PASSWORD="${DB_PASSWORD:-password}"

KEY_TABLES="users items asset_units submissions loans activity_logs"
EXCLUDED_TABLES="sessions cache cache_locks jobs job_batches failed_jobs"
ARCHIVE_DIR="storage/app/backups"

mysql_cli() {
    mysql -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USERNAME" -p"$DB_PASSWORD" -N -B "$@"
}

is_excluded() {
    for excluded in $EXCLUDED_TABLES; do
        [ "$1" = "$excluded" ] && return 0
    done

    return 1
}

counts() {
    mysql_cli -e "SHOW TABLES" "$DB_DATABASE" | while read -r table; do
        [ -z "$table" ] && continue
        is_excluded "$table" && continue
        count=$(mysql_cli -e "SELECT COUNT(*) FROM \`$table\`" "$DB_DATABASE")
        echo "$table=$count"
    done | sort
}

checksum() {
    local list
    list=$(echo "$KEY_TABLES" | tr ' ' ',')
    mysql_cli -e "CHECKSUM TABLE $list" "$DB_DATABASE" | awk '{ print $1"="$2 }' | sort
}

roundtrip() {
    local label="$1"
    shift

    rm -rf "$ARCHIVE_DIR"
    mkdir -p "$ARCHIVE_DIR"

    php artisan siils:backup --path="$ARCHIVE_DIR" --keep=2 "$@"

    local archive
    archive=$(ls -t "$ARCHIVE_DIR"/siils-backup-*.zip | head -1)
    echo "[$label] arsip: $archive"

    local before_counts before_checksum
    before_counts=$(counts)
    before_checksum=$(checksum)

    mysql_cli -e "DROP DATABASE \`$DB_DATABASE\`; CREATE DATABASE \`$DB_DATABASE\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

    php artisan siils:restore "$archive" --force

    local after_counts after_checksum
    after_counts=$(counts)
    after_checksum=$(checksum)

    if [ "$before_counts" != "$after_counts" ]; then
        echo "::error::$label: jumlah baris tidak sama setelah pemulihan"
        diff <(echo "$before_counts") <(echo "$after_counts") || true
        exit 1
    fi

    if [ "$before_checksum" != "$after_checksum" ]; then
        echo "::error::$label: checksum tabel kunci tidak sama setelah pemulihan"
        diff <(echo "$before_checksum") <(echo "$after_checksum") || true
        exit 1
    fi

    echo "[$label] OK - $(echo "$after_counts" | wc -l | tr -d ' ') tabel cocok"
}

roundtrip "dump-otomatis"
roundtrip "dump-php-portabel" --tanpa-mysqldump

echo "Semua uji round-trip cadangan lulus."
