<?php

declare(strict_types=1);

namespace Murmur\Tests\Unit\Service;

use Murmur\Entity\Conversation;
use Murmur\Entity\Message;
use Murmur\Entity\User;
use Murmur\Repository\ConversationMapper;
use Murmur\Repository\MessageMapper;
use Murmur\Repository\SettingMapper;
use Murmur\Repository\UserMapper;
use Murmur\Service\MessageService;
use Murmur\Service\UserBlockService;
use Murmur\Service\UserFollowService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for MessageService.
 */
class MessageServiceTest extends TestCase {

    protected MessageService $message_service;

    protected MockObject $conversation_mapper;

    protected MockObject $message_mapper;

    protected MockObject $user_mapper;

    protected MockObject $setting_mapper;

    protected MockObject $user_follow_service;

    protected MockObject $user_block_service;

    protected function setUp(): void {
        $this->conversation_mapper = $this->createMock(ConversationMapper::class);
        $this->message_mapper = $this->createMock(MessageMapper::class);
        $this->user_mapper = $this->createMock(UserMapper::class);
        $this->setting_mapper = $this->createMock(SettingMapper::class);
        $this->user_follow_service = $this->createMock(UserFollowService::class);
        $this->user_block_service = $this->createMock(UserBlockService::class);

        $this->message_service = new MessageService(
            $this->conversation_mapper,
            $this->message_mapper,
            $this->user_mapper,
            $this->setting_mapper,
            $this->user_follow_service,
            $this->user_block_service
        );
    }

    /**
     * Creates a test user entity.
     */
    protected function createUser(
        int $user_id,
        string $username = 'testuser',
        bool $is_disabled = false,
        bool $is_pending = false
    ): User {
        $user = new User();
        $user->user_id = $user_id;
        $user->username = $username;
        $user->email = $username . '@example.com';
        $user->is_disabled = $is_disabled;
        $user->is_pending = $is_pending;

        return $user;
    }

    /**
     * Creates a test conversation entity.
     */
    protected function createConversation(int $conversation_id, int $user_a, int $user_b): Conversation {
        $conversation = new Conversation();
        $conversation->conversation_id = $conversation_id;
        $conversation->user_a_id = min($user_a, $user_b);
        $conversation->user_b_id = max($user_a, $user_b);

        return $conversation;
    }

    public function testSendMessageSuccess(): void {
        $recipient = $this->createUser(2, 'recipient');
        $conversation = $this->createConversation(1, 1, 2);

        $this->setting_mapper
            ->method('isMessagingEnabled')
            ->willReturn(true);

        $this->user_mapper
            ->method('load')
            ->with(2)
            ->willReturn($recipient);

        $this->user_block_service
            ->method('hasBlockBetween')
            ->with(1, 2)
            ->willReturn(false);

        $this->user_follow_service
            ->method('areMutualFollows')
            ->with(1, 2)
            ->willReturn(true);

        $this->conversation_mapper
            ->method('findByUsers')
            ->with(1, 2)
            ->willReturn($conversation);

        $this->message_mapper
            ->expects($this->once())
            ->method('save');

        $this->conversation_mapper
            ->expects($this->once())
            ->method('updateLastMessageAt');

        $result = $this->message_service->sendMessage(1, 2, 'Hello!');

        $this->assertTrue($result['success']);
        $this->assertInstanceOf(Message::class, $result['message']);
        $this->assertEquals('Hello!', $result['message']->body);
    }

    public function testSendMessageMessagingDisabled(): void {
        $this->setting_mapper
            ->method('isMessagingEnabled')
            ->willReturn(false);

        $result = $this->message_service->sendMessage(1, 2, 'Hello!');

        $this->assertFalse($result['success']);
        $this->assertEquals('Messaging is currently disabled.', $result['error']);
    }

    public function testSendMessageToYourself(): void {
        $this->setting_mapper
            ->method('isMessagingEnabled')
            ->willReturn(true);

        $result = $this->message_service->sendMessage(1, 1, 'Hello!');

        $this->assertFalse($result['success']);
        $this->assertEquals('You cannot message yourself.', $result['error']);
    }

    public function testSendMessageUserNotFound(): void {
        $this->setting_mapper
            ->method('isMessagingEnabled')
            ->willReturn(true);

        $this->user_mapper
            ->method('load')
            ->with(999)
            ->willReturn(null);

        $result = $this->message_service->sendMessage(1, 999, 'Hello!');

        $this->assertFalse($result['success']);
        $this->assertEquals('User not found.', $result['error']);
    }

    public function testSendMessageToDisabledUser(): void {
        $disabled_user = $this->createUser(2, 'disabled', true, false);

        $this->setting_mapper
            ->method('isMessagingEnabled')
            ->willReturn(true);

        $this->user_mapper
            ->method('load')
            ->with(2)
            ->willReturn($disabled_user);

        $result = $this->message_service->sendMessage(1, 2, 'Hello!');

        $this->assertFalse($result['success']);
        $this->assertEquals('This user account is disabled.', $result['error']);
    }

    public function testSendMessageNotMutualFollows(): void {
        $recipient = $this->createUser(2, 'recipient');

        $this->setting_mapper
            ->method('isMessagingEnabled')
            ->willReturn(true);

        $this->user_mapper
            ->method('load')
            ->with(2)
            ->willReturn($recipient);

        $this->user_block_service
            ->method('hasBlockBetween')
            ->with(1, 2)
            ->willReturn(false);

        $this->user_follow_service
            ->method('areMutualFollows')
            ->with(1, 2)
            ->willReturn(false);

        $result = $this->message_service->sendMessage(1, 2, 'Hello!');

        $this->assertFalse($result['success']);
        $this->assertEquals('You can only message users who follow you back.', $result['error']);
    }

    public function testSendMessageBlocked(): void {
        $recipient = $this->createUser(2, 'recipient');

        $this->setting_mapper
            ->method('isMessagingEnabled')
            ->willReturn(true);

        $this->user_mapper
            ->method('load')
            ->with(2)
            ->willReturn($recipient);

        $this->user_block_service
            ->method('hasBlockBetween')
            ->with(1, 2)
            ->willReturn(true);

        $result = $this->message_service->sendMessage(1, 2, 'Hello!');

        $this->assertFalse($result['success']);
        $this->assertEquals('Unable to send message.', $result['error']);
    }

    public function testSendMessageEmptyBody(): void {
        $recipient = $this->createUser(2, 'recipient');

        $this->setting_mapper
            ->method('isMessagingEnabled')
            ->willReturn(true);

        $this->user_mapper
            ->method('load')
            ->with(2)
            ->willReturn($recipient);

        $this->user_block_service
            ->method('hasBlockBetween')
            ->with(1, 2)
            ->willReturn(false);

        $this->user_follow_service
            ->method('areMutualFollows')
            ->with(1, 2)
            ->willReturn(true);

        $result = $this->message_service->sendMessage(1, 2, '');

        $this->assertFalse($result['success']);
        $this->assertEquals('Message cannot be empty.', $result['error']);
    }

    public function testSendMessageTooLong(): void {
        $recipient = $this->createUser(2, 'recipient');
        $long_body = str_repeat('a', 501);

        $this->setting_mapper
            ->method('isMessagingEnabled')
            ->willReturn(true);

        $this->user_mapper
            ->method('load')
            ->with(2)
            ->willReturn($recipient);

        $this->user_block_service
            ->method('hasBlockBetween')
            ->with(1, 2)
            ->willReturn(false);

        $this->user_follow_service
            ->method('areMutualFollows')
            ->with(1, 2)
            ->willReturn(true);

        $result = $this->message_service->sendMessage(1, 2, $long_body);

        $this->assertFalse($result['success']);
        $this->assertEquals('Message cannot exceed 500 characters.', $result['error']);
    }

    public function testGetConversationSuccess(): void {
        $conversation = $this->createConversation(1, 1, 2);

        $this->conversation_mapper
            ->method('load')
            ->with(1)
            ->willReturn($conversation);

        $result = $this->message_service->getConversation(1, 1);

        $this->assertSame($conversation, $result);
    }

    public function testGetConversationNotParticipant(): void {
        $conversation = $this->createConversation(1, 1, 2);

        $this->conversation_mapper
            ->method('load')
            ->with(1)
            ->willReturn($conversation);

        $result = $this->message_service->getConversation(1, 999);

        $this->assertNull($result);
    }

    public function testGetOtherParticipant(): void {
        $conversation = $this->createConversation(1, 1, 2);
        $other_user = $this->createUser(2, 'other');

        $this->user_mapper
            ->method('load')
            ->with(2)
            ->willReturn($other_user);

        $result = $this->message_service->getOtherParticipant($conversation, 1);

        $this->assertSame($other_user, $result);
    }

    public function testGetUnreadCount(): void {
        $this->message_mapper
            ->method('countUnreadForUser')
            ->with(1)
            ->willReturn(5);

        $result = $this->message_service->getUnreadCount(1);

        $this->assertEquals(5, $result);
    }

    public function testGetMaxBodyLength(): void {
        $result = $this->message_service->getMaxBodyLength();

        $this->assertEquals(500, $result);
    }

    public function testCanMessageSuccess(): void {
        $recipient = $this->createUser(2, 'recipient');

        $this->setting_mapper
            ->method('isMessagingEnabled')
            ->willReturn(true);

        $this->user_mapper
            ->method('load')
            ->with(2)
            ->willReturn($recipient);

        $this->user_block_service
            ->method('hasBlockBetween')
            ->with(1, 2)
            ->willReturn(false);

        $this->user_follow_service
            ->method('areMutualFollows')
            ->with(1, 2)
            ->willReturn(true);

        $result = $this->message_service->canMessage(1, 2);

        $this->assertTrue($result['can_message']);
        $this->assertArrayNotHasKey('reason', $result);
    }

    public function testGetMessagesSinceReturnsNewMessages(): void {
        $conversation = $this->createConversation(1, 1, 2);

        $message1 = new Message();
        $message1->message_id = 1;
        $message1->conversation_id = 1;
        $message1->sender_id = 2;
        $message1->body = 'New message';
        $message1->created_at = '2025-12-20 05:40:00';

        $this->conversation_mapper
            ->method('load')
            ->with(1)
            ->willReturn($conversation);

        $this->message_mapper
            ->expects($this->once())
            ->method('markConversationRead')
            ->with(1, 1);

        $this->message_mapper
            ->method('findSince')
            ->with(1, 1, '2025-12-20 05:30:00')
            ->willReturn([$message1]);

        $result = $this->message_service->getMessagesSince(1, 1, '2025-12-20 05:30:00');

        $this->assertNotNull($result);
        $this->assertCount(1, $result);
        $this->assertEquals('New message', $result[0]->body);
    }

    public function testGetMessagesSinceReturnsNullForUnauthorizedUser(): void {
        $conversation = $this->createConversation(1, 1, 2);

        $this->conversation_mapper
            ->method('load')
            ->with(1)
            ->willReturn($conversation);

        // User 999 is not a participant
        $result = $this->message_service->getMessagesSince(1, 999, '2025-12-20 05:30:00');

        $this->assertNull($result);
    }

    public function testGetMessagesSinceReturnsEmptyArrayWhenNoNewMessages(): void {
        $conversation = $this->createConversation(1, 1, 2);

        $this->conversation_mapper
            ->method('load')
            ->with(1)
            ->willReturn($conversation);

        $this->message_mapper
            ->method('findSince')
            ->with(1, 1, '2025-12-20 05:30:00')
            ->willReturn([]);

        $result = $this->message_service->getMessagesSince(1, 1, '2025-12-20 05:30:00');

        $this->assertNotNull($result);
        $this->assertCount(0, $result);
    }

    public function testGetMessagesSinceReturnsNullForNonExistentConversation(): void {
        $this->conversation_mapper
            ->method('load')
            ->with(999)
            ->willReturn(null);

        $result = $this->message_service->getMessagesSince(999, 1, '2025-12-20 05:30:00');

        $this->assertNull($result);
    }
}
