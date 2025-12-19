<?php

declare(strict_types=1);

namespace Murmur\Tests\Unit\Service;

use Murmur\Entity\Post;
use Murmur\Entity\User;
use Murmur\Repository\LikeMapper;
use Murmur\Repository\PostMapper;
use Murmur\Repository\SettingMapper;
use Murmur\Repository\TopicMapper;
use Murmur\Repository\UserMapper;
use Murmur\Service\PostService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for PostService.
 */
class PostServiceTest extends TestCase {

    protected PostService $post_service;

    protected MockObject $post_mapper;

    protected MockObject $user_mapper;

    protected MockObject $like_mapper;

    protected MockObject $topic_mapper;

    protected MockObject $setting_mapper;

    protected function setUp(): void {
        $this->post_mapper = $this->createMock(PostMapper::class);
        $this->user_mapper = $this->createMock(UserMapper::class);
        $this->like_mapper = $this->createMock(LikeMapper::class);
        $this->topic_mapper = $this->createMock(TopicMapper::class);
        $this->setting_mapper = $this->createMock(SettingMapper::class);

        $this->setting_mapper
            ->method('getMaxPostLength')
            ->willReturn(500);

        $this->post_service = new PostService(
            $this->post_mapper,
            $this->user_mapper,
            $this->like_mapper,
            $this->topic_mapper,
            $this->setting_mapper
        );
    }

    public function testCreatePostSuccess(): void {
        $this->post_mapper
            ->expects($this->once())
            ->method('save');

        $result = $this->post_service->createPost(1, 'Hello, world!');

        $this->assertTrue($result['success']);
        $this->assertInstanceOf(Post::class, $result['post']);
        $this->assertEquals(1, $result['post']->user_id);
        $this->assertEquals('Hello, world!', $result['post']->body);
    }

    public function testCreatePostWithImage(): void {
        $this->post_mapper
            ->expects($this->once())
            ->method('save');

        $result = $this->post_service->createPost(1, 'Check out this image!', 'posts/abc123.jpg');

        $this->assertTrue($result['success']);
        $this->assertEquals('posts/abc123.jpg', $result['post']->image_path);
    }

    public function testCreatePostWithTopic(): void {
        $this->post_mapper
            ->expects($this->once())
            ->method('save');

        $result = $this->post_service->createPost(1, 'Hello, world!', null, 5);

        $this->assertTrue($result['success']);
        $this->assertEquals(5, $result['post']->topic_id);
    }

    public function testCreatePostEmptyBody(): void {
        $result = $this->post_service->createPost(1, '');

        $this->assertFalse($result['success']);
        $this->assertEquals('Post cannot be empty.', $result['error']);
    }

    public function testCreatePostWhitespaceOnlyBody(): void {
        $result = $this->post_service->createPost(1, '   ');

        $this->assertFalse($result['success']);
        $this->assertEquals('Post cannot be empty.', $result['error']);
    }

    public function testCreatePostBodyTooLong(): void {
        $long_body = str_repeat('a', 501);

        $result = $this->post_service->createPost(1, $long_body);

        $this->assertFalse($result['success']);
        $this->assertEquals('Post cannot exceed 500 characters.', $result['error']);
    }

    public function testCreateReplySuccess(): void {
        $parent_post = new Post();
        $parent_post->post_id = 1;
        $parent_post->parent_id = null;

        $this->post_mapper
            ->method('load')
            ->willReturn($parent_post);

        $this->post_mapper
            ->expects($this->once())
            ->method('save');

        $result = $this->post_service->createReply(2, 1, 'This is a reply!');

        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['post']->parent_id);
        $this->assertEquals(2, $result['post']->user_id);
    }

    public function testCreateReplyToNonexistentPost(): void {
        $this->post_mapper
            ->method('load')
            ->willReturn(null);

        $result = $this->post_service->createReply(2, 999, 'This is a reply!');

        $this->assertFalse($result['success']);
        $this->assertEquals('The post you are replying to does not exist.', $result['error']);
    }

    public function testCreateReplyToReply(): void {
        $reply_post = new Post();
        $reply_post->post_id = 2;
        $reply_post->parent_id = 1; // This is already a reply

        $this->post_mapper
            ->method('load')
            ->willReturn($reply_post);

        $result = $this->post_service->createReply(3, 2, 'Reply to a reply');

        $this->assertFalse($result['success']);
        $this->assertEquals('You cannot reply to a reply.', $result['error']);
    }

    public function testGetFeed(): void {
        $post1 = new Post();
        $post1->post_id = 1;
        $post1->user_id = 1;

        $post2 = new Post();
        $post2->post_id = 2;
        $post2->user_id = 2;

        $user1 = new User();
        $user1->user_id = 1;

        $user2 = new User();
        $user2->user_id = 2;

        $this->post_mapper
            ->method('findFeed')
            ->willReturn([$post1, $post2]);

        $this->user_mapper
            ->method('load')
            ->willReturnCallback(function ($id) use ($user1, $user2) {
                return $id === 1 ? $user1 : $user2;
            });

        $result = $this->post_service->getFeed();

        $this->assertCount(2, $result);
        $this->assertSame($post1, $result[0]['post']);
        $this->assertSame($user1, $result[0]['author']);
    }

    public function testGetPost(): void {
        $post = new Post();
        $post->post_id = 1;
        $post->user_id = 1;

        $user = new User();
        $user->user_id = 1;

        $this->post_mapper
            ->method('load')
            ->willReturn($post);

        $this->user_mapper
            ->method('load')
            ->willReturn($user);

        $result = $this->post_service->getPost(1);

        $this->assertNotNull($result);
        $this->assertSame($post, $result['post']);
        $this->assertSame($user, $result['author']);
    }

    public function testGetPostNotFound(): void {
        $this->post_mapper
            ->method('load')
            ->willReturn(null);

        $result = $this->post_service->getPost(999);

        $this->assertNull($result);
    }

    public function testDeletePostByOwner(): void {
        $post = new Post();
        $post->post_id = 1;
        $post->user_id = 1;

        $user = new User();
        $user->user_id = 1;
        $user->is_admin = false;

        $this->post_mapper
            ->method('load')
            ->willReturn($post);

        $this->post_mapper
            ->method('findReplies')
            ->willReturn([]);

        $this->post_mapper
            ->expects($this->once())
            ->method('delete')
            ->with(1);

        $result = $this->post_service->deletePost(1, $user);

        $this->assertTrue($result['success']);
    }

    public function testDeletePostByAdmin(): void {
        $post = new Post();
        $post->post_id = 1;
        $post->user_id = 1;

        $admin = new User();
        $admin->user_id = 2;
        $admin->is_admin = true;

        $this->post_mapper
            ->method('load')
            ->willReturn($post);

        $this->post_mapper
            ->method('findReplies')
            ->willReturn([]);

        $this->post_mapper
            ->expects($this->once())
            ->method('delete');

        $result = $this->post_service->deletePost(1, $admin);

        $this->assertTrue($result['success']);
    }

    public function testDeletePostUnauthorized(): void {
        $post = new Post();
        $post->post_id = 1;
        $post->user_id = 1;

        $other_user = new User();
        $other_user->user_id = 2;
        $other_user->is_admin = false;

        $this->post_mapper
            ->method('load')
            ->willReturn($post);

        $result = $this->post_service->deletePost(1, $other_user);

        $this->assertFalse($result['success']);
        $this->assertEquals('You do not have permission to delete this post.', $result['error']);
    }

    public function testDeletePostNotFound(): void {
        $user = new User();
        $user->user_id = 1;

        $this->post_mapper
            ->method('load')
            ->willReturn(null);

        $result = $this->post_service->deletePost(999, $user);

        $this->assertFalse($result['success']);
        $this->assertEquals('Post not found.', $result['error']);
    }

    public function testDeletePostWithReplies(): void {
        $post = new Post();
        $post->post_id = 1;
        $post->user_id = 1;

        $reply = new Post();
        $reply->post_id = 2;
        $reply->parent_id = 1;

        $user = new User();
        $user->user_id = 1;

        $this->post_mapper
            ->method('load')
            ->willReturn($post);

        $this->post_mapper
            ->method('findReplies')
            ->willReturn([$reply]);

        // Should delete reply first, then the post
        $delete_calls = [];
        $this->post_mapper
            ->expects($this->exactly(2))
            ->method('delete')
            ->willReturnCallback(function ($id) use (&$delete_calls) {
                $delete_calls[] = $id;
                return true;
            });

        $result = $this->post_service->deletePost(1, $user);

        $this->assertEquals([2, 1], $delete_calls);

        $this->assertTrue($result['success']);
    }

    public function testGetMaxBodyLength(): void {
        $this->assertEquals(500, $this->post_service->getMaxBodyLength());
    }
}
