<?php

declare(strict_types=1);

namespace Murmur\Tests\Unit\Service;

use Murmur\Entity\Topic;
use Murmur\Repository\TopicFollowMapper;
use Murmur\Repository\TopicMapper;
use Murmur\Service\TopicService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for TopicService.
 */
class TopicServiceTest extends TestCase {

    protected TopicService $topic_service;

    protected MockObject $topic_mapper;

    protected MockObject $topic_follow_mapper;

    protected function setUp(): void {
        $this->topic_mapper = $this->createMock(TopicMapper::class);
        $this->topic_follow_mapper = $this->createMock(TopicFollowMapper::class);
        $this->topic_service = new TopicService($this->topic_mapper, $this->topic_follow_mapper);
    }

    public function testGetAllTopics(): void {
        $topic1 = new Topic();
        $topic1->topic_id = 1;
        $topic1->name = 'General';

        $topic2 = new Topic();
        $topic2->topic_id = 2;
        $topic2->name = 'Off-topic';

        $this->topic_mapper
            ->method('findAll')
            ->willReturn([$topic1, $topic2]);

        $result = $this->topic_service->getAllTopics();

        $this->assertCount(2, $result);
        $this->assertSame($topic1, $result[0]);
        $this->assertSame($topic2, $result[1]);
    }

    public function testGetTopic(): void {
        $topic = new Topic();
        $topic->topic_id = 1;
        $topic->name = 'General';

        $this->topic_mapper
            ->method('load')
            ->with(1)
            ->willReturn($topic);

        $result = $this->topic_service->getTopic(1);

        $this->assertSame($topic, $result);
    }

    public function testGetTopicNotFound(): void {
        $this->topic_mapper
            ->method('load')
            ->with(999)
            ->willReturn(null);

        $result = $this->topic_service->getTopic(999);

        $this->assertNull($result);
    }

    public function testCreateTopicSuccess(): void {
        $this->topic_mapper
            ->method('findByName')
            ->with('General')
            ->willReturn(null);

        $this->topic_mapper
            ->expects($this->once())
            ->method('save');

        $result = $this->topic_service->createTopic('General');

        $this->assertTrue($result['success']);
        $this->assertInstanceOf(Topic::class, $result['topic']);
        $this->assertEquals('General', $result['topic']->name);
    }

    public function testCreateTopicEmptyName(): void {
        $result = $this->topic_service->createTopic('');

        $this->assertFalse($result['success']);
        $this->assertEquals('Topic name cannot be empty.', $result['error']);
    }

    public function testCreateTopicWhitespaceOnlyName(): void {
        $result = $this->topic_service->createTopic('   ');

        $this->assertFalse($result['success']);
        $this->assertEquals('Topic name cannot be empty.', $result['error']);
    }

    public function testCreateTopicNameTooLong(): void {
        $long_name = str_repeat('a', 51);

        $result = $this->topic_service->createTopic($long_name);

        $this->assertFalse($result['success']);
        $this->assertEquals('Topic name cannot exceed 50 characters.', $result['error']);
    }

    public function testCreateTopicDuplicateName(): void {
        $existing_topic = new Topic();
        $existing_topic->topic_id = 1;
        $existing_topic->name = 'General';

        $this->topic_mapper
            ->method('findByName')
            ->with('General')
            ->willReturn($existing_topic);

        $result = $this->topic_service->createTopic('General');

        $this->assertFalse($result['success']);
        $this->assertEquals('A topic with that name already exists.', $result['error']);
    }

    public function testCreateTopicTrimmedName(): void {
        $this->topic_mapper
            ->method('findByName')
            ->with('General')
            ->willReturn(null);

        $this->topic_mapper
            ->expects($this->once())
            ->method('save');

        $result = $this->topic_service->createTopic('  General  ');

        $this->assertTrue($result['success']);
        $this->assertEquals('General', $result['topic']->name);
    }

    public function testDeleteTopicSuccess(): void {
        $topic = new Topic();
        $topic->topic_id = 1;
        $topic->name = 'General';

        $this->topic_mapper
            ->method('load')
            ->with(1)
            ->willReturn($topic);

        $this->topic_mapper
            ->expects($this->once())
            ->method('delete')
            ->with(1);

        $result = $this->topic_service->deleteTopic(1);

        $this->assertTrue($result['success']);
    }

    public function testDeleteTopicNotFound(): void {
        $this->topic_mapper
            ->method('load')
            ->with(999)
            ->willReturn(null);

        $result = $this->topic_service->deleteTopic(999);

        $this->assertFalse($result['success']);
        $this->assertEquals('Topic not found.', $result['error']);
    }

    public function testGetMaxNameLength(): void {
        $this->assertEquals(50, $this->topic_service->getMaxNameLength());
    }
}
