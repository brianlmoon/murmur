--
-- Murmur: Complete Database Schema
-- Database: SQLite
--
-- This is the consolidated schema file containing all table definitions.
-- Run this file to create a fresh database.
--

-- -----------------------------------------------------------------------------
-- Users table
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    user_id       INTEGER PRIMARY KEY AUTOINCREMENT,
    username      TEXT NOT NULL,
    name          VARCHAR(100) DEFAULT NULL,
    email         TEXT NOT NULL,
    password_hash TEXT NOT NULL,
    bio           TEXT DEFAULT NULL,
    avatar_path   TEXT DEFAULT NULL,
    is_admin      INTEGER NOT NULL DEFAULT 0,
    is_disabled   INTEGER NOT NULL DEFAULT 0,
    is_pending    INTEGER NOT NULL DEFAULT 0,
    created_at    TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at    TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE UNIQUE INDEX IF NOT EXISTS uk_users_username ON users (username);
CREATE UNIQUE INDEX IF NOT EXISTS uk_users_email ON users (email);
CREATE INDEX IF NOT EXISTS idx_users_created_at ON users (created_at);

-- Trigger to auto-update updated_at on row modification
CREATE TRIGGER IF NOT EXISTS trg_users_updated_at
    AFTER UPDATE ON users
    FOR EACH ROW
BEGIN
    UPDATE users SET updated_at = datetime('now') WHERE user_id = OLD.user_id;
END;

-- -----------------------------------------------------------------------------
-- Topics table
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS topics (
    topic_id   INTEGER PRIMARY KEY AUTOINCREMENT,
    name       VARCHAR(50) NOT NULL UNIQUE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- -----------------------------------------------------------------------------
-- Posts table
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS posts (
    post_id    INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id    INTEGER NOT NULL,
    parent_id  INTEGER DEFAULT NULL,
    topic_id   INTEGER DEFAULT NULL,
    body       TEXT NOT NULL,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at TEXT NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY (user_id) REFERENCES users (user_id) ON DELETE CASCADE,
    FOREIGN KEY (parent_id) REFERENCES posts (post_id) ON DELETE CASCADE,
    FOREIGN KEY (topic_id) REFERENCES topics (topic_id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_posts_user_id ON posts (user_id);
CREATE INDEX IF NOT EXISTS idx_posts_parent_id ON posts (parent_id);
CREATE INDEX IF NOT EXISTS idx_posts_created_at ON posts (created_at);

-- Trigger to auto-update updated_at on row modification
CREATE TRIGGER IF NOT EXISTS trg_posts_updated_at
    AFTER UPDATE ON posts
    FOR EACH ROW
BEGIN
    UPDATE posts SET updated_at = datetime('now') WHERE post_id = OLD.post_id;
END;

-- -----------------------------------------------------------------------------
-- Post attachments table
-- Stores media attachments for posts (supports multiple images/videos per post).
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS post_attachments (
    attachment_id INTEGER PRIMARY KEY AUTOINCREMENT,
    post_id       INTEGER NOT NULL REFERENCES posts(post_id) ON DELETE CASCADE,
    file_path     TEXT NOT NULL,
    media_type    TEXT NOT NULL DEFAULT 'image',
    sort_order    INTEGER NOT NULL DEFAULT 0,
    created_at    TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE INDEX IF NOT EXISTS idx_post_attachments_post_id ON post_attachments(post_id);

-- -----------------------------------------------------------------------------
-- Settings table
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS settings (
    setting_name  TEXT PRIMARY KEY,
    setting_value TEXT NOT NULL
);

-- Default settings
INSERT OR IGNORE INTO settings (setting_name, setting_value) VALUES
    ('site_name', 'Murmur'),
    ('registration_open', '1'),
    ('videos_allowed', '1'),
    ('max_video_size_mb', '100');

-- -----------------------------------------------------------------------------
-- Likes table
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS likes (
    like_id    INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id    INTEGER NOT NULL REFERENCES users(user_id) ON DELETE CASCADE,
    post_id    INTEGER NOT NULL REFERENCES posts(post_id) ON DELETE CASCADE,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (user_id, post_id)
);

CREATE INDEX IF NOT EXISTS idx_likes_post_id ON likes(post_id);

-- -----------------------------------------------------------------------------
-- Topic follows table
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS topic_follows (
    follow_id  INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id    INTEGER NOT NULL REFERENCES users(user_id) ON DELETE CASCADE,
    topic_id   INTEGER NOT NULL REFERENCES topics(topic_id) ON DELETE CASCADE,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (user_id, topic_id)
);

CREATE INDEX IF NOT EXISTS idx_topic_follows_topic_id ON topic_follows(topic_id);

-- -----------------------------------------------------------------------------
-- Link previews table
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS link_previews (
    preview_id   INTEGER PRIMARY KEY AUTOINCREMENT,
    url_hash     VARCHAR(64) NOT NULL,
    url          VARCHAR(2048) NOT NULL,
    title        VARCHAR(255),
    description  TEXT,
    image_url    VARCHAR(2048),
    site_name    VARCHAR(255),
    fetched_at   DATETIME,
    fetch_status VARCHAR(20) DEFAULT 'pending',
    created_at   DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE UNIQUE INDEX IF NOT EXISTS idx_link_previews_url_hash ON link_previews(url_hash);

-- -----------------------------------------------------------------------------
-- User follows table
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS user_follows (
    follow_id    INTEGER PRIMARY KEY AUTOINCREMENT,
    follower_id  INTEGER NOT NULL REFERENCES users(user_id) ON DELETE CASCADE,
    following_id INTEGER NOT NULL REFERENCES users(user_id) ON DELETE CASCADE,
    created_at   TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (follower_id, following_id)
);

CREATE INDEX IF NOT EXISTS idx_user_follows_following_id ON user_follows(following_id);

-- -----------------------------------------------------------------------------
-- Conversations table
-- Groups messages between two users for efficient inbox queries.
-- Convention: user_a_id < user_b_id to ensure consistent lookups.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS conversations (
    conversation_id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_a_id       INTEGER NOT NULL REFERENCES users(user_id) ON DELETE CASCADE,
    user_b_id       INTEGER NOT NULL REFERENCES users(user_id) ON DELETE CASCADE,
    last_message_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at      TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (user_a_id, user_b_id)
);

CREATE INDEX IF NOT EXISTS idx_conversations_user_a ON conversations(user_a_id);
CREATE INDEX IF NOT EXISTS idx_conversations_user_b ON conversations(user_b_id);

-- -----------------------------------------------------------------------------
-- Messages table
-- Individual messages within conversations.
-- Supports sender-only and recipient-only soft deletes.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS messages (
    message_id           INTEGER PRIMARY KEY AUTOINCREMENT,
    conversation_id      INTEGER NOT NULL REFERENCES conversations(conversation_id) ON DELETE CASCADE,
    sender_id            INTEGER NOT NULL REFERENCES users(user_id) ON DELETE CASCADE,
    body                 TEXT NOT NULL,
    is_read              INTEGER NOT NULL DEFAULT 0,
    deleted_by_sender    INTEGER NOT NULL DEFAULT 0,
    deleted_by_recipient INTEGER NOT NULL DEFAULT 0,
    created_at           TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_messages_conversation ON messages(conversation_id, created_at);

-- -----------------------------------------------------------------------------
-- User blocks table
-- Allows users to block others from messaging them.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS user_blocks (
    block_id   INTEGER PRIMARY KEY AUTOINCREMENT,
    blocker_id INTEGER NOT NULL REFERENCES users(user_id) ON DELETE CASCADE,
    blocked_id INTEGER NOT NULL REFERENCES users(user_id) ON DELETE CASCADE,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (blocker_id, blocked_id)
);

CREATE INDEX IF NOT EXISTS idx_user_blocks_blocked ON user_blocks(blocked_id);
