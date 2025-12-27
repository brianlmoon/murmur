# Administration

This guide covers managing your Murmur instance as an administrator.

## Accessing the Admin Panel

The admin panel is available at `/admin` to any user with admin privileges. The first user created during setup is automatically an admin.

## Dashboard

The admin dashboard (`/admin`) shows:

- Total users
- Total posts
- Quick links to admin functions

## User Management

### Viewing Users

Navigate to `/admin/users` to see all registered users. Each user shows:

- Username and email
- Admin status
- Account status (active, disabled, pending)
- Registration date

### Enabling and Disabling Users

Disabled users cannot log in. To disable a user:

1. Go to `/admin/users`
2. Find the user
3. Click "Disable"

To re-enable, click "Enable" on a disabled user.

**Note:** You cannot disable your own account.

### Admin Privileges

To grant or revoke admin access:

1. Go to `/admin/users`
2. Find the user
3. Click "Make Admin" or "Remove Admin"

**Note:** You cannot remove your own admin privileges.

### Pending Users

When "Require Approval" is enabled in settings, new registrations require admin approval.

Navigate to `/admin/pending` to see pending registrations:

- **Approve** — User can log in and participate
- **Reject** — User account is deleted

## Topic Management

Topics help organize posts by category. Navigate to `/admin/topics` to manage them.

### Creating Topics

1. Go to `/admin/topics`
2. Enter a topic name (max 50 characters)
3. Click "Create Topic"

Topic names must be unique.

### Deleting Topics

1. Go to `/admin/topics`
2. Find the topic
3. Click "Delete"

When a topic is deleted:
- Posts in that topic remain but have no topic assignment
- Users who followed the topic are automatically unfollowed

## Site Settings

Navigate to `/admin/settings` to configure site-wide options.

### General Settings

| Setting | Description |
|---------|-------------|
| **Site Name** | Shown in the header and browser title bar |
| **Logo URL** | Optional. If set, displays an image instead of the site name |
| **Theme** | Visual theme for the user interface |

### Registration

| Setting | Description |
|---------|-------------|
| **Registration Open** | When unchecked, no new users can register |
| **Require Approval** | New registrations must be approved by an admin |

### Content

| Setting | Description |
|---------|-------------|
| **Images Allowed** | Users can attach images to posts |
| **Videos Allowed** | Users can attach videos to posts |
| **Max Attachments** | Maximum number of images or videos per post (default: 10) |
| **Max Video Size (MB)** | Maximum file size for uploaded videos, 1–1000 MB (default: 100) |
| **Public Feed** | Visitors who aren't logged in can view the feed |
| **Require Topic** | Posts must be assigned to a topic |

### Features

| Setting | Description |
|---------|-------------|
| **Messaging Enabled** | Users can send private messages |

## Common Tasks

### Closing Registration

To stop accepting new users:

1. Go to `/admin/settings`
2. Uncheck "Registration Open"
3. Save

### Enabling Moderated Registration

To review registrations before users can participate:

1. Go to `/admin/settings`
2. Check "Require Approval"
3. Save

New users will appear at `/admin/pending`.

### Making the Feed Public

By default, visitors must log in to see posts. To make posts visible to everyone:

1. Go to `/admin/settings`
2. Check "Public Feed"
3. Save

### Requiring Topics

To ensure all posts are categorized:

1. Create at least one topic at `/admin/topics`
2. Go to `/admin/settings`
3. Check "Require Topic"
4. Save

### Disabling Private Messages

To turn off the messaging feature site-wide:

1. Go to `/admin/settings`
2. Uncheck "Messaging Enabled"
3. Save

The Messages link will no longer appear in navigation.

### Configuring Video Uploads

Videos require additional server configuration for large file uploads.

**PHP settings** (in `php.ini` or `.htaccess`):

```ini
upload_max_filesize = 100M
post_max_size = 105M
max_execution_time = 300
```

**Nginx** (if applicable):

```nginx
client_max_body_size 100M;
```

To adjust the maximum video size in Murmur:

1. Go to `/admin/settings`
2. Set "Maximum Video Size (MB)" (1–1000)
3. Save

**Heads-up:** The Murmur setting must not exceed your server's `upload_max_filesize`.

### Disabling Video Uploads

To allow images but not videos:

1. Go to `/admin/settings`
2. Uncheck "Allow video uploads"
3. Save

Users will only see image file types in the upload picker.

## Moderation

### Handling Problem Users

1. **Disable the account** — Prevents login; user data preserved
2. **Delete posts manually** — Currently requires database access

### Viewing User Profiles

Click any username to view their public profile at `/user/{username}`. As an admin, you see the same view as other users.

## Database Maintenance

### SQLite

For SQLite installations, periodic vacuuming reclaims space:

```bash
sqlite3 data/murmur.db "VACUUM;"
```

### Backups

**SQLite:**
```bash
cp data/murmur.db data/murmur-backup-$(date +%Y%m%d).db
```

**MySQL:**
```bash
mysqldump -u murmur -p murmur > murmur-backup-$(date +%Y%m%d).sql
```

**PostgreSQL:**
```bash
pg_dump -U murmur murmur > murmur-backup-$(date +%Y%m%d).sql
```

### Media Uploads

User uploads (images and videos) are stored in `public/uploads/`. Back up this directory alongside your database.

**Heads-up:** Video files can be large. Monitor disk usage and consider cloud storage (S3) for production instances with heavy video uploads.

## Security Best Practices

1. **Use strong admin passwords** — Admin accounts have full control
2. **Review pending users** — If approval is required, check regularly
3. **Monitor disabled accounts** — Consider deleting after a period
4. **Keep PHP updated** — Security patches are important
5. **Back up regularly** — Both database and uploads

## Troubleshooting

### "Registration is closed" but I enabled it

Clear your browser cache and check the settings again. The setting should take effect immediately.

### Pending users not appearing

Ensure "Require Approval" is checked in settings. Users registered before this was enabled are already approved.

### Topics not showing for posts

Topics only appear if at least one topic exists. Create topics at `/admin/topics`.
