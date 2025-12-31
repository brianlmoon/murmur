# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Murmur is a self-hosted, open-source social platform focused on calm conversation without algorithmic feeds, engagement metrics, or federation. PHP 8.x with MySQL/MariaDB/PostgreSQL/SQLite support.

## Commands

```bash
# Install dependencies
composer install

# Run tests
vendor/bin/phpunit

# Run a single test
vendor/bin/phpunit tests/Path/To/TestFile.php
vendor/bin/phpunit --filter testMethodName

# Run local development server
php -S localhost:8000 -t public

# Setup SQLite database (for local development)
sqlite3 data/murmur.db < schema/sqlite/schema.sql
```

## Setup

1. Copy `etc/config.ini.example` to `etc/config.ini`
2. For SQLite (local dev): set `db.murmur.type = pdo` and `db.murmur.dsn = "sqlite:/path/to/murmur/data/murmur.db"`
3. Run `schema/{mysql,postgresql,sqlite}/schema.sql` to create tables
4. Ensure `public/uploads/` and `data/` are writable
5. Murmur supports subdirectory installations (e.g., `/murmur/`). The base URL is auto-detected during setup.

See [docs/installation.md](docs/installation.md) for detailed setup instructions.

## Architecture

### Data Flow
Controllers (thin) → Services (business logic) → Repositories (data mappers) → Entities (value objects)

### Key Libraries
- **dealnews/db**: Data Mapper pattern for database access. Mappers extend `AbstractMapper` with TABLE, PRIMARY_KEY, MAPPED_CLASS, and MAPPING constants.
- **moonspot/value-objects**: Entities extend `ValueObject` with typed public properties.
- **pagemill/router**: HTTP routing.
- **twig/twig**: Server-rendered templates.
- **league/flysystem**: Storage abstraction for local and cloud backends (S3, etc.).

### Naming Conventions
- Entities: singular (User, Post, Setting, Topic)
- Mappers: EntityMapper (UserMapper, PostMapper, TopicMapper)
- Tables: plural snake_case (users, posts, settings, topics)
- Primary keys: singular_id (user_id, post_id, topic_id)

### Database Schema
Schema files in `schema/{mysql,postgresql,sqlite}/schema.sql` contain complete table definitions. When modifying the schema, update the `schema.sql` file for each database type.

### Template Structure
```
templates/
├── admin/           # Admin panel templates (fixed, not themed)
└── {theme}/         # User-facing themes (e.g., "default")
    ├── components/  # Reusable partials (post.html.twig, compose.html.twig)
    ├── layouts/     # Base layouts (base.html.twig)
    └── pages/       # Full page templates
```

- Theme is configurable via admin settings (stored in `settings` table as `theme`)
- Templates use `theme` global variable: `{% extends theme ~ '/layouts/base.html.twig' %}`
- Admin templates are not themed and live directly in `templates/admin/`

### Controllers and Theming
- `BaseController` provides `render()` for admin templates and `renderThemed()` for user-facing pages
- All controllers receive `SettingMapper` in constructor for theme access
- `renderThemed('pages/feed.html.twig')` automatically prepends the current theme
- `BaseController::redirect()` automatically prepends base URL

### Global Twig Variables
Set in `public/index.php`:
- `base_url` - Base URL for subdirectory installations (e.g., "/murmur" or empty for root)
- `site_name` - Instance name
- `images_allowed` - Whether image uploads are enabled
- `theme` - Current theme name (e.g., "default")
- `logo_url` - Optional logo URL (displays image instead of site name in header)
- `max_attachments` - Maximum number of images allowed per post

### User Entity
Key fields on the User entity:
- `name` - Required full/display name (shown on posts and profile)
- `username` - Required unique handle for URLs and @mentions
- `email` - Required for login
- `bio` - Optional profile description (max 160 chars)
- `avatar_path` - Optional path to avatar image
- `is_admin` - Admin privileges
- `is_disabled` - Account disabled by admin
- `is_pending` - Account awaiting admin approval

## Admin Settings

Settings stored in `settings` table as key-value pairs:
- `site_name` - Instance name
- `registration_open` - Whether new users can register
- `images_allowed` - Whether image uploads are enabled
- `videos_allowed` - Whether video uploads are enabled
- `max_video_size_mb` - Maximum video file size in megabytes (default: 100)
- `theme` - Current theme name
- `logo_url` - Optional logo URL
- `require_approval` - Whether new accounts need admin approval
- `public_feed` - Whether non-logged-in users can view posts
- `require_topic` - Whether a topic must be selected when creating posts
- `messaging_enabled` - Whether private messaging is enabled
- `base_url` - Base URL for subdirectory installations (auto-detected)
- `max_attachments` - Maximum number of media attachments per post (default: 10)

Access via `SettingMapper` helper methods (e.g., `isRegistrationOpen()`, `isPublicFeed()`, `isTopicRequired()`, `isMessagingEnabled()`, `getMaxAttachments()`, `areVideosAllowed()`, `getMaxVideoSizeMb()`).

## Coding Standards

- PSR-1/PSR-12 with 1TBS brace style
- `snake_case` for variables and properties
- `protected` visibility by default (not private)
- Single return point per function/method
- No pass-by-reference arguments
- Typed properties and return types required
- Catch `Throwable`, not `Exception`
- Value objects over associative arrays for complex returns

## dealnews/db Usage

### String Primary Keys
The `dealnews/db` library's `save()` method doesn't handle string primary keys well (uses `lastInsertId()` which fails for non-auto-increment keys). For entities with string primary keys (like `Setting`), use direct CRUD operations:

```php
// Instead of $this->save($entity), use:
if ($existing === null) {
    $this->crud->create(self::TABLE, $data);
} else {
    $this->crud->update(self::TABLE, $data, $where);
}
```

### Custom Queries
When writing custom SQL with `IN` clauses, use **named parameters** (not positional `?`):

```php
// CORRECT - named parameters
$params = [];
$placeholders = [];
foreach ($post_ids as $i => $post_id) {
    $key = ':post_id_' . $i;
    $placeholders[] = $key;
    $params[$key] = $post_id;
}
$sql = "SELECT * FROM posts WHERE post_id IN (" . implode(',', $placeholders) . ")";
$rows = $this->crud->runFetch($sql, $params);

// WRONG - positional parameters cause PDO binding errors
$sql = "SELECT * FROM posts WHERE post_id IN (?, ?, ?)";
$rows = $this->crud->runFetch($sql, $post_ids); // Will fail!
```

### Standard Mapper Methods
- `$this->load($id)` - Load by primary key
- `$this->find($criteria, $limit, $offset, $order)` - Find with conditions
- `$this->save($entity)` - Insert or update (integer PKs only)
- `$this->delete($id)` - Delete by primary key
- `$this->crud->runFetch($sql, $params)` - Custom SELECT queries
- `$this->crud->run($sql, $params)` - Custom INSERT/UPDATE/DELETE
- `$this->crud->create($table, $data)` - Direct insert
- `$this->crud->update($table, $data, $where)` - Direct update

## Adding New Entity Fields

When adding a new field to an entity:

1. **Update Schema** - Modify `schema.sql` for each database type (mysql, postgresql, sqlite)
2. **Update Entity** - Add typed property to the entity class
3. **Update Mapper** - Add field to the `MAPPING` constant
4. **Update Service** - Add parameter to relevant methods, update validation
5. **Update Controller** - Read from POST/query, pass to service and templates
6. **Update Templates** - Add form fields and display logic
7. **Update Tests** - Add new parameter to all affected test method calls

## Adding New Settings

When adding a new admin setting:

1. **Update SettingMapper** - Add helper method (e.g., `isFeatureEnabled(): bool`)
2. **Update AdminService** - Add getter method and update `updateSettings()` signature
3. **Update AdminController** - Pass setting to template, read from POST
4. **Update admin/settings.html.twig** - Add form field (checkbox, input, etc.)
5. **Update Tests** - Update `testUpdateSettingsSuccess` with new parameter count and call

## Subdirectory Installation

All URLs in templates must use `{{ base_url }}` prefix:
- Links: `href="{{ base_url }}/login"`
- Form actions: `action="{{ base_url }}/post"`
- Images/uploads: `src="{{ base_url }}/uploads/{{ path }}"`
- Static assets: `src="{{ base_url }}/images/logo.svg"`

The router strips `base_url` from `REQUEST_URI` before matching routes.

## User Preferences

Some user preferences are stored in cookies rather than the database:
- `comment_sort` - Comment ordering preference (`oldest` or `newest`), 30-day expiry

Pattern for cookie-based preferences:
```php
// Read: query param overrides cookie, cookie overrides default
$pref = $this->getQuery('pref');
if ($pref !== null) {
    setcookie('pref_name', $pref, time() + (30 * 24 * 60 * 60), '/', '', false, true);
} else {
    $pref = $_COOKIE['pref_name'] ?? 'default_value';
}
```

## Template Component Patterns

### CSS Organization
Shared CSS for reusable components should be extracted into separate template files:
- **Location:** `templates/{theme}/components/component-styles.html.twig`
- **Include:** `{% include theme ~ '/components/component-styles.html.twig' %}`
- **Benefits:** Single source of truth, no duplicate CSS across pages

Example: `post-styles.html.twig` contains all post card styling shared between feed, post detail, and profile pages.

### Post Component Variables
The `post.html.twig` component accepts these parameters:
- `post` - Post entity (required)
- `author` - User entity (required)
- `current_user` - From base template
- `csrf_token` - From base template
- `is_reply` - Boolean, whether this is a reply/comment (default: false)
- `show_reply_link` - Boolean, show comment link (default: true)
- `like_count` - Integer count (default: 0)
- `user_liked` - Boolean (default: false)
- `reply_count` - Integer count (default: 0)
- `topic` - Topic entity for top-level posts (optional)
- `user_following` - Boolean, user follows topic (default: false)
- `image_urls` - Array of image URL strings (default: empty array)
- `avatar_url` - Author's avatar URL or null

### Post Entity Structure
Key fields for templates:
- `post_id` - Unique identifier
- `parent_id` - Null for top-level posts, set for replies/comments
- `topic_id` - Topic categorization (top-level posts only)
- `body` - Post content
- `created_at` - Timestamp string

**Note:** Media attachments are stored in the separate `post_attachments` table, not on the Post entity. Use `attachments` array from PostService or `image_urls`/`video_urls` from MediaService enrichment.

**Using `parent_id` for logic:**
- Check `{% if post.parent_id %}` to determine if post is a reply/comment
- Use for conditional rendering (anchors, URLs, aria-labels)

## Pagination Implementation

Feed pagination uses offset-based approach:
- **Per page:** 20 posts (configurable via `$per_page` in controller)
- **Detection:** Fetch `$per_page + 1` items, check if count exceeds limit
- **URL structure:** `/?page=2` or `/?page=2&filter=following`
- **Template variables:** `page`, `has_more`
- **Validation:** Page < 1 defaults to 1

Pagination controls only show when needed:
- "← Newer" link: Shows when `page > 1`
- "Older →" link: Shows when `has_more` is true
- Filter parameters preserved in URLs

## Accessibility Patterns

### Focus Management
- **Autofocus:** Only use when user explicitly triggers action (e.g., `#reply` hash)
- **Form includes:** Pass `'autofocus': false` by default, let JavaScript handle conditional focus
- **JavaScript pattern:** Check `window.location.hash` before calling `textarea.focus()`

Example from compose component:
```javascript
if (form.id && window.location.hash === '#' + form.id) {
    window.addEventListener('load', function() {
        setTimeout(function() {
            form.scrollIntoView({ behavior: 'smooth', block: 'center' });
            textarea.focus();
        }, 500);
    });
}
```

### ARIA Labels
- **Links:** Use descriptive `aria-label` that explains action
  - Posts: `"View post from {date}"`
  - Comments: `"View comment from {date}"`
- **Context matters:** Change labels based on element type/purpose

### Keyboard Navigation
- **Focus indicators:** Use `:focus` pseudo-class with visible outline
  - `outline: 2px solid var(--color-primary)`
  - `outline-offset: 2px`
  - `border-radius: 2px` (optional polish)
- **Tab order:** Ensure logical flow, don't use `autofocus` unconditionally

### Link Styling
Remove default link appearance when needed:
```css
.custom-link {
    text-decoration: none;
    color: inherit; /* or specific color */
}

.custom-link:hover {
    text-decoration: none; /* maintain no underline */
    color: var(--color-text); /* subtle color change */
}
```

## Comment Anchors

Comments have unique anchors for direct linking:
- **Article ID:** `id="comment-{post_id}"` (only when `post.parent_id` exists)
- **URL format:** `/post/{parent_id}#comment-{post_id}`
- **Date links:** Comments link to their anchor, top-level posts link to themselves
- **Visual feedback:** Use `:target` pseudo-class for highlight effect

Implementation pattern:
```twig
{# Add ID to article #}
<article class="post {{ is_reply ? 'post--reply' : '' }}"{% if post.parent_id %} id="comment-{{ post.post_id }}"{% endif %}>

{# Conditional date link #}
{% if post.parent_id %}
    <a href="{{ base_url }}/post/{{ post.parent_id }}#comment-{{ post.post_id }}">
{% else %}
    <a href="{{ base_url }}/post/{{ post.post_id }}">
{% endif %}
```

CSS for targeted comments:
```css
.post--reply:target {
    background-color: #fffbea;
    border-left-color: var(--color-primary);
    border-left-width: 4px;
    animation: highlight-fade 2s ease-out;
}
```

## Smooth Scrolling

Add to base layout for smooth anchor navigation:
```css
html {
    scroll-behavior: smooth;
    scroll-padding-top: 1rem;
}
```

- `scroll-behavior: smooth` - Animates scroll to anchors
- `scroll-padding-top` - Prevents anchored element from being flush with viewport top

## Responsive Design Patterns

### Media Queries
Use max-width breakpoints for mobile-first approach:
```css
@media (max-width: 640px) {
    /* Mobile-specific styles */
}
```

### Flexible Layouts
Use flexbox with wrapping for responsive content:
```css
.container {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}

/* Force element to new line on small screens */
@media (max-width: 640px) {
    .element {
        flex-basis: 100%;
    }
}
```

### Container Width
- **Current max-width:** 720px
- **Set in:** `templates/default/layouts/base.html.twig`
- **Responsive padding:** 1rem on each side via `.container`

## Testing Workflow

1. **Make changes** to code
2. **Run test suite:** `vendor/bin/phpunit`
3. **Verify output:** All tests should pass (currently 175 tests, 320 assertions)
4. **Manual testing:** Check actual behavior in browser on test server
5. **Accessibility testing:** Use keyboard navigation, screen readers

**Note:** Test server may not have active login session - use `curl` for HTML verification when logged-out testing is sufficient.

## Common Pitfalls

### CSS Duplication
**Problem:** Same CSS in multiple page templates  
**Solution:** Extract to `components/{name}-styles.html.twig` and include

### Autofocus Breaking Navigation
**Problem:** Form fields focus on page load, breaking keyboard navigation  
**Solution:** Only autofocus when hash matches form ID (handled via JavaScript)

### Parent Post ID Access
**Problem:** Needing parent_id but not having it available  
**Solution:** Post entity already has `parent_id` field - use `post.parent_id` in templates

### Link Cursor on Non-Links
**Problem:** Elements styled as links but with help/default cursor  
**Solution:** Remove cursor override, let link default pointer cursor show

### Alignment Issues
**Problem:** Inline elements not aligning vertically  
**Solution:** Use `display: inline-flex` with `align-items: center` or adjust padding

## Development Principles Learned

1. **Use entity data when available** - Don't pass extra parameters if entity already has the field
2. **Keep component logic internal** - Don't require parent templates to pass derived data
3. **Consolidate shared styles** - Extract to components for single source of truth
4. **Think accessibility first** - ARIA labels, focus management, keyboard navigation
5. **Test incrementally** - Run tests after each change, verify with curl/browser
6. **Preserve existing behavior** - Only change what's needed, maintain backwards compatibility
7. **Use native browser features** - HTML anchors, smooth scroll, :target pseudo-class
8. **Plan before implementing** - Write detailed plan, get approval, then execute
9. **Minimal changes** - Surgical edits, don't refactor unrelated code
10. **Document in code** - Use comments in templates to explain parameters and usage

## User Follow System

Users can follow other users. This enables feed filtering and messaging permissions.

### Entities & Mappers
- `UserFollow` entity with `follow_id`, `follower_id`, `followed_id`, `created_at`
- `UserFollowMapper` with `findByUsers()`, `getFollowerCount()`, `getFollowingCount()`, `getFollowerIds()`, `getFollowingIds()`

### UserFollowService Methods
- `follow(int $follower_id, int $followed_id): array` - Returns `['success' => bool, 'error' => string]`
- `unfollow(int $follower_id, int $followed_id): array` - Idempotent (success even if not following)
- `isFollowing(int $follower_id, int $followed_id): bool`
- `areMutualFollows(int $user_a_id, int $user_b_id): bool` - Both users follow each other
- `getFollowerCount(int $user_id): int`
- `getFollowingCount(int $user_id): int`

### Profile Integration
- Follow/unfollow buttons on user profiles
- Follower and following counts displayed
- "Message" button shown only for mutual follows

## Private Messaging System

One-to-one messaging between mutual follows with conversation threading.

### Entities
- `Conversation` - Groups messages between two users (`user_a_id < user_b_id` convention)
- `Message` - Individual messages with soft-delete flags (`deleted_by_sender`, `deleted_by_recipient`)
- `UserBlock` - Prevents messaging between users

### Key Design Decisions
- **Mutual follows required** - Users can only message people who follow them back
- **Conversation model** - Messages grouped by user pair, not individual threads
- **Sender-only deletion** - Only the sender can delete their own messages
- **Soft delete** - Messages hidden per-user, not removed from database
- **500 character limit** - Same as posts
- **Chronological display** - Oldest first, "Load more" pagination for history
- **Global admin toggle** - `messaging_enabled` setting to disable feature site-wide

### MessageService Methods
- `sendMessage(int $sender_id, int $recipient_id, string $body): array`
- `canMessage(int $sender_id, int $recipient_id): array` - Returns `['can_message' => bool, 'reason' => string]`
- `getInbox(int $user_id, int $limit, int $offset): array` - Returns conversation summaries
- `getConversation(int $conversation_id, int $user_id): ?Conversation`
- `getMessages(int $conversation_id, int $user_id, int $limit, int $offset): array`
- `getOtherParticipant(Conversation $conversation, int $user_id): User`
- `deleteMessage(int $message_id, int $user_id): array`
- `deleteConversation(int $conversation_id, int $user_id): array`
- `getUnreadCount(int $user_id): int`
- `markConversationAsRead(int $conversation_id, int $user_id): void`
- `getOrCreateConversation(int $user_a_id, int $user_b_id): Conversation`

### UserBlockService Methods
- `block(int $blocker_id, int $blocked_id): array`
- `unblock(int $blocker_id, int $blocked_id): array`
- `isBlocked(int $blocker_id, int $blocked_id): bool`
- `hasBlockBetween(int $user_a_id, int $user_b_id): bool` - Either direction

### Routes
```
GET  /messages                           - Inbox list
GET  /messages/search                    - Find users to message
GET  /messages/new/{username}            - Start/open conversation
GET  /messages/{conversation_id}         - View conversation
POST /messages/{conversation_id}/send    - Send message
POST /messages/{conversation_id}/delete  - Delete conversation
POST /messages/{id}/delete/{message_id}  - Delete single message
POST /messages/block/{username}          - Block user
POST /messages/unblock/{username}        - Unblock user
```

### Templates
- `pages/messages.html.twig` - Inbox with conversation previews and unread badges
- `pages/conversation.html.twig` - Message thread with compose form
- `pages/message_search.html.twig` - User search filtered to messageable users
- Header nav includes "Messages" link with optional unread count badge

### Conversation User ID Convention
Conversations always store `user_a_id < user_b_id` to ensure consistent lookups:

```php
$user_a_id = min($sender_id, $recipient_id);
$user_b_id = max($sender_id, $recipient_id);
```

### Checking Message Permissions
Before allowing messaging, check:
1. Messaging is enabled globally (`isMessagingEnabled()`)
2. Not messaging yourself
3. Recipient exists and is not disabled/pending
4. No block in either direction (`hasBlockBetween()`)
5. Users are mutual follows (`areMutualFollows()`)

## Adding New Features Checklist

When adding a major feature like messaging:

1. **Plan first** - Document requirements and get approval
2. **Database schema** - Create migrations for all 3 database types
3. **Entities** - Create value objects extending `ValueObject`
4. **Mappers** - Create data mappers extending `AbstractMapper`
5. **Services** - Business logic with validation and error handling
6. **Controller** - Thin layer calling services, handling requests
7. **Routes** - Add to `public/index.php` with proper regex patterns
8. **Templates** - User-facing pages following theme structure
9. **Admin settings** - Add toggles if feature should be controllable
10. **Tests** - Unit tests for services, update existing tests if signatures change
11. **Documentation** - Update AGENTS.md with new patterns and methods

## Configuration File Format

The `etc/config.ini` file uses flat key-value format with `db.murmur.*` prefix. This is required by the `dealnews/db` library which uses `GetConfig` to read settings.

### Correct Format
```ini
db.murmur.type = mysql
db.murmur.server = localhost
db.murmur.port = 3306
db.murmur.db = murmur
db.murmur.user = murmur
db.murmur.pass = your_password
db.murmur.charset = utf8mb4
```

### Incorrect Format (Will Not Work)
```ini
[db.murmur]
type = mysql
server = localhost
```

**Heads-up:** INI section syntax does not work with `GetConfig`. Always use the flat `db.murmur.key = value` format.

### SQLite Configuration
```ini
db.murmur.type = pdo
db.murmur.dsn = "sqlite:/path/to/murmur/data/murmur.db"
```

### PostgreSQL Configuration
```ini
db.murmur.type = pgsql
db.murmur.server = localhost
db.murmur.port = 5432
db.murmur.db = murmur
db.murmur.user = murmur
db.murmur.pass = your_password
```

## Documentation Structure

User-facing documentation lives in the `docs/` directory:

```
docs/
├── installation.md    # Complete setup guide for all databases
├── configuration.md   # Database and admin settings reference
└── administration.md  # Admin panel and user management guide
```

The main `README.md` provides a project overview and links to these guides. When adding new features that affect end users or administrators, update the relevant docs file.

## Schema Management

Database schemas are consolidated into single files per database type:

```
schema/
├── mysql/schema.sql       # Complete MySQL/MariaDB schema
├── postgresql/schema.sql  # Complete PostgreSQL schema
└── sqlite/schema.sql      # Complete SQLite schema
```

**Key points:**
- Each `schema.sql` contains all `CREATE TABLE` statements in dependency order
- Tables with foreign keys are created after the tables they reference
- Default settings are inserted via `INSERT` statements in the schema
- PostgreSQL includes the `update_updated_at_column()` trigger function

### Table Creation Order (for foreign key dependencies)
1. `users` (no dependencies)
2. `topics` (no dependencies)
3. `posts` (depends on users, topics)
4. `post_attachments` (depends on posts)
5. `settings` (no dependencies)
6. `likes` (depends on users, posts)
7. `topic_follows` (depends on users, topics)
8. `link_previews` (no dependencies)
9. `user_follows` (depends on users)
10. `conversations` (depends on users)
11. `messages` (depends on conversations, users)
12. `user_blocks` (depends on users)

When modifying the schema, update all three `schema.sql` files to maintain consistency across database types.

## Post Attachments System

Posts support multiple media attachments (images and videos, configurable via `max_attachments` admin setting, default: 10).

### Entities &amp; Mappers
- `PostAttachment` entity with `attachment_id`, `post_id`, `file_path`, `media_type`, `sort_order`, `created_at`
- `PostAttachmentMapper` with `findByPostId()`, `findByPostIds()` (batch), `getFilePathsByPostId()`

### Media Types
- `media_type` field: `'image'` or `'video'`
- Images: JPEG, PNG, GIF, WebP (max 5MB)
- Videos: MP4, WebM (max configurable via `max_video_size_mb`, default 100MB)

### PostService Methods
- `createPost(int $user_id, string $body, array $media_paths = [], ?int $topic_id = null)` - Creates post with attachments
- `createReply(int $user_id, int $parent_id, string $body, array $media_paths = [])` - Creates reply with attachments
- `deletePost(int $post_id, User $user)` - Returns `deleted_files` array for cleanup
- `getMaxAttachments(): int` - Gets configured limit

The `$media_paths` parameter accepts arrays of `{path, media_type}` from `MediaService::uploadMultiple()`.

### MediaService Methods
- `hasUploads(?array $files): bool` - Check for multi-file upload
- `upload(array $file, string $subdirectory): array` - Upload single file, returns `{success, path, media_type}`
- `uploadMultiple(array $files, string $subdirectory, int $max_files): array` - Atomic batch upload, returns `{success, paths: [{path, media_type}]}`
- `getMediaType(string $mime): string` - Returns 'image', 'video', or 'unknown'
- `enrichPostsWithUrls(array $posts): array` - Adds `image_urls`, `video_urls`, and `avatar_url` to post items
- `getUrl(string $path): string` - Get public URL for stored file
- `delete(string $path): bool` - Delete file from storage

### Template Variables
Posts now include:
- `attachments` - Array of `PostAttachment` entities (from PostService)
- `image_urls` - Array of image URL strings (from MediaService enrichment)
- `video_urls` - Array of video URL strings (from MediaService enrichment)

### Compose Form
- Input name: `name="media[]"` with `multiple` attribute
- Accepts images (JPEG, PNG, GIF, WebP) and videos (MP4, WebM)
- JavaScript displays preview grid with count indicator
- Progress bar shows during upload
- Validates against `max_attachments` setting (data attribute)

### Post Display Layout
Images use primary + thumbnails pattern:
- First image displays large
- Remaining images display as small thumbnails below
- All images open in new tab when clicked

Videos display inline:
- Native HTML5 video player with controls
- `preload="metadata"` shows first frame
- Click to play (no autoplay)

## Storage System

Murmur uses a storage abstraction layer supporting local filesystem and S3-compatible cloud storage. This enables seamless migration from local development to cloud-hosted production.

### Architecture

```
StorageFactory → creates → FlysystemStorage (implements StorageInterface)
                              ↓
                         UrlGenerator → generates public URLs
```

**Key Components:**
- `StorageInterface` - Contract for all storage operations (write, read, delete, exists, getUrl)
- `FlysystemStorage` - Flysystem-based implementation supporting multiple backends
- `StorageFactory` - Creates storage instances from configuration
- `UrlGenerator` - Generates public URLs for stored files

### Configuration

Storage is configured in `etc/config.ini` with the `storage.uploads.*` prefix:

```ini
# Local filesystem (default)
storage.uploads.adapter = local
storage.uploads.local_path = "/path/to/murmur/public/uploads"
storage.uploads.base_url = "/uploads"

# Amazon S3
storage.uploads.adapter = s3
storage.uploads.s3_key = AKIAIOSFODNN7EXAMPLE
storage.uploads.s3_secret = wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY
storage.uploads.s3_region = us-east-1
storage.uploads.s3_bucket = your-bucket-name
storage.uploads.base_url = "https://your-bucket-name.s3.us-east-1.amazonaws.com"

# S3-compatible services (MinIO, DigitalOcean Spaces, Cloudflare R2)
storage.uploads.s3_endpoint = "https://your-endpoint.example.com"
```

### StorageInterface Methods

- `write(string $path, string $content): bool` - Write content to storage
- `writeFromPath(string $path, string $local_path): bool` - Write from local file (for uploads)
- `read(string $path): ?string` - Read file content
- `delete(string $path): bool` - Delete file (idempotent)
- `exists(string $path): bool` - Check if file exists
- `getUrl(string $path): string` - Get public URL for file

### Usage Pattern

```php
// Storage is injected into MediaService
$storage = StorageFactory::create($config);
$media_service = new MediaService($storage);

// Upload a file
$path = $media_service->upload($_FILES['image'], 'posts');

// Get public URL
$url = $storage->getUrl($path);

// Delete a file
$storage->delete($path);
```

### URL Generation

URLs are generated based on the configured `base_url`:

| Adapter | base_url | Path | Result |
|---------|----------|------|--------|
| local | `/uploads` | `posts/abc123.jpg` | `/uploads/posts/abc123.jpg` |
| s3 | `https://bucket.s3.amazonaws.com` | `posts/abc123.jpg` | `https://bucket.s3.amazonaws.com/posts/abc123.jpg` |

### S3-Compatible Services

The storage layer supports any S3-compatible service by setting a custom endpoint:

- **MinIO** - Self-hosted S3-compatible storage
- **DigitalOcean Spaces** - `s3_endpoint = "https://nyc3.digitaloceanspaces.com"`
- **Backblaze B2** - `s3_endpoint = "https://s3.us-west-001.backblazeb2.com"`
- **Cloudflare R2** - `s3_endpoint = "https://accountid.r2.cloudflarestorage.com"`

**Heads-up:** When using a custom endpoint, path-style URLs are automatically enabled.

### Adding Storage Support to Services

When creating services that need file storage:

1. Accept `StorageInterface` in the constructor
2. Use `writeFromPath()` for uploaded files (from `$_FILES['tmp_name']`)
3. Use `getUrl()` to generate URLs for templates
4. Call `delete()` when removing content (returns success even if file missing)

## Internationalization (i18n)

Murmur supports multiple languages through the Symfony Translation component. The locale is configured site-wide in admin settings.

### Translation Files

Translation files are stored in `translations/` using YAML format:

```
translations/
└── messages.en-US.yaml    # English (default)
```

Files follow the naming pattern `messages.{locale}.yaml` where locale uses IETF tags (e.g., `en-US`, `es-MX`, `fr-FR`).

### Using Translations in Templates

**Simple strings:**
```twig
<button>{{ 'auth.login_button'|trans }}</button>
```

**With variables:**
```twig
{{ 'profile.joined'|trans({'%date%': user.created_at|localized_date('month_year')}) }}
```

**Pluralization (ICU format):**
```twig
{{ 'profile.followers'|trans({'count': follower_count}) }}
```

In YAML:
```yaml
profile:
    followers: "{count, plural, =1 {1 follower} other {# followers}}"
```

### Localized Dates

Use `|localized_date` instead of `|date` for locale-aware formatting:

```twig
{{ post.created_at|localized_date('long') }}
```

Available formats: `short`, `long`, `time`, `datetime`, `month_year`

Formats are defined in the translation file under `dates.format_*` keys.

### Translation Key Naming

Keys are organized hierarchically by feature:

| Prefix | Description |
|--------|-------------|
| `common` | Shared UI elements |
| `nav` | Navigation |
| `auth` | Login/registration |
| `feed` | Feed page |
| `post` | Post display |
| `compose` | Compose forms |
| `profile` | User profiles |
| `settings` | User settings |
| `messages` | Private messaging |
| `topics` | Topics |
| `errors` | Error pages |
| `dates` | Date format strings |
| `relative` | Relative date strings |

### TranslationService Methods

- `getTranslator(): Translator` - Returns the Symfony Translator instance
- `getLocale(): string` - Returns current locale code
- `getAvailableLocales(): array` - Returns array of available locale codes
- `getAvailableLocalesWithNames(): array` - Returns locale code => display name map

### Adding a New Language

1. Copy `translations/messages.en-US.yaml` to `translations/messages.{locale}.yaml`
2. Translate all values (keep keys unchanged)
3. New language appears automatically in admin settings

See [docs/translations.md](docs/translations.md) for complete documentation.
