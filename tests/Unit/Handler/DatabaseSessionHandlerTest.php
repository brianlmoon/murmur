<?php

declare(strict_types=1);

namespace Murmur\Tests\Unit\Handler;

use Murmur\Entity\Session;
use Murmur\Handler\DatabaseSessionHandler;
use Murmur\Repository\SessionMapper;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for DatabaseSessionHandler.
 */
class DatabaseSessionHandlerTest extends TestCase {

    /**
     * The handler under test.
     */
    protected DatabaseSessionHandler $handler;

    /**
     * Mock session mapper.
     */
    protected MockObject $session_mapper;

    /**
     * Sets up the test fixtures.
     *
     * @return void
     */
    protected function setUp(): void {
        $this->session_mapper = $this->createMock(SessionMapper::class);
        $this->handler        = new DatabaseSessionHandler($this->session_mapper, 604800);
    }

    /**
     * Tests that open() returns true.
     *
     * @return void
     */
    public function testOpenReturnsTrue(): void {
        $result = $this->handler->open('/tmp', 'PHPSESSID');

        $this->assertTrue($result);
    }

    /**
     * Tests that close() returns true.
     *
     * @return void
     */
    public function testCloseReturnsTrue(): void {
        $result = $this->handler->close();

        $this->assertTrue($result);
    }

    /**
     * Tests reading an existing session.
     *
     * @return void
     */
    public function testReadExistingSession(): void {
        $session       = new Session();
        $session->data = 'serialized_data';

        $this->session_mapper
            ->method('findBySessionId')
            ->with('test_session_id')
            ->willReturn($session);

        $result = $this->handler->read('test_session_id');

        $this->assertEquals('serialized_data', $result);
    }

    /**
     * Tests reading a non-existent session.
     *
     * @return void
     */
    public function testReadNonExistentSession(): void {
        $this->session_mapper
            ->method('findBySessionId')
            ->with('nonexistent_id')
            ->willReturn(null);

        $result = $this->handler->read('nonexistent_id');

        $this->assertEquals('', $result);
    }

    /**
     * Tests reading returns false on error.
     *
     * @return void
     */
    public function testReadReturnsFalseOnError(): void {
        $this->session_mapper
            ->method('findBySessionId')
            ->willThrowException(new \Exception('Database error'));

        $result = $this->handler->read('test_id');

        $this->assertFalse($result);
    }

    /**
     * Tests destroying a session.
     *
     * @return void
     */
    public function testDestroy(): void {
        $this->session_mapper
            ->expects($this->once())
            ->method('delete')
            ->with('session_to_destroy');

        $result = $this->handler->destroy('session_to_destroy');

        $this->assertTrue($result);
    }

    /**
     * Tests destroy returns false on error.
     *
     * @return void
     */
    public function testDestroyReturnsFalseOnError(): void {
        $this->session_mapper
            ->method('delete')
            ->willThrowException(new \Exception('Database error'));

        $result = $this->handler->destroy('test_id');

        $this->assertFalse($result);
    }

    /**
     * Tests garbage collection.
     *
     * @return void
     */
    public function testGarbageCollection(): void {
        $this->session_mapper
            ->expects($this->once())
            ->method('deleteExpired')
            ->with(3600)
            ->willReturn(5);

        $result = $this->handler->gc(3600);

        $this->assertEquals(5, $result);
    }

    /**
     * Tests garbage collection returns false on error.
     *
     * @return void
     */
    public function testGarbageCollectionReturnsFalseOnError(): void {
        $this->session_mapper
            ->method('deleteExpired')
            ->willThrowException(new \Exception('Database error'));

        $result = $this->handler->gc(3600);

        $this->assertFalse($result);
    }
}
