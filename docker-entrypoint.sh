#!/bin/bash
set -e

echo "==============================================="
echo "  PHP Container Booting"
echo "  Environment : ${APP_ENV:-unknown}"
echo "  Debug       : ${APP_DEBUG:-false}"
echo "  URL         : ${APP_URL:-http://localhost}"
echo "==============================================="

DB_FILE="/var/www/html/database/database.sqlite"

echo "➡ Checking database schema..."

if [ ! -f "$DB_FILE" ]; then
    echo "➡ Creating database file..."
    touch "$DB_FILE"
    chown www-data:www-data "$DB_FILE"
fi

TABLE_EXISTS=$(sqlite3 $DB_FILE "SELECT name FROM sqlite_master WHERE type='table' AND name='users';")

if [ -z "$TABLE_EXISTS" ]; then
    echo "➡ Creating users table..."
    sqlite3 $DB_FILE <<EOF
CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    email TEXT NOT NULL
);
EOF
fi

HAS_DATA=$(sqlite3 $DB_FILE "SELECT COUNT(*) FROM users;")

if [ "$HAS_DATA" -eq "0" ]; then
    echo "➡ Seeding users table..."
    sqlite3 $DB_FILE <<EOF
INSERT INTO users (name, email) VALUES
('Alice', 'alice@example.com'),
('Bob', 'bob@example.com');
EOF
else
    echo "➡ Users table already contains $HAS_DATA row(s)."
fi

echo "➡ Database ready!"

exec "$@"
