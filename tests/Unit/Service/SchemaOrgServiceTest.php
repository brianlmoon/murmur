<?php

declare(strict_types=1);

namespace Murmur\Tests\Unit\Service;

use Murmur\Entity\Post;
use Murmur\Entity\Topic;
use Murmur\Entity\User;
use Murmur\Service\SchemaOrgService;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for SchemaOrgService.
 */
class SchemaOrgServiceTest extends TestCase {

    protected SchemaOrgService $schema_org_service;

    protected function setUp(): void {
        $this->schema_org_service = new SchemaOrgService();
    }

    protected function makeUser(int $user_id, string $username, ?string $name = null): User {
        $user = new User();
        $user->user_id = $user_id;
        $user->username = $username;
        $user->name = $name;

        return $user;
    }

    protected function makePost(
        int $post_id,
        int $user_id,
        string $body,
        ?string $created_at = '2026-01-01 12:00:00',
        ?string $updated_at = '2026-01-01 12:00:00'
    ): Post {
        $post = new Post();
        $post->post_id = $post_id;
        $post->user_id = $user_id;
        $post->body = $body;
        $post->created_at = $created_at;
        $post->updated_at = $updated_at;

        return $post;
    }

    public function testBuildWebSite(): void {
        $result = $this->schema_org_service->buildWebSite('Murmur Instance', 'https://example.com');

        $this->assertEquals('https://schema.org', $result['@context']);
        $this->assertEquals('WebSite', $result['@type']);
        $this->assertEquals('Murmur Instance', $result['name']);
        $this->assertEquals('https://example.com', $result['url']);
    }

    public function testBuildSocialMediaPostingNoReplies(): void {
        $author = $this->makeUser(1, 'alice', 'Alice');
        $post = $this->makePost(10, 1, 'Hello world');

        $result = $this->schema_org_service->buildSocialMediaPosting($post, $author, 'https://example.com', 3);

        $this->assertEquals('SocialMediaPosting', $result['@type']);
        $this->assertEquals('https://example.com/post/10', $result['@id']);
        $this->assertEquals('https://example.com/post/10', $result['url']);
        $this->assertEquals('Hello world', $result['headline']);
        $this->assertEquals('Hello world', $result['articleBody']);
        $this->assertEquals('Alice', $result['author']['name']);
        $this->assertEquals('https://example.com/user/alice', $result['author']['url']);
        $this->assertEquals(3, $result['interactionStatistic']['userInteractionCount']);
        $this->assertArrayNotHasKey('comment', $result);
    }

    public function testBuildSocialMediaPostingTruncatesHeadline(): void {
        $author = $this->makeUser(1, 'alice');
        $body = str_repeat('a', 150);
        $post = $this->makePost(10, 1, $body);

        $result = $this->schema_org_service->buildSocialMediaPosting($post, $author, 'https://example.com', 0);

        $this->assertEquals(110, mb_strlen($result['headline']));
        $this->assertEquals($body, $result['articleBody']);
    }

    public function testBuildSocialMediaPostingWithFewReplies(): void {
        $author = $this->makeUser(1, 'alice');
        $post = $this->makePost(10, 1, 'Hello world');

        $replies = [
            ['post' => $this->makePost(11, 2, 'Reply one'), 'author' => $this->makeUser(2, 'bob')],
            ['post' => $this->makePost(12, 3, 'Reply two'), 'author' => $this->makeUser(3, 'carol')],
        ];

        $result = $this->schema_org_service->buildSocialMediaPosting($post, $author, 'https://example.com', 0, $replies);

        $this->assertCount(2, $result['comment']);
        $this->assertEquals('Reply one', $result['comment'][0]['text']);
        $this->assertEquals('carol', $result['comment'][1]['author']['name']);
    }

    public function testBuildSocialMediaPostingTruncatesRepliesOverCap(): void {
        $author = $this->makeUser(1, 'alice');
        $post = $this->makePost(10, 1, 'Hello world');

        $replies = [];
        for ($i = 1; $i <= 15; $i++) {
            $replies[] = [
                'post'   => $this->makePost(100 + $i, $i, 'Reply ' . $i),
                'author' => $this->makeUser($i, 'user' . $i),
            ];
        }

        $result = $this->schema_org_service->buildSocialMediaPosting($post, $author, 'https://example.com', 0, $replies);

        $this->assertCount(10, $result['comment']);
        $this->assertEquals('Reply 1', $result['comment'][0]['text']);
        $this->assertEquals('Reply 10', $result['comment'][9]['text']);
    }

    public function testBuildSocialMediaPostingIncludesDateModifiedWhenEdited(): void {
        $author = $this->makeUser(1, 'alice');
        $post = $this->makePost(10, 1, 'Hello world', '2026-01-01 12:00:00', '2026-01-02 08:30:00');

        $result = $this->schema_org_service->buildSocialMediaPosting($post, $author, 'https://example.com', 0);

        $this->assertArrayHasKey('datePublished', $result);
        $this->assertArrayHasKey('dateModified', $result);
        $this->assertNotEquals($result['datePublished'], $result['dateModified']);
    }

    public function testBuildSocialMediaPostingOmitsDateModifiedWhenUnedited(): void {
        $author = $this->makeUser(1, 'alice');
        $post = $this->makePost(10, 1, 'Hello world', '2026-01-01 12:00:00', '2026-01-01 12:00:00');

        $result = $this->schema_org_service->buildSocialMediaPosting($post, $author, 'https://example.com', 0);

        $this->assertArrayHasKey('datePublished', $result);
        $this->assertArrayNotHasKey('dateModified', $result);
    }

    public function testBuildSocialMediaPostingDoesNotExposeIndividualLikers(): void {
        $author = $this->makeUser(1, 'alice');
        $post = $this->makePost(10, 1, 'Hello world');

        $result = $this->schema_org_service->buildSocialMediaPosting($post, $author, 'https://example.com', 5);

        $this->assertEquals('InteractionCounter', $result['interactionStatistic']['@type']);
        $this->assertEquals('https://schema.org/LikeAction', $result['interactionStatistic']['interactionType']);
        $this->assertEquals(5, $result['interactionStatistic']['userInteractionCount']);
        $this->assertArrayNotHasKey('likers', $result);
        $this->assertArrayNotHasKey('participant', $result);
    }

    public function testBuildTopicCollectionPage(): void {
        $topic = new Topic();
        $topic->topic_id = 5;
        $topic->name = 'General';

        $posts = [
            ['post' => $this->makePost(1, 1, 'First post'), 'author' => $this->makeUser(1, 'alice')],
            ['post' => $this->makePost(2, 2, 'Second post'), 'author' => $this->makeUser(2, 'bob')],
        ];

        $result = $this->schema_org_service->buildTopicCollectionPage($topic, $posts, 'https://example.com');

        $this->assertEquals('CollectionPage', $result['@type']);
        $this->assertEquals('https://example.com/topic/5', $result['url']);
        $this->assertEquals('ItemList', $result['mainEntity']['@type']);
        $this->assertCount(2, $result['mainEntity']['itemListElement']);

        $first_item = $result['mainEntity']['itemListElement'][0];
        $this->assertEquals(1, $first_item['position']);
        $this->assertEquals('SocialMediaPosting', $first_item['item']['@type']);
        $this->assertEquals('https://example.com/post/1', $first_item['item']['url']);
        $this->assertArrayNotHasKey('articleBody', $first_item['item']);
    }

    public function testBuildProfilePageWithFollowerCount(): void {
        $user = $this->makeUser(1, 'alice', 'Alice');

        $result = $this->schema_org_service->buildProfilePage($user, 'https://example.com', 42);

        $this->assertEquals('ProfilePage', $result['@type']);
        $this->assertEquals('https://example.com/user/alice', $result['url']);
        $this->assertEquals('Person', $result['mainEntity']['@type']);
        $this->assertEquals(42, $result['mainEntity']['interactionStatistic']['userInteractionCount']);
    }

    public function testBuildProfilePageWithoutFollowerCount(): void {
        $user = $this->makeUser(1, 'alice');

        $result = $this->schema_org_service->buildProfilePage($user, 'https://example.com');

        $this->assertArrayNotHasKey('interactionStatistic', $result['mainEntity']);
    }
}
