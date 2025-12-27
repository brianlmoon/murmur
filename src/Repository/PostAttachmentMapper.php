<?php

declare(strict_types=1);

namespace Murmur\Repository;

use DealNews\DB\AbstractMapper;
use Murmur\Entity\PostAttachment;

/**
 * Data Mapper for the PostAttachment entity.
 *
 * Handles persistence operations for the `post_attachments` table.
 * Supports batch loading for efficient feed queries.
 */
class PostAttachmentMapper extends AbstractMapper {

    /**
     * Database configuration name.
     */
    public const DATABASE_NAME = 'murmur';

    /**
     * Database table name.
     */
    public const TABLE = 'post_attachments';

    /**
     * Primary key column name.
     */
    public const PRIMARY_KEY = 'attachment_id';

    /**
     * Value object class for this mapper.
     */
    public const MAPPED_CLASS = PostAttachment::class;

    /**
     * Column mappings from database to entity properties.
     *
     * @var array<string, array>
     */
    public const MAPPING = [
        'attachment_id' => [],
        'post_id'       => [],
        'file_path'     => [],
        'media_type'    => [],
        'sort_order'    => [],
        'created_at'    => ['read_only' => true],
    ];

    /**
     * Retrieves all attachments for a single post.
     *
     * @param int $post_id The post ID.
     *
     * @return array<PostAttachment> Array of PostAttachment entities, ordered by sort_order.
     */
    public function findByPostId(int $post_id): array {
        return $this->find(
            ['post_id' => $post_id],
            100,
            0,
            'sort_order ASC'
        ) ?? [];
    }

    /**
     * Retrieves attachments for multiple posts in a single query.
     *
     * This is optimized for feed pages where we need attachments for many posts.
     * Returns an associative array keyed by post_id for easy lookup.
     *
     * @param array<int> $post_ids Array of post IDs.
     *
     * @return array<int, array<PostAttachment>> Associative array of post_id => attachments.
     */
    public function findByPostIds(array $post_ids): array {
        $result = [];

        if (empty($post_ids)) {
            return $result;
        }

        // Initialize empty arrays for all requested post IDs
        foreach ($post_ids as $post_id) {
            $result[$post_id] = [];
        }

        $params = [];
        $placeholders = [];
        foreach ($post_ids as $i => $post_id) {
            $key = ':post_id_' . $i;
            $placeholders[] = $key;
            $params[$key] = $post_id;
        }

        $placeholder_str = implode(',', $placeholders);
        $sql = "SELECT * FROM {$this->table} WHERE post_id IN ({$placeholder_str}) ORDER BY post_id, sort_order ASC";
        $rows = $this->crud->runFetch($sql, $params);

        foreach ($rows as $row) {
            $attachment = $this->setData($row);
            $result[(int) $row['post_id']][] = $attachment;
        }

        return $result;
    }

    /**
     * Deletes all attachments for a post.
     *
     * Note: This only removes database records. File cleanup should be handled
     * separately by the calling service to delete actual files from storage.
     *
     * @param int $post_id The post ID.
     *
     * @return bool True on success.
     */
    public function deleteByPostId(int $post_id): bool {
        $sql = "DELETE FROM {$this->table} WHERE post_id = :post_id";

        return $this->crud->run($sql, [':post_id' => $post_id]);
    }

    /**
     * Gets file paths for all attachments of a post.
     *
     * Useful for file cleanup when deleting a post.
     *
     * @param int $post_id The post ID.
     *
     * @return array<string> Array of file paths.
     */
    public function getFilePathsByPostId(int $post_id): array {
        $result = [];

        $attachments = $this->findByPostId($post_id);

        foreach ($attachments as $attachment) {
            $result[] = $attachment->file_path;
        }

        return $result;
    }
}
