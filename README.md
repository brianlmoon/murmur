# Murmur

**A quiet place for meaningful conversation.**

Murmur is a self-hosted, open-source social platform designed for calm, intentional discourse. No algorithmic feeds. No engagement metrics. No federation complexity. Just you and your community.

## Why Murmur?

Modern social platforms optimize for engagement, not connection. Murmur takes a different approach:

- **No algorithms** — Posts appear in chronological order. You see what you follow.
- **No metrics** — No public like counts, no follower leaderboards, no viral incentives.
- **No federation** — Your instance, your rules. Simple to run, simple to moderate.
- **Your data** — Self-hosted means you own everything. Export anytime.

## Features

- **Posts & Replies** — Share thoughts with optional image attachments
- **Topics** — Organize conversations by category; follow topics that interest you
- **User Follows** — Build your own feed by following people you care about
- **Private Messaging** — One-to-one conversations between mutual follows
- **Theming** — Customizable themes with CSS-only styling
- **Admin Controls** — User management, registration settings, approval workflows
- **Multi-Database** — MySQL, MariaDB, PostgreSQL, or SQLite

## Quick Start

```bash
# Clone the repository
git clone https://github.com/brianlmoon/murmur.git
cd murmur

# Install dependencies
composer install

# Configure database
cp etc/config.ini.example etc/config.ini
# Edit etc/config.ini with your database settings

# Create database tables (SQLite example)
sqlite3 data/murmur.db < schema/sqlite/schema.sql

# Start the development server
php -S localhost:8000 -t public
```

Visit `http://localhost:8000` and complete the setup wizard to create your admin account.

## Documentation

| Guide | Description |
|-------|-------------|
| [Installation](docs/installation.md) | Complete setup instructions for all databases |
| [Configuration](docs/configuration.md) | Database, settings, and environment options |
| [Administration](docs/administration.md) | Managing users, topics, and site settings |

## Requirements

- PHP 8.1 or higher
- One of: MySQL 5.7+, MariaDB 10.3+, PostgreSQL 12+, or SQLite 3
- Composer

## Project Structure

```
murmur/
├── public/          # Web root (index.php, uploads, static assets)
├── src/             # PHP application code
│   ├── Controller/  # HTTP request handlers
│   ├── Entity/      # Value objects (User, Post, Topic, etc.)
│   ├── Repository/  # Data mappers for database access
│   └── Service/     # Business logic layer
├── templates/       # Twig templates
│   ├── admin/       # Admin panel (not themed)
│   └── default/     # Default user-facing theme
├── schema/          # Database schema files
│   ├── mysql/
│   ├── postgresql/
│   └── sqlite/
├── tests/           # PHPUnit test suite
└── etc/             # Configuration files
```

## Architecture

Murmur follows a clean layered architecture:

```
HTTP Request
     ↓
Controller (thin — routing, request/response)
     ↓
Service (business logic, validation)
     ↓
Repository (data mapper pattern)
     ↓
Entity (typed value objects)
     ↓
Database
```

## Contributing

Contributions are welcome! Please:

1. Fork the repository
2. Create a feature branch
3. Write tests for new functionality
4. Ensure all tests pass (`vendor/bin/phpunit`)
5. Submit a pull request

## License

Murmur is released under the [BSD 3-Clause License](LICENSE).

```
Copyright (c) 2023, Brian Moon
```

## Credits

Built with:
- [Twig](https://twig.symfony.com/) — Template engine
- [dealnews/db](https://github.com/dealnews/db) — Database abstraction
- [pagemill/router](https://github.com/PageMill/Router) — HTTP routing
