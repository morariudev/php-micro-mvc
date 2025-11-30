#!/usr/bin/env sh
set -e

DB_DIR="/var/www/html/database"
DB_FILE="$DB_DIR/database.sqlite"

echo "➡ Starting container initialization..."

# -----------------------------------------
# Ensure directories exist
# -----------------------------------------
mkdir -p "$DB_DIR"
mkdir -p /var/www/html/cache
mkdir -p /var/www/html/uploads

chown -R www-data:www-data /var/www/html/database
chown -R www-data:www-data /var/www/html/cache
chown -R www-data:www-data /var/www/html/uploads

chmod -R 775 /var/www/html/database
chmod -R 775 /var/www/html/cache
chmod -R 775 /var/www/html/uploads

# -----------------------------------------
# Create DB file if missing
# -----------------------------------------
if [ ! -f "$DB_FILE" ]; then
    echo "➡ Creating SQLite database..."
    touch "$DB_FILE"
    chown www-data:www-data "$DB_FILE"
fi

# -----------------------------------------
# Create users table
# -----------------------------------------
TABLE_EXISTS=$(sqlite3 "$DB_FILE" "SELECT name FROM sqlite_master WHERE type='table' AND name='users';")

if [ -z "$TABLE_EXISTS" ]; then
    echo "➡ Creating users table..."
    sqlite3 "$DB_FILE" <<EOF
CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    email TEXT NOT NULL
);
EOF
fi

# -----------------------------------------
# Seed users table
# -----------------------------------------
HAS_DATA=$(sqlite3 "$DB_FILE" "SELECT COUNT(*) FROM users;")

if [ "$HAS_DATA" -eq "0" ]; then
    echo "➡ Seeding users table..."
    sqlite3 "$DB_FILE" <<EOF
INSERT INTO users (name, email) VALUES
('Alice', 'alice@example.com'),
('Bob', 'bob@example.com');
EOF
else
    echo "➡ Users table already has data ($HAS_DATA row(s))."
fi

echo "➡ Database ready!"

# -----------------------------------------
# Run final command (PHP-FPM)
# -----------------------------------------
exec "$@"
