<?php

declare(strict_types=1);

namespace Murmur\Tests\Unit\Service;

use DealNews\GetConfig\GetConfig;
use Murmur\Service\OAuthConfigService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for OAuthConfigService.
 */
class OAuthConfigServiceTest extends TestCase {

    protected OAuthConfigService $service;

    protected MockObject $config;

    protected function setUp(): void {
        $this->config = $this->createMock(GetConfig::class);
        $this->service = new OAuthConfigService($this->config, 'https://example.com');
    }

    public function testGetClientIdReturnsValue(): void {
        $this->config
            ->method('get')
            ->with('oauth.google.client_id')
            ->willReturn('test_client_id');

        $result = $this->service->getClientId('google');

        $this->assertEquals('test_client_id', $result);
    }

    public function testGetClientIdReturnsNullForEmptyString(): void {
        $this->config
            ->method('get')
            ->with('oauth.google.client_id')
            ->willReturn('');

        $result = $this->service->getClientId('google');

        $this->assertNull($result);
    }

    public function testGetClientIdReturnsNullForNull(): void {
        $this->config
            ->method('get')
            ->with('oauth.google.client_id')
            ->willReturn(null);

        $result = $this->service->getClientId('google');

        $this->assertNull($result);
    }

    public function testGetClientSecretReturnsValue(): void {
        $this->config
            ->method('get')
            ->with('oauth.facebook.client_secret')
            ->willReturn('test_secret');

        $result = $this->service->getClientSecret('facebook');

        $this->assertEquals('test_secret', $result);
    }

    public function testGetRedirectUriIncludesBaseUrl(): void {
        $result = $this->service->getRedirectUri('google');

        $this->assertEquals('https://example.com/oauth/google/callback', $result);
    }

    public function testIsConfiguredReturnsTrueForGoogleWithCredentials(): void {
        $this->config
            ->method('get')
            ->willReturnCallback(function ($key) {
                $values = [
                    'oauth.google.client_id'     => 'test_id',
                    'oauth.google.client_secret' => 'test_secret',
                ];

                return $values[$key] ?? null;
            });

        $result = $this->service->isConfigured('google');

        $this->assertTrue($result);
    }

    public function testIsConfiguredReturnsFalseForGoogleWithoutSecret(): void {
        $this->config
            ->method('get')
            ->willReturnCallback(function ($key) {
                $values = [
                    'oauth.google.client_id'     => 'test_id',
                    'oauth.google.client_secret' => '',
                ];

                return $values[$key] ?? null;
            });

        $result = $this->service->isConfigured('google');

        $this->assertFalse($result);
    }

    public function testIsConfiguredReturnsTrueForAppleWithAllCredentials(): void {
        $this->config
            ->method('get')
            ->willReturnCallback(function ($key) {
                $values = [
                    'oauth.apple.client_id'         => 'test_id',
                    'oauth.apple.team_id'           => 'team123',
                    'oauth.apple.key_id'            => 'key123',
                    'oauth.apple.private_key_path'  => '/path/to/key.p8',
                ];

                return $values[$key] ?? null;
            });

        $result = $this->service->isConfigured('apple');

        $this->assertTrue($result);
    }

    public function testIsConfiguredReturnsFalseForAppleWithoutTeamId(): void {
        $this->config
            ->method('get')
            ->willReturnCallback(function ($key) {
                $values = [
                    'oauth.apple.client_id'         => 'test_id',
                    'oauth.apple.team_id'           => '',
                    'oauth.apple.key_id'            => 'key123',
                    'oauth.apple.private_key_path'  => '/path/to/key.p8',
                ];

                return $values[$key] ?? null;
            });

        $result = $this->service->isConfigured('apple');

        $this->assertFalse($result);
    }

    public function testGetAvailableProvidersReturnsAllProviders(): void {
        $result = $this->service->getAvailableProviders();

        $this->assertCount(3, $result);
        $this->assertContains('google', $result);
        $this->assertContains('facebook', $result);
        $this->assertContains('apple', $result);
    }
}
