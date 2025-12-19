<?php

declare(strict_types=1);

namespace Murmur\Repository;

use DealNews\DB\AbstractMapper;
use Murmur\Entity\LinkPreview;

/**
 * Data mapper for link preview entities.
 *
 * Handles CRUD operations for cached URL metadata used to display
 * rich preview cards in posts.
 */
class LinkPreviewMapper extends AbstractMapper {

    /**
     * Database configuration name.
     */
    public const DATABASE_NAME = 'murmur';

    /**
     * Database table name.
     */
    public const TABLE = 'link_previews';

    /**
     * Primary key column name.
     */
    public const PRIMARY_KEY = 'preview_id';

    /**
     * Value object class for hydration.
     */
    public const MAPPED_CLASS = LinkPreview::class;

    /**
     * Column to property mapping.
     *
     * @var array<string, array>
     */
    public const MAPPING = [
        'preview_id'   => [],
        'url_hash'     => [],
        'url'          => [],
        'title'        => [],
        'description'  => [],
        'image_url'    => [],
        'site_name'    => [],
        'fetched_at'   => [],
        'fetch_status' => [],
        'created_at'   => ['read_only' => true],
    ];

    /**
     * Finds a preview by URL hash.
     *
     * @param string $url_hash SHA-256 hash of the URL.
     *
     * @return LinkPreview|null The preview or null if not found.
     */
    public function findByUrlHash(string $url_hash): ?LinkPreview {
        $result = null;
        $previews = $this->find(['url_hash' => $url_hash], 1);

        if (!empty($previews)) {
            $result = reset($previews);
        }

        return $result;
    }

    /**
     * Finds previews for multiple URL hashes.
     *
     * @param array<string> $url_hashes Array of SHA-256 hashes.
     *
     * @return array<string, LinkPreview> Map of hash => preview.
     */
    public function findByUrlHashes(array $url_hashes): array {
        $result = [];

        if (!empty($url_hashes)) {
            $params = [];
            $placeholders = [];

            foreach ($url_hashes as $i => $hash) {
                $key = ':hash_' . $i;
                $placeholders[] = $key;
                $params[$key] = $hash;
            }

            $sql = "SELECT * FROM " . self::TABLE .
                   " WHERE url_hash IN (" . implode(',', $placeholders) . ")";

            $rows = $this->crud->runFetch($sql, $params);

            foreach ($rows as $row) {
                $preview = $this->setData($row);
                $result[$preview->url_hash] = $preview;
            }
        }

        return $result;
    }

    /**
     * Finds previews that need fetching (pending status).
     *
     * @param int $limit Maximum previews to return.
     *
     * @return array<LinkPreview> Previews needing fetch.
     */
    public function findPending(int $limit = 10): array {
        return $this->find(['fetch_status' => 'pending'], $limit);
    }

    /**
     * Finds stale previews older than given days.
     *
     * @param int $days  Number of days before considered stale.
     * @param int $limit Maximum previews to return.
     *
     * @return array<LinkPreview> Stale previews.
     */
    public function findStale(int $days = 7, int $limit = 10): array {
        $result = [];
        $cutoff = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        $sql = "SELECT * FROM " . self::TABLE .
               " WHERE fetch_status = :status" .
               " AND fetched_at < :cutoff" .
               " ORDER BY fetched_at ASC" .
               " LIMIT " . (int)$limit;

        $rows = $this->crud->runFetch($sql, [
            ':status' => 'success',
            ':cutoff' => $cutoff,
        ]);

        foreach ($rows as $row) {
            $result[] = $this->setData($row);
        }

        return $result;
    }
}
