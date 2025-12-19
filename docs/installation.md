# Installation

This guide covers installing Murmur on your own server.

## Requirements

- **PHP 8.1+** with extensions: PDO, json, mbstring
- **Database**: MySQL 5.7+, MariaDB 10.3+, PostgreSQL 12+, or SQLite 3
- **Composer** for dependency management
- **Web server**: Apache, Nginx, or PHP's built-in server for development

## Step 1: Download

Clone the repository or download a release:

```bash
git clone https://github.com/brianlmoon/murmur.git
cd murmur
```

## Step 2: Install Dependencies

```bash
composer install --no-dev --optimize-autoloader
```

For development, omit `--no-dev` to include PHPUnit.

## Step 3: Configure Database

Copy the example configuration:

```bash
cp etc/config.ini.example etc/config.ini
```

Edit `etc/config.ini` with your database settings.

### SQLite (Recommended for Small Instances)

SQLite requires no separate database server—perfect for personal use or small communities.

```ini
db.murmur.type = pdo
db.murmur.dsn = "sqlite:/path/to/murmur/data/murmur.db"
```

Create the database:

```bash
sqlite3 data/murmur.db < schema/sqlite/schema.sql
```

### MySQL / MariaDB

```ini
db.murmur.type = mysql
db.murmur.server = localhost
db.murmur.port = 3306
db.murmur.db = murmur
db.murmur.user = murmur
db.murmur.pass = your_secure_password
db.murmur.charset = utf8mb4
```

Create the database and user:

```sql
CREATE DATABASE murmur CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'murmur'@'localhost' IDENTIFIED BY 'your_secure_password';
GRANT ALL PRIVILEGES ON murmur.* TO 'murmur'@'localhost';
FLUSH PRIVILEGES;
```

Import the schema:

```bash
mysql -u murmur -p murmur < schema/mysql/schema.sql
```

### PostgreSQL

```ini
db.murmur.type = pgsql
db.murmur.server = localhost
db.murmur.port = 5432
db.murmur.db = murmur
db.murmur.user = murmur
db.murmur.pass = your_secure_password
```

Create the database and user:

```sql
CREATE USER murmur WITH PASSWORD 'your_secure_password';
CREATE DATABASE murmur OWNER murmur;
```

Import the schema:

```bash
psql -U murmur -d murmur -f schema/postgresql/schema.sql
```

## Step 4: Set Permissions

The web server needs write access to:

```bash
chmod 755 data/
chmod 755 public/uploads/
```

For production, ensure these directories are owned by your web server user (e.g., `www-data`).

## Step 5: Web Server Configuration

### Development Server

For testing, use PHP's built-in server:

```bash
php -S localhost:8000 -t public
```

### Apache

Point your document root to the `public/` directory:

```apache
<VirtualHost *:80>
    ServerName murmur.example.com
    DocumentRoot /path/to/murmur/public
    
    <Directory /path/to/murmur/public>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

Create `public/.htaccess`:

```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^ index.php [L]
```

### Nginx

```nginx
server {
    listen 80;
    server_name murmur.example.com;
    root /path/to/murmur/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~* \.(css|js|png|jpg|jpeg|gif|ico|svg|woff|woff2)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
}
```

### Subdirectory Installation

Murmur supports running in a subdirectory (e.g., `https://example.com/murmur/`). The base URL is auto-detected during setup and stored in settings. No additional configuration required.

## Step 6: Complete Setup

Visit your Murmur URL in a browser. The setup wizard will:

1. Detect your database connection
2. Create your admin account
3. Configure initial settings

After setup, you'll be logged in as the administrator.

## Upgrading

To upgrade Murmur:

```bash
git pull origin main
composer install --no-dev --optimize-autoloader
```

Check the release notes for any schema changes. If schema updates are required, the schema files will be updated and you'll need to apply the changes manually or recreate the database.

## Troubleshooting

### "Class not found" errors

Run `composer dump-autoload` to regenerate the autoloader.

### Database connection errors

1. Verify credentials in `etc/config.ini`
2. Check that the database server is running
3. Ensure the database and user exist with proper permissions

### Permission denied errors

Ensure `data/` and `public/uploads/` are writable by the web server.

### Blank page or 500 errors

Check your PHP error log. Common causes:
- Missing PHP extensions
- Syntax errors in `config.ini`
- Incorrect file permissions

## Next Steps

- [Configuration](configuration.md) — Customize your instance
- [Administration](administration.md) — Manage users and settings
