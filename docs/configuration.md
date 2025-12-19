# Configuration

Murmur is configured through two mechanisms: the database configuration file and admin settings stored in the database.

## Database Configuration

Database connection settings are stored in `etc/config.ini`. This file uses a flat key-value format.

### Configuration Keys

All database settings use the `db.murmur.*` prefix:

| Key | Description | Required |
|-----|-------------|----------|
| `db.murmur.type` | Database type: `mysql`, `pgsql`, or `pdo` | Yes |
| `db.murmur.dsn` | PDO connection string (SQLite only) | For SQLite |
| `db.murmur.server` | Database server hostname | For MySQL/PostgreSQL |
| `db.murmur.port` | Database server port | For MySQL/PostgreSQL |
| `db.murmur.db` | Database name | For MySQL/PostgreSQL |
| `db.murmur.user` | Database username | For MySQL/PostgreSQL |
| `db.murmur.pass` | Database password | For MySQL/PostgreSQL |
| `db.murmur.charset` | Character set (MySQL only) | No (defaults to utf8mb4) |

### Example: SQLite

```ini
db.murmur.type = pdo
db.murmur.dsn = "sqlite:/var/www/murmur/data/murmur.db"
```

### Example: MySQL

```ini
db.murmur.type = mysql
db.murmur.server = localhost
db.murmur.port = 3306
db.murmur.db = murmur
db.murmur.user = murmur
db.murmur.pass = your_password
db.murmur.charset = utf8mb4
```

### Example: PostgreSQL

```ini
db.murmur.type = pgsql
db.murmur.server = localhost
db.murmur.port = 5432
db.murmur.db = murmur
db.murmur.user = murmur
db.murmur.pass = your_password
```

## Admin Settings

Site-wide settings are managed through the admin panel at `/admin/settings`. These are stored in the `settings` database table.

### General Settings

| Setting | Description | Default |
|---------|-------------|---------|
| Site Name | Displayed in header and browser title | "Murmur" |
| Logo URL | Optional URL to a logo image (replaces site name in header) | Empty |
| Theme | Visual theme for the user interface | "default" |

### Registration Settings

| Setting | Description | Default |
|---------|-------------|---------|
| Registration Open | Allow new users to register | Yes |
| Require Approval | New accounts must be approved by an admin | No |

When approval is required:
- New registrations are marked as "pending"
- Pending users cannot log in
- Admins see pending users at `/admin/pending`
- Admins can approve or reject each registration

### Content Settings

| Setting | Description | Default |
|---------|-------------|---------|
| Images Allowed | Users can upload images with posts | Yes |
| Public Feed | Non-logged-in visitors can view the feed | No |
| Require Topic | Posts must be assigned to a topic | No |

### Messaging Settings

| Setting | Description | Default |
|---------|-------------|---------|
| Messaging Enabled | Enable private messaging between users | Yes |

When enabled:
- Users can message others who mutually follow them
- Unread message count appears in the navigation
- Users can block others to prevent messages

### Base URL

| Setting | Description | Default |
|---------|-------------|---------|
| Base URL | Path prefix for subdirectory installations | Auto-detected |

This is auto-detected during setup. Only change if you move Murmur to a different path.

## Directory Structure

### Required Directories

These directories must exist and be writable:

| Directory | Purpose |
|-----------|---------|
| `data/` | SQLite database file (if using SQLite) |
| `public/uploads/` | User-uploaded images |

### Optional Directories

| Directory | Purpose |
|-----------|---------|
| `local/` | Local overrides (not tracked in git) |

## Environment Considerations

### Development

```bash
# Use PHP's built-in server
php -S localhost:8000 -t public
```

Template caching is disabled by default. Error display is controlled by PHP settings.

### Production

For production deployments:

1. **Enable template caching** — Edit `public/index.php` and set the `cache` option in the Twig configuration to a writable directory
2. **Disable error display** — Set `display_errors = Off` in php.ini
3. **Use HTTPS** — Configure your web server with SSL certificates
4. **Set secure cookies** — Cookies are already marked `httponly`

### File Upload Limits

Image uploads are limited by PHP settings:

```ini
upload_max_filesize = 10M
post_max_size = 10M
```

Adjust these in `php.ini` or your web server configuration.

## Security

### Configuration File

The `etc/config.ini` file contains database credentials. Ensure it's not web-accessible:

```apache
# Apache - add to .htaccess in the etc/ directory
Deny from all
```

```nginx
# Nginx - add to server block
location /etc {
    deny all;
}
```

### Upload Directory

The `public/uploads/` directory should not execute PHP:

```apache
# Apache - add to .htaccess in public/uploads/
php_flag engine off
```

```nginx
# Nginx - add to server block
location /uploads {
    location ~ \.php$ { deny all; }
}
```

## Next Steps

- [Administration](administration.md) — Managing your Murmur instance
