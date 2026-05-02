<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Command\SeedDatabaseCommand;
use App\Controller\AuthController;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Contract: AuthController and SeedDatabaseCommand must hash tokens identically.
 */
class TokenHashingTest extends TestCase
{
    public function testAuthControllerAndSeedCommandUseSameHashingAlgorithm(): void
    {
        $plaintext = 'sample-token-123';
        $secret = 'test-secret';

        $authController = new AuthController($secret);
        $seedCommand = new SeedDatabaseCommand($this->createMock(EntityManagerInterface::class), $secret);

        $authHash = $this->invokeHashToken($authController, $plaintext);
        $seedHash = $this->invokeHashToken($seedCommand, $plaintext);

        self::assertSame($authHash, $seedHash);
        self::assertSame(hash_hmac('sha256', $plaintext, $secret), $authHash);
    }

    public function testDifferentSecretsProduceDifferentHashes(): void
    {
        $plaintext = 'sample-token-123';

        $firstController = new AuthController('secret-a');
        $secondController = new AuthController('secret-b');

        $firstHash = $this->invokeHashToken($firstController, $plaintext);
        $secondHash = $this->invokeHashToken($secondController, $plaintext);

        self::assertNotSame($firstHash, $secondHash);
    }

    private function invokeHashToken(object $service, string $plaintext): string
    {
        $method = new ReflectionMethod($service, 'hashToken');

        return $method->invoke($service, $plaintext);
    }
}
