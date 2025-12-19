<?php

declare(strict_types=1);

namespace Murmur\Service;

use Murmur\Entity\Topic;
use Murmur\Entity\TopicFollow;
use Murmur\Repository\TopicFollowMapper;
use Murmur\Repository\TopicMapper;

/**
 * Service for topic operations.
 *
 * Handles topic CRUD operations for conversation categorization and topic following.
 */
class TopicService {

    /**
     * Maximum length for topic name.
     */
    protected const MAX_NAME_LENGTH = 50;

    /**
     * The topic mapper for database operations.
     */
    protected TopicMapper $topic_mapper;

    /**
     * The topic follow mapper for database operations.
     */
    protected TopicFollowMapper $topic_follow_mapper;

    /**
     * Creates a new TopicService instance.
     *
     * @param TopicMapper       $topic_mapper        The topic mapper.
     * @param TopicFollowMapper $topic_follow_mapper The topic follow mapper.
     */
    public function __construct(TopicMapper $topic_mapper, TopicFollowMapper $topic_follow_mapper) {
        $this->topic_mapper = $topic_mapper;
        $this->topic_follow_mapper = $topic_follow_mapper;
    }

    /**
     * Retrieves all topics.
     *
     * @return array<Topic> Array of Topic entities.
     */
    public function getAllTopics(): array {
        return $this->topic_mapper->findAll();
    }

    /**
     * Retrieves a topic by ID.
     *
     * @param int $topic_id The topic ID.
     *
     * @return Topic|null The topic or null if not found.
     */
    public function getTopic(int $topic_id): ?Topic {
        return $this->topic_mapper->load($topic_id);
    }

    /**
     * Creates a new topic.
     *
     * @param string $name The topic name.
     *
     * @return array{success: bool, topic?: Topic, error?: string}
     */
    public function createTopic(string $name): array {
        $result = ['success' => false];

        $name = trim($name);
        $validation_error = $this->validateName($name);

        if ($validation_error !== null) {
            $result['error'] = $validation_error;
        } elseif ($this->topic_mapper->findByName($name) !== null) {
            $result['error'] = 'A topic with that name already exists.';
        } else {
            $topic = new Topic();
            $topic->name = $name;

            $this->topic_mapper->save($topic);

            $result['success'] = true;
            $result['topic'] = $topic;
        }

        return $result;
    }

    /**
     * Deletes a topic.
     *
     * @param int $topic_id The topic ID to delete.
     *
     * @return array{success: bool, error?: string}
     */
    public function deleteTopic(int $topic_id): array {
        $result = ['success' => false];

        $topic = $this->topic_mapper->load($topic_id);

        if ($topic === null) {
            $result['error'] = 'Topic not found.';
        } else {
            $this->topic_mapper->delete($topic_id);
            $result['success'] = true;
        }

        return $result;
    }

    /**
     * Validates topic name.
     *
     * @param string $name The topic name to validate.
     *
     * @return string|null Error message or null if valid.
     */
    protected function validateName(string $name): ?string {
        $error = null;

        if ($name === '') {
            $error = 'Topic name cannot be empty.';
        } elseif (mb_strlen($name) > self::MAX_NAME_LENGTH) {
            $error = 'Topic name cannot exceed ' . self::MAX_NAME_LENGTH . ' characters.';
        }

        return $error;
    }

    /**
     * Gets the maximum allowed topic name length.
     *
     * @return int The maximum length.
     */
    public function getMaxNameLength(): int {
        return self::MAX_NAME_LENGTH;
    }

    /**
     * Follows a topic for a user.
     *
     * @param int $user_id  The user ID.
     * @param int $topic_id The topic ID.
     *
     * @return array{success: bool, error?: string}
     */
    public function followTopic(int $user_id, int $topic_id): array {
        $result = ['success' => false];

        $topic = $this->topic_mapper->load($topic_id);

        if ($topic === null) {
            $result['error'] = 'Topic not found.';
        } elseif ($this->topic_follow_mapper->findByUserAndTopic($user_id, $topic_id) !== null) {
            $result['success'] = true;
        } else {
            $follow = new TopicFollow();
            $follow->user_id = $user_id;
            $follow->topic_id = $topic_id;

            $this->topic_follow_mapper->save($follow);
            $result['success'] = true;
        }

        return $result;
    }

    /**
     * Unfollows a topic for a user.
     *
     * @param int $user_id  The user ID.
     * @param int $topic_id The topic ID.
     *
     * @return array{success: bool, error?: string}
     */
    public function unfollowTopic(int $user_id, int $topic_id): array {
        $result = ['success' => false];

        $follow = $this->topic_follow_mapper->findByUserAndTopic($user_id, $topic_id);

        if ($follow === null) {
            $result['success'] = true;
        } else {
            $this->topic_follow_mapper->delete($follow->follow_id);
            $result['success'] = true;
        }

        return $result;
    }

    /**
     * Checks if a user is following a topic.
     *
     * @param int $user_id  The user ID.
     * @param int $topic_id The topic ID.
     *
     * @return bool True if following, false otherwise.
     */
    public function isFollowing(int $user_id, int $topic_id): bool {
        return $this->topic_follow_mapper->findByUserAndTopic($user_id, $topic_id) !== null;
    }

    /**
     * Retrieves all topics a user is following.
     *
     * @param int $user_id The user ID.
     *
     * @return array<Topic> Array of Topic entities.
     */
    public function getFollowedTopics(int $user_id): array {
        $result = [];

        $follows = $this->topic_follow_mapper->findByUserId($user_id);
        $topic_ids = array_map(fn($f) => $f->topic_id, $follows);

        foreach ($topic_ids as $topic_id) {
            $topic = $this->topic_mapper->load($topic_id);
            if ($topic !== null) {
                $result[] = $topic;
            }
        }

        return $result;
    }

    /**
     * Retrieves just the topic IDs that a user follows.
     *
     * @param int $user_id The user ID.
     *
     * @return array<int> Array of topic IDs.
     */
    public function getFollowedTopicIds(int $user_id): array {
        return $this->topic_follow_mapper->getFollowedTopicIds($user_id);
    }
}
