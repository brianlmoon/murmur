<?php

declare(strict_types=1);

namespace Murmur\Controller;

use Murmur\Repository\SettingMapper;
use Murmur\Repository\UserMapper;
use Murmur\Service\MessageService;
use Murmur\Service\SessionService;
use Murmur\Service\UserBlockService;
use Twig\Environment;

/**
 * Controller for private messaging routes.
 *
 * Handles inbox, conversation views, sending messages,
 * and message/conversation deletion.
 */
class MessageController extends BaseController {

    /**
     * Message service for messaging operations.
     */
    protected MessageService $message_service;

    /**
     * User block service for block operations.
     */
    protected UserBlockService $user_block_service;

    /**
     * User mapper for user lookups.
     */
    protected UserMapper $user_mapper;

    /**
     * Creates a new MessageController instance.
     *
     * @param Environment      $twig               Twig environment for rendering.
     * @param SessionService   $session            Session service.
     * @param SettingMapper    $setting_mapper     Setting mapper.
     * @param MessageService   $message_service    Message service.
     * @param UserBlockService $user_block_service User block service.
     * @param UserMapper       $user_mapper        User mapper.
     */
    public function __construct(
        Environment $twig,
        SessionService $session,
        SettingMapper $setting_mapper,
        MessageService $message_service,
        UserBlockService $user_block_service,
        UserMapper $user_mapper
    ) {
        parent::__construct($twig, $session, $setting_mapper);
        $this->message_service = $message_service;
        $this->user_block_service = $user_block_service;
        $this->user_mapper = $user_mapper;
    }

    /**
     * Displays the messaging inbox.
     *
     * GET /messages
     *
     * @return string The rendered HTML.
     */
    public function inbox(): string {
        $this->requireAuth();

        if (!$this->setting_mapper->isMessagingEnabled()) {
            $this->session->addFlash('error', 'Messaging is currently disabled.');
            $this->redirect('/');
            return '';
        }

        $current_user = $this->session->getCurrentUser();
        $page = max(1, (int) $this->getQuery('page', 1));
        $per_page = 20;
        $offset = ($page - 1) * $per_page;

        $inbox = $this->message_service->getInbox(
            $current_user->user_id,
            $per_page + 1,
            $offset
        );

        $has_more = count($inbox) > $per_page;

        if ($has_more) {
            array_pop($inbox);
        }

        return $this->renderThemed('pages/messages.html.twig', [
            'inbox'    => $inbox,
            'page'     => $page,
            'has_more' => $has_more,
        ]);
    }

    /**
     * Displays a conversation thread.
     *
     * GET /messages/{conversation_id}
     *
     * @param int $conversation_id The conversation ID.
     *
     * @return string The rendered HTML.
     */
    public function showConversation(int $conversation_id): string {
        $result = '';

        $this->requireAuth();

        if (!$this->setting_mapper->isMessagingEnabled()) {
            $this->session->addFlash('error', 'Messaging is currently disabled.');
            $this->redirect('/');
            return '';
        }

        $current_user = $this->session->getCurrentUser();
        $conversation = $this->message_service->getConversation(
            $conversation_id,
            $current_user->user_id
        );

        if ($conversation === null) {
            http_response_code(404);
            $result = $this->renderThemed('pages/404.html.twig', [
                'message' => 'Conversation not found.',
            ]);
        } else {
            $other_user = $this->message_service->getOtherParticipant(
                $conversation,
                $current_user->user_id
            );

            $page = max(1, (int) $this->getQuery('page', 1));
            $per_page = 50;
            $offset = ($page - 1) * $per_page;

            $messages = $this->message_service->getMessages(
                $conversation_id,
                $current_user->user_id,
                $per_page + 1,
                $offset
            );

            $has_more = count($messages) > $per_page;

            if ($has_more) {
                array_pop($messages);
            }

            // Check if user can still message this person
            $can_reply = $this->message_service->canMessage(
                $current_user->user_id,
                $other_user->user_id
            );

            $result = $this->renderThemed('pages/conversation.html.twig', [
                'conversation'    => $conversation,
                'other_user'      => $other_user,
                'messages'        => $messages,
                'page'            => $page,
                'has_more'        => $has_more,
                'can_reply'       => $can_reply['can_message'],
                'cannot_reply_reason' => $can_reply['reason'] ?? null,
                'max_body_length' => $this->message_service->getMaxBodyLength(),
            ]);
        }

        return $result;
    }

    /**
     * Polls for new messages in a conversation.
     *
     * Returns JSON with new messages since the given timestamp.
     *
     * GET /messages/{conversation_id}/poll?since={timestamp}
     *
     * @param int $conversation_id The conversation ID.
     *
     * @return string JSON response.
     */
    public function pollConversation(int $conversation_id): string {
        header('Content-Type: application/json');

        $this->session->start();
        $current_user = $this->session->getCurrentUser();

        if ($current_user === null) {
            http_response_code(401);
            return json_encode([
                'success' => false,
                'error'   => 'Not authenticated',
            ]);
        }

        if (!$this->setting_mapper->isMessagingEnabled()) {
            http_response_code(403);
            return json_encode([
                'success' => false,
                'error'   => 'Messaging is currently disabled',
            ]);
        }

        $since = $this->getQuery('since', '');

        if ($since === '') {
            http_response_code(400);
            return json_encode([
                'success' => false,
                'error'   => 'Missing since parameter',
            ]);
        }

        $conversation = $this->message_service->getConversation(
            $conversation_id,
            $current_user->user_id
        );

        if ($conversation === null) {
            http_response_code(404);
            return json_encode([
                'success' => false,
                'error'   => 'Conversation not found',
            ]);
        }

        $other_user = $this->message_service->getOtherParticipant(
            $conversation,
            $current_user->user_id
        );

        $messages = $this->message_service->getMessagesSince(
            $conversation_id,
            $current_user->user_id,
            $since
        );

        $can_reply = $this->message_service->canMessage(
            $current_user->user_id,
            $other_user->user_id
        );

        // Format messages for JSON response
        $formatted_messages = [];
        $last_timestamp = $since;

        foreach ($messages as $message) {
            $formatted_messages[] = [
                'message_id' => $message->message_id,
                'sender_id'  => $message->sender_id,
                'body'       => $message->body,
                'created_at' => $message->created_at,
                'is_mine'    => $message->sender_id === $current_user->user_id,
            ];
            $last_timestamp = $message->created_at;
        }

        return json_encode([
            'success'              => true,
            'messages'             => $formatted_messages,
            'can_reply'            => $can_reply['can_message'],
            'cannot_reply_reason'  => $can_reply['reason'] ?? null,
            'last_timestamp'       => $last_timestamp,
        ]);
    }

    /**
     * Starts a new conversation or opens existing one with a user.
     *
     * GET /messages/new/{username}
     *
     * @param string $username The username to message.
     *
     * @return string The rendered HTML.
     */
    public function newConversation(string $username): string {
        $result = '';

        $this->requireAuth();

        if (!$this->setting_mapper->isMessagingEnabled()) {
            $this->session->addFlash('error', 'Messaging is currently disabled.');
            $this->redirect('/');
            return '';
        }

        $current_user = $this->session->getCurrentUser();
        $other_user = $this->user_mapper->findByUsername($username);

        if ($other_user === null) {
            http_response_code(404);
            $result = $this->renderThemed('pages/404.html.twig', [
                'message' => 'User not found.',
            ]);
        } else {
            // Check if conversation already exists
            $conversation = $this->message_service->getOrCreateConversation(
                $current_user->user_id,
                $other_user->user_id
            );

            // Redirect to existing conversation
            $this->redirect('/messages/' . $conversation->conversation_id);
        }

        return $result;
    }

    /**
     * Sends a message in a conversation.
     *
     * POST /messages/{conversation_id}/send
     *
     * @param int $conversation_id The conversation ID.
     *
     * @return void
     */
    public function sendMessage(int $conversation_id): void {
        $this->requireAuth();

        if (!$this->validateCsrf()) {
            $this->session->addFlash('error', 'Invalid form submission. Please try again.');
            $this->redirect('/messages/' . $conversation_id);
            return;
        }

        if (!$this->setting_mapper->isMessagingEnabled()) {
            $this->session->addFlash('error', 'Messaging is currently disabled.');
            $this->redirect('/');
            return;
        }

        $current_user = $this->session->getCurrentUser();
        $conversation = $this->message_service->getConversation(
            $conversation_id,
            $current_user->user_id
        );

        if ($conversation === null) {
            $this->session->addFlash('error', 'Conversation not found.');
            $this->redirect('/messages');
            return;
        }

        $other_user = $this->message_service->getOtherParticipant(
            $conversation,
            $current_user->user_id
        );

        $body = trim((string) $this->getPost('body', ''));

        $send_result = $this->message_service->sendMessage(
            $current_user->user_id,
            $other_user->user_id,
            $body
        );

        if (!$send_result['success']) {
            $this->session->addFlash('error', $send_result['error']);
        }

        $this->redirect('/messages/' . $conversation_id);
    }

    /**
     * Deletes a single message.
     *
     * POST /messages/{conversation_id}/delete/{message_id}
     *
     * @param int $conversation_id The conversation ID.
     * @param int $message_id      The message ID.
     *
     * @return void
     */
    public function deleteMessage(int $conversation_id, int $message_id): void {
        $this->requireAuth();

        if (!$this->validateCsrf()) {
            $this->session->addFlash('error', 'Invalid form submission. Please try again.');
            $this->redirect('/messages/' . $conversation_id);
            return;
        }

        $current_user = $this->session->getCurrentUser();

        $delete_result = $this->message_service->deleteMessage(
            $message_id,
            $current_user->user_id
        );

        if ($delete_result['success']) {
            $this->session->addFlash('success', 'Message deleted.');
        } else {
            $this->session->addFlash('error', $delete_result['error']);
        }

        $this->redirect('/messages/' . $conversation_id);
    }

    /**
     * Deletes an entire conversation for the current user.
     *
     * POST /messages/{conversation_id}/delete
     *
     * @param int $conversation_id The conversation ID.
     *
     * @return void
     */
    public function deleteConversation(int $conversation_id): void {
        $this->requireAuth();

        if (!$this->validateCsrf()) {
            $this->session->addFlash('error', 'Invalid form submission. Please try again.');
            $this->redirect('/messages');
            return;
        }

        $current_user = $this->session->getCurrentUser();

        $delete_result = $this->message_service->deleteConversation(
            $conversation_id,
            $current_user->user_id
        );

        if ($delete_result['success']) {
            $this->session->addFlash('success', 'Conversation deleted.');
        } else {
            $this->session->addFlash('error', $delete_result['error']);
        }

        $this->redirect('/messages');
    }

    /**
     * Blocks a user.
     *
     * POST /messages/block/{username}
     *
     * @param string $username The username to block.
     *
     * @return void
     */
    public function blockUser(string $username): void {
        $this->requireAuth();

        if (!$this->validateCsrf()) {
            $this->session->addFlash('error', 'Invalid form submission. Please try again.');
            $this->redirect('/messages');
            return;
        }

        $current_user = $this->session->getCurrentUser();
        $user_to_block = $this->user_mapper->findByUsername($username);

        if ($user_to_block === null) {
            $this->session->addFlash('error', 'User not found.');
        } else {
            $block_result = $this->user_block_service->block(
                $current_user->user_id,
                $user_to_block->user_id
            );

            if ($block_result['success']) {
                $this->session->addFlash('success', $username . ' has been blocked.');
            } else {
                $this->session->addFlash('error', $block_result['error']);
            }
        }

        $this->redirect('/messages');
    }

    /**
     * Unblocks a user.
     *
     * POST /messages/unblock/{username}
     *
     * @param string $username The username to unblock.
     *
     * @return void
     */
    public function unblockUser(string $username): void {
        $this->requireAuth();

        if (!$this->validateCsrf()) {
            $this->session->addFlash('error', 'Invalid form submission. Please try again.');
            $this->redirect('/messages');
            return;
        }

        $current_user = $this->session->getCurrentUser();
        $user_to_unblock = $this->user_mapper->findByUsername($username);

        if ($user_to_unblock === null) {
            $this->session->addFlash('error', 'User not found.');
        } else {
            $unblock_result = $this->user_block_service->unblock(
                $current_user->user_id,
                $user_to_unblock->user_id
            );

            if ($unblock_result['success']) {
                $this->session->addFlash('success', $username . ' has been unblocked.');
            } else {
                $this->session->addFlash('error', $unblock_result['error']);
            }
        }

        $this->redirect('/messages');
    }

    /**
     * Searches for users to start a conversation with.
     *
     * GET /messages/search
     *
     * @return string The rendered HTML.
     */
    public function searchUsers(): string {
        $this->requireAuth();

        if (!$this->setting_mapper->isMessagingEnabled()) {
            $this->session->addFlash('error', 'Messaging is currently disabled.');
            $this->redirect('/');
            return '';
        }

        $query = trim((string) $this->getQuery('q', ''));
        $users = [];

        if ($query !== '') {
            $current_user = $this->session->getCurrentUser();
            $found_users = $this->user_mapper->searchUsers($query, 20);

            // Filter to only mutual follows who can be messaged
            foreach ($found_users as $user) {
                if ($user->user_id === $current_user->user_id) {
                    continue;
                }

                $can_message = $this->message_service->canMessage(
                    $current_user->user_id,
                    $user->user_id
                );

                if ($can_message['can_message']) {
                    $users[] = $user;
                }
            }
        }

        return $this->renderThemed('pages/message_search.html.twig', [
            'query' => $query,
            'users' => $users,
        ]);
    }
}
