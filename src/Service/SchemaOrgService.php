<?php

declare(strict_types=1);

namespace Murmur\Service;

use Murmur\Entity\Post;
use Murmur\Entity\Topic;
use Murmur\Entity\User;

/**
 * Service for building Schema.org JSON-LD structured data.
 *
 * Builds plain arrays suitable for json_encode(). Takes entities and
 * already-loaded data as input, performs no database access, and knows
 * nothing about HTTP requests, templates, or rendering.
 */
class SchemaOrgService {

    /**
     * Maximum headline length before truncation.
     */
    protected const HEADLINE_MAX_LENGTH = 110;

    /**
     * Maximum number of replies inlined as Comment objects.
     */
    protected const MAX_COMMENTS = 10;

    /**
     * Builds the site-wide WebSite schema.
     *
     * @param string $site_name The site's display name.
     * @param string $site_url  The site's absolute base URL.
     *
     * @return array<string, mixed> The WebSite schema data.
     */
    public function buildWebSite(string $site_name, string $site_url): array {
        return [
            '@context' => 'https://schema.org',
            '@type'    => 'WebSite',
            'name'     => $site_name,
            'url'      => $site_url,
        ];
    }

    /**
     * Builds SocialMediaPosting schema for a single post and its replies.
     *
     * @param Post   $post       The post entity.
     * @param User   $author     The post's author.
     * @param string $site_url   The site's absolute base URL.
     * @param int    $like_count Aggregate like count (individual likers stay private).
     * @param array  $replies    Replies in PostService::getReplies() shape:
     *                           [['post' => Post, 'author' => User], ...].
     *
     * @return array<string, mixed> The SocialMediaPosting schema data.
     */
    public function buildSocialMediaPosting(
        Post $post,
        User $author,
        string $site_url,
        int $like_count,
        array $replies = []
    ): array {
        $url = $this->buildPostUrl($site_url, $post->post_id);

        $result = [
            '@context'   => 'https://schema.org',
            '@type'      => 'SocialMediaPosting',
            '@id'        => $url,
            'url'        => $url,
            'headline'   => $this->truncateHeadline($post->body),
            'articleBody' => $post->body,
            'author'     => $this->buildPerson($author, $site_url),
            'interactionStatistic' => [
                '@type'                => 'InteractionCounter',
                'interactionType'      => 'https://schema.org/LikeAction',
                'userInteractionCount' => $like_count,
            ],
        ];

        if ($post->created_at !== null) {
            $result['datePublished'] = $this->toIso8601($post->created_at);
        }

        if ($this->wasEdited($post)) {
            $result['dateModified'] = $this->toIso8601($post->updated_at);
        }

        $comments = array_slice($replies, 0, self::MAX_COMMENTS);

        if (!empty($comments)) {
            $result['comment'] = array_map(
                fn(array $reply) => $this->buildComment($reply['post'], $reply['author'], $site_url),
                $comments
            );
        }

        return $result;
    }

    /**
     * Builds a CollectionPage/ItemList schema for a topic's posts.
     *
     * @param Topic  $topic    The topic entity.
     * @param array  $posts    Posts in PostService::getFeed() shape:
     *                         [['post' => Post, 'author' => User], ...].
     * @param string $site_url The site's absolute base URL.
     *
     * @return array<string, mixed> The CollectionPage schema data.
     */
    public function buildTopicCollectionPage(Topic $topic, array $posts, string $site_url): array {
        $items = [];

        foreach ($posts as $position => $post_item) {
            $post = $post_item['post'];
            $author = $post_item['author'];

            $item = [
                '@type'    => 'SocialMediaPosting',
                'headline' => $this->truncateHeadline($post->body),
                'url'      => $this->buildPostUrl($site_url, $post->post_id),
                'author'   => $this->buildPerson($author, $site_url),
            ];

            if ($post->created_at !== null) {
                $item['datePublished'] = $this->toIso8601($post->created_at);
            }

            $items[] = [
                '@type'    => 'ListItem',
                'position' => $position + 1,
                'item'     => $item,
            ];
        }

        return [
            '@context'     => 'https://schema.org',
            '@type'        => 'CollectionPage',
            'name'         => $topic->name,
            'url'          => $this->buildTopicUrl($site_url, $topic->topic_id),
            'mainEntity'   => [
                '@type'           => 'ItemList',
                'itemListElement' => $items,
            ],
        ];
    }

    /**
     * Builds a ProfilePage schema for a user profile.
     *
     * @param User     $user            The profile's user entity.
     * @param string   $site_url        The site's absolute base URL.
     * @param int|null $follower_count  Public follower count, if available.
     *
     * @return array<string, mixed> The ProfilePage schema data.
     */
    public function buildProfilePage(User $user, string $site_url, ?int $follower_count = null): array {
        $person = $this->buildPerson($user, $site_url);

        if ($follower_count !== null) {
            $person['interactionStatistic'] = [
                '@type'                => 'InteractionCounter',
                'interactionType'      => 'https://schema.org/FollowAction',
                'userInteractionCount' => $follower_count,
            ];
        }

        return [
            '@context'  => 'https://schema.org',
            '@type'     => 'ProfilePage',
            'url'       => $this->buildProfileUrl($site_url, $user->username),
            'mainEntity' => $person,
        ];
    }

    /**
     * Builds a Comment schema for a reply.
     *
     * @param Post   $reply    The reply post entity.
     * @param User   $author   The reply's author.
     * @param string $site_url The site's absolute base URL.
     *
     * @return array<string, mixed> The Comment schema data.
     */
    protected function buildComment(Post $reply, User $author, string $site_url): array {
        $result = [
            '@type'  => 'Comment',
            'text'   => $reply->body,
            'author' => $this->buildPerson($author, $site_url),
        ];

        if ($reply->created_at !== null) {
            $result['dateCreated'] = $this->toIso8601($reply->created_at);
        }

        return $result;
    }

    /**
     * Builds a Person schema for a user.
     *
     * @param User   $user     The user entity.
     * @param string $site_url The site's absolute base URL.
     *
     * @return array<string, mixed> The Person schema data.
     */
    protected function buildPerson(User $user, string $site_url): array {
        return [
            '@type' => 'Person',
            'name'  => $user->name ?? $user->username,
            'url'   => $this->buildProfileUrl($site_url, $user->username),
        ];
    }

    /**
     * Determines whether a post was edited after creation.
     *
     * @param Post $post The post entity.
     *
     * @return bool True if the post has a dateModified distinct from its created date.
     */
    protected function wasEdited(Post $post): bool {
        return $post->updated_at !== null
            && $post->created_at !== null
            && $post->updated_at !== $post->created_at;
    }

    /**
     * Truncates a post body into a headline fallback.
     *
     * @param string $body The post's body text.
     *
     * @return string The truncated headline.
     */
    protected function truncateHeadline(string $body): string {
        $result = trim($body);

        if (mb_strlen($result) > self::HEADLINE_MAX_LENGTH) {
            $result = mb_substr($result, 0, self::HEADLINE_MAX_LENGTH - 1) . '…';
        }

        return $result;
    }

    /**
     * Converts a stored datetime string to ISO 8601.
     *
     * @param string $datetime The stored datetime string.
     *
     * @return string The ISO 8601 formatted date, or the original string if unparsable.
     */
    protected function toIso8601(string $datetime): string {
        $result = $datetime;

        $timestamp = strtotime($datetime);

        if ($timestamp !== false) {
            $result = date('c', $timestamp);
        }

        return $result;
    }

    /**
     * Builds an absolute URL for a post.
     *
     * @param string   $site_url The site's absolute base URL.
     * @param int|null $post_id  The post's ID.
     *
     * @return string The absolute post URL.
     */
    protected function buildPostUrl(string $site_url, ?int $post_id): string {
        return rtrim($site_url, '/') . '/post/' . $post_id;
    }

    /**
     * Builds an absolute URL for a topic.
     *
     * @param string   $site_url The site's absolute base URL.
     * @param int|null $topic_id The topic's ID.
     *
     * @return string The absolute topic URL.
     */
    protected function buildTopicUrl(string $site_url, ?int $topic_id): string {
        return rtrim($site_url, '/') . '/topic/' . $topic_id;
    }

    /**
     * Builds an absolute URL for a user profile.
     *
     * @param string $site_url The site's absolute base URL.
     * @param string $username The user's username.
     *
     * @return string The absolute profile URL.
     */
    protected function buildProfileUrl(string $site_url, string $username): string {
        return rtrim($site_url, '/') . '/user/' . $username;
    }
}
