#!/bin/bash
# Runs once, on the first start of an empty db volume (MariaDB entrypoint).
set -e

mysql=(mariadb -uroot -p"$MARIADB_ROOT_PASSWORD")

echo "Creating databases and user..."
"${mysql[@]}" <<SQL
CREATE DATABASE IF NOT EXISTS bovornserv_db CHARACTER SET utf8 COLLATE utf8_general_ci;
CREATE DATABASE IF NOT EXISTS bovornserv_v2_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'%' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON bovornserv_db.* TO '${DB_USER}'@'%';
GRANT ALL PRIVILEGES ON bovornserv_v2_db.* TO '${DB_USER}'@'%';
FLUSH PRIVILEGES;
SQL

echo "Importing legacy database (bovornserv_db)..."
"${mysql[@]}" bovornserv_db < /dumps/bovornserv_db.sql

# Prefer the newest V2 backup; fall back to the original dump
V2_DUMP=/dumps/bovornserv_v2_db_before_upgrade_20261013.sql
[ -f "$V2_DUMP" ] || V2_DUMP=/dumps/bovornserv_v2_db.sql
echo "Importing V2 database from $V2_DUMP..."
"${mysql[@]}" bovornserv_v2_db < "$V2_DUMP"

echo "Import complete."
