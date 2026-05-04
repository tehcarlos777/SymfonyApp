<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Photo;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

class PhoenixImportFieldsTest extends TestCase
{
    public function testUserPhoenixApiTokenRoundTrip(): void
    {
        $user = (new User())->setUsername('u')->setEmail('u@example.com');

        self::assertNull($user->getPhoenixApiToken());

        $user->setPhoenixApiToken('token-value');
        self::assertSame('token-value', $user->getPhoenixApiToken());

        $user->setPhoenixApiToken(null);
        self::assertNull($user->getPhoenixApiToken());
    }

    public function testPhotoPhoenixPhotoIdRoundTrip(): void
    {
        $user = (new User())->setUsername('u')->setEmail('u@example.com');
        $photo = (new Photo())->setUser($user)->setImageUrl('https://example.com/x.jpg');

        self::assertNull($photo->getPhoenixPhotoId());

        $photo->setPhoenixPhotoId(4242);
        self::assertSame(4242, $photo->getPhoenixPhotoId());

        $photo->setPhoenixPhotoId(null);
        self::assertNull($photo->getPhoenixPhotoId());
    }
}
