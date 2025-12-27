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

## Storage Configuration

Murmur supports local filesystem storage (default) and Amazon S3-compatible cloud storage for user uploads.

### Local Filesystem (Default)

If no storage configuration is provided, Murmur uses the `public/uploads/` directory. For explicit local configuration:

```ini
storage.uploads.adapter = local
storage.uploads.local_path = "/var/www/murmur/public/uploads"
storage.uploads.base_url = "/uploads"
```

| Key | Description | Required |
|-----|-------------|----------|
| `storage.uploads.adapter` | Storage adapter type: `local` or `s3` | No (defaults to `local`) |
| `storage.uploads.local_path` | Absolute path to the uploads directory | Yes (for local) |
| `storage.uploads.base_url` | URL prefix for uploaded files | No (defaults to `/uploads`) |

### Amazon S3

For Amazon S3 or S3-compatible services (MinIO, DigitalOcean Spaces, Backblaze B2, Cloudflare R2):

```ini
storage.uploads.adapter = s3
storage.uploads.s3_key = AKIAIOSFODNN7EXAMPLE
storage.uploads.s3_secret = wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY
storage.uploads.s3_region = us-east-1
storage.uploads.s3_bucket = your-bucket-name
storage.uploads.base_url = "https://your-bucket-name.s3.us-east-1.amazonaws.com"
```

| Key | Description | Required |
|-----|-------------|----------|
| `storage.uploads.adapter` | Must be `s3` | Yes |
| `storage.uploads.s3_key` | AWS access key ID | Yes |
| `storage.uploads.s3_secret` | AWS secret access key | Yes |
| `storage.uploads.s3_region` | AWS region (e.g., `us-east-1`) | Yes |
| `storage.uploads.s3_bucket` | S3 bucket name | Yes |
| `storage.uploads.s3_endpoint` | Custom endpoint URL (for S3-compatible services) | No |
| `storage.uploads.base_url` | Public URL prefix for the bucket | Yes |

### S3-Compatible Services

For services like MinIO, DigitalOcean Spaces, or Cloudflare R2, add the custom endpoint:

```ini
storage.uploads.adapter = s3
storage.uploads.s3_key = your_access_key
storage.uploads.s3_secret = your_secret_key
storage.uploads.s3_region = us-east-1
storage.uploads.s3_bucket = your-bucket-name
storage.uploads.s3_endpoint = "https://your-endpoint.example.com"
storage.uploads.base_url = "https://your-bucket-name.your-endpoint.example.com"
```

**Heads-up:** When using a custom endpoint, path-style URLs are automatically enabled.

### S3 Bucket Configuration

Your S3 bucket must be configured for public read access to serve uploaded images:

1. **Create a bucket** with a name matching `storage.uploads.s3_bucket`
2. **Enable public access** for the bucket (or configure CloudFront/CDN)
3. **Add a bucket policy** allowing public reads:

```json
{
    "Version": "2012-10-17",
    "Statement": [
        {
            "Sid": "PublicReadGetObject",
            "Effect": "Allow",
            "Principal": "*",
            "Action": "s3:GetObject",
            "Resource": "arn:aws:s3:::your-bucket-name/*"
        }
    ]
}
```

4. **Configure CORS** if needed for direct browser uploads (optional)

## Directory Structure

### Required Directories

These directories must exist and be writable:

| Directory | Purpose |
|-----------|---------|
| `data/` | SQLite database file (if using SQLite) |
| `public/uploads/` | User-uploaded images (local storage only) |

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
