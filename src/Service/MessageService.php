<?php

declare(strict_types=1);

namespace Murmur\Service;

use Murmur\Entity\Conversation;
use Murmur\Entity\Message;
use Murmur\Entity\User;
use Murmur\Repository\ConversationMapper;
use Murmur\Repository\MessageMapper;
use Murmur\Repository\SettingMapper;
use Murmur\Repository\UserMapper;

/**
 * Service for private messaging operations.
 *
 * Handles the business logic for sending messages, managing conversations,
 * and enforcing messaging permissions (mutual follows, blocks, etc.).
 */
class MessageService {

    /**
     * Maximum length for message body.
     */
    protected const MAX_BODY_LENGTH = 500;

    /**
     * The conversation mapper for database operations.
     */
    protected ConversationMapper $conversation_mapper;

    /**
     * The message mapper for database operations.
     */
    protected MessageMapper $message_mapper;

    /**
     * The user mapper for user lookups.
     */
    protected UserMapper $user_mapper;

    /**
     * The setting mapper for global settings.
     */
    protected SettingMapper $setting_mapper;

    /**
     * The user follow service for mutual follow checks.
     */
    protected UserFollowService $user_follow_service;

    /**
     * The user block service for block checks.
     */
    protected UserBlockService $user_block_service;

    /**
     * Creates a new MessageService instance.
     *
     * @param ConversationMapper $conversation_mapper The conversation mapper.
     * @param MessageMapper      $message_mapper      The message mapper.
     * @param UserMapper         $user_mapper         The user mapper.
     * @param SettingMapper      $setting_mapper      The setting mapper.
     * @param UserFollowService  $user_follow_service The user follow service.
     * @param UserBlockService   $user_block_service  The user block service.
     */
    public function __construct(
        ConversationMapper $conversation_mapper,
        MessageMapper $message_mapper,
        UserMapper $user_mapper,
        SettingMapper $setting_mapper,
        UserFollowService $user_follow_service,
        UserBlockService $user_block_service
    ) {
        $this->conversation_mapper = $conversation_mapper;
        $this->message_mapper = $message_mapper;
        $this->user_mapper = $user_mapper;
        $this->setting_mapper = $setting_mapper;
        $this->user_follow_service = $user_follow_service;
        $this->user_block_service = $user_block_service;
    }

    /**
     * Sends a message to another user.
     *
     * Creates a new conversation if one doesn't exist.
     *
     * @param int    $sender_id    The sender's user ID.
     * @param int    $recipient_id The recipient's user ID.
     * @param string $body         The message content.
     *
     * @return array{success: bool, message?: Message, conversation?: Conversation, error?: string}
     */
    public function sendMessage(int $sender_id, int $recipient_id, string $body): array {
        $result = ['success' => false];

        $validation_error = $this->validateMessage($sender_id, $recipient_id, $body);

        if ($validation_error !== null) {
            $result['error'] = $validation_error;
        } else {
            // Get or create conversation
            $conversation = $this->getOrCreateConversation($sender_id, $recipient_id);

            // Create message
            $message = new Message();
            $message->conversation_id = $conversation->conversation_id;
            $message->sender_id = $sender_id;
            $message->body = trim($body);

            $this->message_mapper->save($message);

            // Update conversation's last_message_at
            $timestamp = date('Y-m-d H:i:s');
            $this->conversation_mapper->updateLastMessageAt(
                $conversation->conversation_id,
                $timestamp
            );

            $result['success'] = true;
            $result['message'] = $message;
            $result['conversation'] = $conversation;
        }

        return $result;
    }

    /**
     * Gets or creates a conversation between two users.
     *
     * @param int $user_id_1 First user ID.
     * @param int $user_id_2 Second user ID.
     *
     * @return Conversation The existing or newly created conversation.
     */
    public function getOrCreateConversation(int $user_id_1, int $user_id_2): Conversation {
        $conversation = $this->conversation_mapper->findByUsers($user_id_1, $user_id_2);

        if ($conversation === null) {
            $conversation = $this->conversation_mapper->createBetweenUsers($user_id_1, $user_id_2);
        }

        return $conversation;
    }

    /**
     * Gets a conversation by ID if the user is a participant.
     *
     * @param int $conversation_id The conversation ID.
     * @param int $user_id         The user ID to verify participation.
     *
     * @return Conversation|null The conversation or null if not found/unauthorized.
     */
    public function getConversation(int $conversation_id, int $user_id): ?Conversation {
        $result = null;

        $conversation = $this->conversation_mapper->load($conversation_id);

        if ($conversation !== null) {
            if ($conversation->user_a_id === $user_id || $conversation->user_b_id === $user_id) {
                $result = $conversation;
            }
        }

        return $result;
    }

    /**
     * Gets the other participant in a conversation.
     *
     * @param Conversation $conversation The conversation.
     * @param int          $user_id      The current user's ID.
     *
     * @return User|null The other user or null if not found.
     */
    public function getOtherParticipant(Conversation $conversation, int $user_id): ?User {
        $other_id = ($conversation->user_a_id === $user_id)
            ? $conversation->user_b_id
            : $conversation->user_a_id;

        return $this->user_mapper->load($other_id);
    }

    /**
     * Gets all conversations for a user (inbox).
     *
     * @param int $user_id The user ID.
     * @param int $limit   Maximum conversations to return.
     * @param int $offset  Number of conversations to skip.
     *
     * @return array<array{conversation: Conversation, other_user: User, last_message: Message|null, unread_count: int}>
     */
    public function getInbox(int $user_id, int $limit = 20, int $offset = 0): array {
        $result = [];

        $conversations = $this->conversation_mapper->findByUserId($user_id, $limit, $offset);

        foreach ($conversations as $conversation) {
            $other_user = $this->getOtherParticipant($conversation, $user_id);

            if ($other_user !== null) {
                $last_message = $this->message_mapper->getLastMessage(
                    $conversation->conversation_id,
                    $user_id
                );

                $unread_count = $this->message_mapper->countUnreadInConversation(
                    $conversation->conversation_id,
                    $user_id
                );

                $result[] = [
                    'conversation'  => $conversation,
                    'other_user'    => $other_user,
                    'last_message'  => $last_message,
                    'unread_count'  => $unread_count,
                ];
            }
        }

        return $result;
    }

    /**
     * Gets messages in a conversation.
     *
     * Also marks the conversation as read for the user.
     *
     * @param int $conversation_id The conversation ID.
     * @param int $user_id         The viewing user's ID.
     * @param int $limit           Maximum messages to return.
     * @param int $offset          Number of messages to skip.
     *
     * @return array<Message> Array of Message entities.
     */
    public function getMessages(
        int $conversation_id,
        int $user_id,
        int $limit = 50,
        int $offset = 0
    ): array {
        // Mark messages as read
        $this->message_mapper->markConversationRead($conversation_id, $user_id);

        return $this->message_mapper->findByConversation(
            $conversation_id,
            $user_id,
            $limit,
            $offset
        );
    }

    /**
     * Deletes a message for the current user (sender-only or recipient-only).
     *
     * @param int $message_id The message ID.
     * @param int $user_id    The user deleting the message.
     *
     * @return array{success: bool, error?: string}
     */
    public function deleteMessage(int $message_id, int $user_id): array {
        $result = ['success' => false];

        $message = $this->message_mapper->load($message_id);

        if ($message === null) {
            $result['error'] = 'Message not found.';
        } else {
            // Verify user is part of the conversation
            $conversation = $this->conversation_mapper->load($message->conversation_id);

            if ($conversation === null) {
                $result['error'] = 'Conversation not found.';
            } elseif ($conversation->user_a_id !== $user_id && $conversation->user_b_id !== $user_id) {
                $result['error'] = 'You do not have permission to delete this message.';
            } elseif ($message->sender_id === $user_id) {
                $this->message_mapper->deleteForSender($message_id);
                $result['success'] = true;
            } else {
                $this->message_mapper->deleteForRecipient($message_id);
                $result['success'] = true;
            }
        }

        return $result;
    }

    /**
     * Deletes an entire conversation for the current user.
     *
     * @param int $conversation_id The conversation ID.
     * @param int $user_id         The user deleting the conversation.
     *
     * @return array{success: bool, error?: string}
     */
    public function deleteConversation(int $conversation_id, int $user_id): array {
        $result = ['success' => false];

        $conversation = $this->conversation_mapper->load($conversation_id);

        if ($conversation === null) {
            $result['error'] = 'Conversation not found.';
        } elseif ($conversation->user_a_id !== $user_id && $conversation->user_b_id !== $user_id) {
            $result['error'] = 'You do not have permission to delete this conversation.';
        } else {
            $this->message_mapper->deleteConversationForUser($conversation_id, $user_id);
            $result['success'] = true;
        }

        return $result;
    }

    /**
     * Gets the total unread message count for a user.
     *
     * @param int $user_id The user ID.
     *
     * @return int The number of unread messages.
     */
    public function getUnreadCount(int $user_id): int {
        return $this->message_mapper->countUnreadForUser($user_id);
    }

    /**
     * Checks if a user can message another user.
     *
     * Requirements:
     * - Messaging must be enabled globally
     * - Neither user can be disabled or pending
     * - Users must be mutual follows
     * - Neither user has blocked the other
     *
     * @param int $sender_id    The sender's user ID.
     * @param int $recipient_id The recipient's user ID.
     *
     * @return array{can_message: bool, reason?: string}
     */
    public function canMessage(int $sender_id, int $recipient_id): array {
        $result = ['can_message' => false];

        if (!$this->setting_mapper->isMessagingEnabled()) {
            $result['reason'] = 'Messaging is currently disabled.';
        } elseif ($sender_id === $recipient_id) {
            $result['reason'] = 'You cannot message yourself.';
        } else {
            $recipient = $this->user_mapper->load($recipient_id);

            if ($recipient === null) {
                $result['reason'] = 'User not found.';
            } elseif ($recipient->is_disabled) {
                $result['reason'] = 'This user account is disabled.';
            } elseif ($recipient->is_pending) {
                $result['reason'] = 'This user account is pending approval.';
            } elseif ($this->user_block_service->hasBlockBetween($sender_id, $recipient_id)) {
                $result['reason'] = 'Unable to send message.';
            } elseif (!$this->user_follow_service->areMutualFollows($sender_id, $recipient_id)) {
                $result['reason'] = 'You can only message users who follow you back.';
            } else {
                $result['can_message'] = true;
            }
        }

        return $result;
    }

    /**
     * Gets the maximum allowed message body length.
     *
     * @return int The maximum length.
     */
    public function getMaxBodyLength(): int {
        return self::MAX_BODY_LENGTH;
    }

    /**
     * Validates a message before sending.
     *
     * @param int    $sender_id    The sender's user ID.
     * @param int    $recipient_id The recipient's user ID.
     * @param string $body         The message content.
     *
     * @return string|null Error message or null if valid.
     */
    protected function validateMessage(int $sender_id, int $recipient_id, string $body): ?string {
        $error = null;

        $body = trim($body);

        $can_message = $this->canMessage($sender_id, $recipient_id);

        if (!$can_message['can_message']) {
            $error = $can_message['reason'] ?? 'Unable to send message.';
        } elseif ($body === '') {
            $error = 'Message cannot be empty.';
        } elseif (mb_strlen($body) > self::MAX_BODY_LENGTH) {
            $error = 'Message cannot exceed ' . self::MAX_BODY_LENGTH . ' characters.';
        }

        return $error;
    }

    /**
     * Gets messages in a conversation created after a given timestamp.
     *
     * Also marks fetched messages as read for the user.
     *
     * @param int    $conversation_id The conversation ID.
     * @param int    $user_id         The viewing user's ID.
     * @param string $since           Timestamp to fetch messages after (Y-m-d H:i:s format).
     *
     * @return array<Message>|null Array of messages, or null if user not authorized.
     */
    public function getMessagesSince(
        int $conversation_id,
        int $user_id,
        string $since
    ): ?array {
        $result = null;

        $conversation = $this->getConversation($conversation_id, $user_id);

        if ($conversation !== null) {
            // Mark messages as read
            $this->message_mapper->markConversationRead($conversation_id, $user_id);

            $result = $this->message_mapper->findSince(
                $conversation_id,
                $user_id,
                $since
            );
        }

        return $result;
    }
}
