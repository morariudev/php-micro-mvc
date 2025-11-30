#!/bin/bash
set -e

echo "==============================================="
echo "  PHP Container Booting"
echo "  Environment : ${APP_ENV:-unknown}"
echo "  Debug       : ${APP_DEBUG:-false}"
echo "  URL         : ${APP_URL:-http://localhost}"
echo "==============================================="

DB_DIR="/var/www/html/database"
DB_FILE="$DB_DIR/database.sqlite"

# Ensure host-mounted DB directory is writable
mkdir -p "$DB_DIR"
chown -R www-data:www-data "$DB_DIR"

echo "➡ Checking database schema..."

# Create database file if missing
if [ ! -f "$DB_FILE" ]; then
    echo "➡ Creating database file..."
    touch "$DB_FILE"
    chown www-data:www-data "$DB_FILE"
fi

# -----------------------------
# Users table with timestamps & soft deletes
# -----------------------------
TABLE_EXISTS=$(sqlite3 "$DB_FILE" "SELECT name FROM sqlite_master WHERE type='table' AND name='users';")

if [ -z "$TABLE_EXISTS" ]; then
    echo "➡ Creating users table with timestamps and soft delete..."
    sqlite3 "$DB_FILE" <<EOF
CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    email TEXT NOT NULL,
    created_at TEXT DEFAULT (datetime('now')),
    updated_at TEXT DEFAULT (datetime('now')),
    deleted_at TEXT
);
EOF

    echo "➡ Creating trigger to update 'updated_at' on row update..."
    sqlite3 "$DB_FILE" <<EOF
CREATE TRIGGER users_updated_at
AFTER UPDATE ON users
FOR EACH ROW
BEGIN
    UPDATE users SET updated_at = datetime('now') WHERE id = OLD.id;
END;
EOF
fi

# Seed users if table is empty
HAS_DATA=$(sqlite3 "$DB_FILE" "SELECT COUNT(*) FROM users WHERE deleted_at IS NULL;")

if [ "$HAS_DATA" -eq "0" ]; then
    echo "➡ Seeding users table..."
    sqlite3 "$DB_FILE" <<EOF
INSERT INTO users (name, email, created_at, updated_at) VALUES
('Alice', 'alice@example.com', datetime('now'), datetime('now')),
('Bob', 'bob@example.com', datetime('now'), datetime('now'));
EOF
else
    echo "➡ Users table already contains $HAS_DATA active row(s)."
fi

# -----------------------------
# Optional: Add future table migrations here
# -----------------------------
# Example: Posts table with soft deletes & timestamps
# TABLE_EXISTS=$(sqlite3 "$DB_FILE" "SELECT name FROM sqlite_master WHERE type='table' AND name='posts';")
# if [ -z "$TABLE_EXISTS" ]; then
#     echo "➡ Creating posts table..."
#     sqlite3 "$DB_FILE" <<EOF
# CREATE TABLE posts (
#     id INTEGER PRIMARY KEY AUTOINCREMENT,
#     title TEXT NOT NULL,
#     body TEXT NOT NULL,
#     created_at TEXT DEFAULT (datetime('now')),
#     updated_at TEXT DEFAULT (datetime('now')),
#     deleted_at TEXT
# );
# EOF
#     sqlite3 "$DB_FILE" <<EOF
# CREATE TRIGGER posts_updated_at
# AFTER UPDATE ON posts
# FOR EACH ROW
# BEGIN
#     UPDATE posts SET updated_at = datetime('now') WHERE id = OLD.id;
# END;
# EOF
# fi

echo "➡ Database ready!"

# Execute container CMD
exec "$@"
