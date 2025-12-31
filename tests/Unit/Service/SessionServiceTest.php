<?php

declare(strict_types=1);

namespace Murmur\Tests\Unit\Service;

use Murmur\Entity\User;
use Murmur\Repository\SessionMapper;
use Murmur\Repository\UserMapper;
use Murmur\Service\SessionService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for SessionService.
 *
 * Note: These tests focus on the database-backed session functionality.
 * Testing session lifecycle methods (start, login, logout) requires
 * integration tests due to PHP session dependencies.
 */
class SessionServiceTest extends TestCase {

    /**
     * The service under test.
     */
    protected SessionService $session_service;

    /**
     * Mock user mapper.
     */
    protected MockObject $user_mapper;

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
        $this->user_mapper    = $this->createMock(UserMapper::class);
        $this->session_mapper = $this->createMock(SessionMapper::class);
        $this->session_service = new SessionService($this->user_mapper, $this->session_mapper);
    }

    /**
     * Tests that logoutOtherDevices calls mapper correctly.
     *
     * @return void
     *
     * @runInSeparateProcess
     */
    public function testLogoutOtherDevicesCallsMapper(): void {
        // Start a session so session_id() returns a value
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $this->session_mapper
            ->expects($this->once())
            ->method('deleteByUserIdExcept')
            ->with(
                1,
                $this->callback(function ($session_id) {
                    return is_string($session_id) && strlen($session_id) > 0;
                })
            )
            ->willReturn(3);

        $result = $this->session_service->logoutOtherDevices(1);

        $this->assertEquals(3, $result);
    }

    /**
     * Tests that logoutOtherDevices returns 0 when no mapper provided.
     *
     * @return void
     */
    public function testLogoutOtherDevicesWithoutMapper(): void {
        $service = new SessionService($this->user_mapper, null);

        $result = $service->logoutOtherDevices(1);

        $this->assertEquals(0, $result);
    }

    /**
     * Tests backward compatibility when session mapper is null.
     *
     * @return void
     */
    public function testConstructorAcceptsNullSessionMapper(): void {
        $service = new SessionService($this->user_mapper);

        $this->assertInstanceOf(SessionService::class, $service);
    }

    /**
     * Tests logoutOtherDevices returns 0 when no sessions to delete.
     *
     * @return void
     *
     * @runInSeparateProcess
     */
    public function testLogoutOtherDevicesReturnsZeroWhenNoOtherSessions(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $this->session_mapper
            ->expects($this->once())
            ->method('deleteByUserIdExcept')
            ->willReturn(0);

        $result = $this->session_service->logoutOtherDevices(1);

        $this->assertEquals(0, $result);
    }
}
