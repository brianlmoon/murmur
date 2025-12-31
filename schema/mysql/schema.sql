--
-- Murmur: Complete Database Schema
-- Database: MySQL / MariaDB
--
-- This is the consolidated schema file containing all table definitions.
-- Run this file to create a fresh database.
--

-- -----------------------------------------------------------------------------
-- Users table
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
    `user_id`       BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `username`      VARCHAR(50) NOT NULL,
    `name`          VARCHAR(100) DEFAULT NULL,
    `email`         VARCHAR(255) NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `bio`           TEXT DEFAULT NULL,
    `avatar_path`   VARCHAR(255) DEFAULT NULL,
    `is_admin`      TINYINT(1) NOT NULL DEFAULT 0,
    `is_disabled`   TINYINT(1) NOT NULL DEFAULT 0,
    `is_pending`    TINYINT(1) NOT NULL DEFAULT 0,
    `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`user_id`),
    UNIQUE KEY `uk_users_username` (`username`),
    UNIQUE KEY `uk_users_email` (`email`),
    KEY `idx_users_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Topics table
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `topics` (
    `topic_id`   INT AUTO_INCREMENT PRIMARY KEY,
    `name`       VARCHAR(50) NOT NULL UNIQUE,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Posts table
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `posts` (
    `post_id`    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`    BIGINT UNSIGNED NOT NULL,
    `parent_id`  BIGINT UNSIGNED DEFAULT NULL,
    `topic_id`   INT DEFAULT NULL,
    `body`       MEDIUMTEXT NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`post_id`),
    KEY `idx_posts_user_id` (`user_id`),
    KEY `idx_posts_parent_id` (`parent_id`),
    KEY `idx_posts_created_at` (`created_at`),
    CONSTRAINT `fk_posts_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
    CONSTRAINT `fk_posts_parent` FOREIGN KEY (`parent_id`) REFERENCES `posts` (`post_id`) ON DELETE CASCADE,
    CONSTRAINT `fk_posts_topic` FOREIGN KEY (`topic_id`) REFERENCES `topics` (`topic_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Post attachments table
-- Stores media attachments for posts (supports multiple images/videos per post).
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `post_attachments` (
    `attachment_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `post_id`       BIGINT UNSIGNED NOT NULL,
    `file_path`     VARCHAR(255) NOT NULL,
    `media_type`    VARCHAR(10) NOT NULL DEFAULT 'image',
    `sort_order`    INT NOT NULL DEFAULT 0,
    `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`attachment_id`),
    KEY `idx_post_attachments_post_id` (`post_id`),
    CONSTRAINT `fk_post_attachments_post` FOREIGN KEY (`post_id`) REFERENCES `posts` (`post_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Settings table
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `settings` (
    `setting_name`  VARCHAR(100) NOT NULL,
    `setting_value` TEXT NOT NULL,
    PRIMARY KEY (`setting_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default settings
INSERT INTO `settings` (`setting_name`, `setting_value`) VALUES
    ('site_name', 'Murmur'),
    ('registration_open', '1'),
    ('videos_allowed', '1'),
    ('max_video_size_mb', '100')
ON DUPLICATE KEY UPDATE `setting_name` = `setting_name`;

-- -----------------------------------------------------------------------------
-- Sessions table
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `sessions` (
    `session_id`   VARCHAR(128) NOT NULL,
    `user_id`      BIGINT UNSIGNED DEFAULT NULL,
    `data`         MEDIUMTEXT NOT NULL,
    `last_active`  INT UNSIGNED NOT NULL,
    `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`session_id`),
    KEY `idx_sessions_last_active` (`last_active`),
    KEY `idx_sessions_user_id` (`user_id`),
    FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Likes table
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `likes` (
    `like_id`    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`    BIGINT UNSIGNED NOT NULL,
    `post_id`    BIGINT UNSIGNED NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`like_id`),
    UNIQUE KEY `idx_likes_user_post` (`user_id`, `post_id`),
    KEY `idx_likes_post_id` (`post_id`),
    CONSTRAINT `fk_likes_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
    CONSTRAINT `fk_likes_post` FOREIGN KEY (`post_id`) REFERENCES `posts` (`post_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Topic follows table
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `topic_follows` (
    `follow_id`  INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `user_id`    INT UNSIGNED NOT NULL,
    `topic_id`   INT UNSIGNED NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_user_topic` (`user_id`, `topic_id`),
    KEY `idx_topic_id` (`topic_id`),
    FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
    FOREIGN KEY (`topic_id`) REFERENCES `topics` (`topic_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Link previews table
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `link_previews` (
    `preview_id`   INT AUTO_INCREMENT PRIMARY KEY,
    `url_hash`     VARCHAR(64) NOT NULL,
    `url`          VARCHAR(2048) NOT NULL,
    `title`        VARCHAR(255),
    `description`  TEXT,
    `image_url`    VARCHAR(2048),
    `site_name`    VARCHAR(255),
    `fetched_at`   DATETIME,
    `fetch_status` VARCHAR(20) DEFAULT 'pending',
    `created_at`   DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `idx_url_hash` (`url_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------------------------------
-- User follows table
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `user_follows` (
    `follow_id`    INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `follower_id`  INT UNSIGNED NOT NULL,
    `following_id` INT UNSIGNED NOT NULL,
    `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_follower_following` (`follower_id`, `following_id`),
    KEY `idx_following_id` (`following_id`),
    FOREIGN KEY (`follower_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
    FOREIGN KEY (`following_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Conversations table
-- Groups messages between two users for efficient inbox queries.
-- Convention: user_a_id < user_b_id to ensure consistent lookups.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `conversations` (
    `conversation_id` INTEGER UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `user_a_id`       INTEGER UNSIGNED NOT NULL,
    `user_b_id`       INTEGER UNSIGNED NOT NULL,
    `last_message_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_user_pair` (`user_a_id`, `user_b_id`),
    KEY `idx_user_a` (`user_a_id`),
    KEY `idx_user_b` (`user_b_id`),
    FOREIGN KEY (`user_a_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_b_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Messages table
-- Individual messages within conversations.
-- Supports sender-only and recipient-only soft deletes.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `messages` (
    `message_id`           INTEGER UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `conversation_id`      INTEGER UNSIGNED NOT NULL,
    `sender_id`            INTEGER UNSIGNED NOT NULL,
    `body`                 TEXT NOT NULL,
    `is_read`              TINYINT(1) NOT NULL DEFAULT 0,
    `deleted_by_sender`    TINYINT(1) NOT NULL DEFAULT 0,
    `deleted_by_recipient` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at`           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_conversation` (`conversation_id`, `created_at`),
    FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`conversation_id`) ON DELETE CASCADE,
    FOREIGN KEY (`sender_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- User blocks table
-- Allows users to block others from messaging them.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `user_blocks` (
    `block_id`   INTEGER UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `blocker_id` INTEGER UNSIGNED NOT NULL,
    `blocked_id` INTEGER UNSIGNED NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_blocker_blocked` (`blocker_id`, `blocked_id`),
    KEY `idx_blocked` (`blocked_id`),
    FOREIGN KEY (`blocker_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
    FOREIGN KEY (`blocked_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
