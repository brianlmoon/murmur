--
-- Murmur: Complete Database Schema
-- Database: PostgreSQL
--
-- This is the consolidated schema file containing all table definitions.
-- Run this file to create a fresh database.
--

-- -----------------------------------------------------------------------------
-- Helper function for auto-updating updated_at columns
-- -----------------------------------------------------------------------------
CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

-- -----------------------------------------------------------------------------
-- Users table
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    user_id       BIGSERIAL PRIMARY KEY,
    username      VARCHAR(50) NOT NULL,
    name          VARCHAR(100) DEFAULT NULL,
    email         VARCHAR(255) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    bio           TEXT DEFAULT NULL,
    avatar_path   VARCHAR(255) DEFAULT NULL,
    is_admin      BOOLEAN NOT NULL DEFAULT FALSE,
    is_disabled   BOOLEAN NOT NULL DEFAULT FALSE,
    is_pending    BOOLEAN NOT NULL DEFAULT FALSE,
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uk_users_username UNIQUE (username),
    CONSTRAINT uk_users_email UNIQUE (email)
);

CREATE INDEX IF NOT EXISTS idx_users_created_at ON users (created_at);

DROP TRIGGER IF EXISTS trg_users_updated_at ON users;
CREATE TRIGGER trg_users_updated_at
    BEFORE UPDATE ON users
    FOR EACH ROW
    EXECUTE FUNCTION update_updated_at_column();

-- -----------------------------------------------------------------------------
-- Topics table
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS topics (
    topic_id   SERIAL PRIMARY KEY,
    name       VARCHAR(50) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- -----------------------------------------------------------------------------
-- Posts table
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS posts (
    post_id    BIGSERIAL PRIMARY KEY,
    user_id    BIGINT NOT NULL,
    parent_id  BIGINT DEFAULT NULL,
    topic_id   INTEGER DEFAULT NULL,
    body       TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_posts_user FOREIGN KEY (user_id) REFERENCES users (user_id) ON DELETE CASCADE,
    CONSTRAINT fk_posts_parent FOREIGN KEY (parent_id) REFERENCES posts (post_id) ON DELETE CASCADE,
    CONSTRAINT fk_posts_topic FOREIGN KEY (topic_id) REFERENCES topics (topic_id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_posts_user_id ON posts (user_id);
CREATE INDEX IF NOT EXISTS idx_posts_parent_id ON posts (parent_id);
CREATE INDEX IF NOT EXISTS idx_posts_created_at ON posts (created_at);

DROP TRIGGER IF EXISTS trg_posts_updated_at ON posts;
CREATE TRIGGER trg_posts_updated_at
    BEFORE UPDATE ON posts
    FOR EACH ROW
    EXECUTE FUNCTION update_updated_at_column();

-- -----------------------------------------------------------------------------
-- Post attachments table
-- Stores media attachments for posts (supports multiple images/videos per post).
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS post_attachments (
    attachment_id BIGSERIAL PRIMARY KEY,
    post_id       BIGINT NOT NULL REFERENCES posts(post_id) ON DELETE CASCADE,
    file_path     VARCHAR(255) NOT NULL,
    media_type    VARCHAR(10) NOT NULL DEFAULT 'image',
    sort_order    INTEGER NOT NULL DEFAULT 0,
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_post_attachments_post_id ON post_attachments(post_id);

-- -----------------------------------------------------------------------------
-- Settings table
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS settings (
    setting_name  VARCHAR(100) PRIMARY KEY,
    setting_value TEXT NOT NULL
);

-- Default settings
INSERT INTO settings (setting_name, setting_value) VALUES
    ('site_name', 'Murmur'),
    ('registration_open', '1'),
    ('videos_allowed', '1'),
    ('max_video_size_mb', '100')
ON CONFLICT (setting_name) DO NOTHING;

-- -----------------------------------------------------------------------------
-- Likes table
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS likes (
    like_id    BIGSERIAL PRIMARY KEY,
    user_id    BIGINT NOT NULL REFERENCES users(user_id) ON DELETE CASCADE,
    post_id    BIGINT NOT NULL REFERENCES posts(post_id) ON DELETE CASCADE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (user_id, post_id)
);

CREATE INDEX IF NOT EXISTS idx_likes_post_id ON likes(post_id);

-- -----------------------------------------------------------------------------
-- Topic follows table
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS topic_follows (
    follow_id  SERIAL PRIMARY KEY,
    user_id    INTEGER NOT NULL REFERENCES users(user_id) ON DELETE CASCADE,
    topic_id   INTEGER NOT NULL REFERENCES topics(topic_id) ON DELETE CASCADE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (user_id, topic_id)
);

CREATE INDEX IF NOT EXISTS idx_topic_follows_topic_id ON topic_follows(topic_id);

-- -----------------------------------------------------------------------------
-- Link previews table
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS link_previews (
    preview_id   SERIAL PRIMARY KEY,
    url_hash     VARCHAR(64) NOT NULL,
    url          VARCHAR(2048) NOT NULL,
    title        VARCHAR(255),
    description  TEXT,
    image_url    VARCHAR(2048),
    site_name    VARCHAR(255),
    fetched_at   TIMESTAMP,
    fetch_status VARCHAR(20) DEFAULT 'pending',
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT idx_url_hash UNIQUE (url_hash)
);

CREATE INDEX IF NOT EXISTS idx_link_previews_url_hash ON link_previews(url_hash);

-- -----------------------------------------------------------------------------
-- User follows table
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS user_follows (
    follow_id    SERIAL PRIMARY KEY,
    follower_id  INTEGER NOT NULL REFERENCES users(user_id) ON DELETE CASCADE,
    following_id INTEGER NOT NULL REFERENCES users(user_id) ON DELETE CASCADE,
    created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (follower_id, following_id)
);

CREATE INDEX IF NOT EXISTS idx_user_follows_following_id ON user_follows(following_id);

-- -----------------------------------------------------------------------------
-- Conversations table
-- Groups messages between two users for efficient inbox queries.
-- Convention: user_a_id < user_b_id to ensure consistent lookups.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS conversations (
    conversation_id SERIAL PRIMARY KEY,
    user_a_id       INTEGER NOT NULL REFERENCES users(user_id) ON DELETE CASCADE,
    user_b_id       INTEGER NOT NULL REFERENCES users(user_id) ON DELETE CASCADE,
    last_message_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
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
    message_id           SERIAL PRIMARY KEY,
    conversation_id      INTEGER NOT NULL REFERENCES conversations(conversation_id) ON DELETE CASCADE,
    sender_id            INTEGER NOT NULL REFERENCES users(user_id) ON DELETE CASCADE,
    body                 TEXT NOT NULL,
    is_read              BOOLEAN NOT NULL DEFAULT FALSE,
    deleted_by_sender    BOOLEAN NOT NULL DEFAULT FALSE,
    deleted_by_recipient BOOLEAN NOT NULL DEFAULT FALSE,
    created_at           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_messages_conversation ON messages(conversation_id, created_at);

-- -----------------------------------------------------------------------------
-- User blocks table
-- Allows users to block others from messaging them.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS user_blocks (
    block_id   SERIAL PRIMARY KEY,
    blocker_id INTEGER NOT NULL REFERENCES users(user_id) ON DELETE CASCADE,
    blocked_id INTEGER NOT NULL REFERENCES users(user_id) ON DELETE CASCADE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (blocker_id, blocked_id)
);

CREATE INDEX IF NOT EXISTS idx_user_blocks_blocked ON user_blocks(blocked_id);
