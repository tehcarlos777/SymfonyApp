<?php

declare(strict_types=1);

namespace App\Tests\Import;

use App\Entity\Photo;
use App\Entity\User;
use App\Import\PhoenixPhotoImporter;
use App\Repository\PhotoRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class PhoenixPhotoImporterTest extends TestCase
{
    public function testImportThrowsWhenTokenMissing(): void
    {
        $user = (new User())->setUsername('u')->setEmail('u@example.com')->setPhoenixApiToken(null);

        $importer = new PhoenixPhotoImporter(
            new MockHttpClient(),
            $this->createMock(PhotoRepository::class),
            $this->createMock(EntityManagerInterface::class),
            'http://localhost:4000',
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Brak tokenu Phoenix.');

        $importer->importForUser($user);
    }

    public function testImportThrowsOn401(): void
    {
        $client = new MockHttpClient([
            new MockResponse(json_encode(['photos' => []]), ['http_code' => 401]),
        ]);

        $user = (new User())->setUsername('u')->setEmail('u@example.com')->setPhoenixApiToken('tok');

        $importer = new PhoenixPhotoImporter(
            $client,
            $this->createMock(PhotoRepository::class),
            $this->createMock(EntityManagerInterface::class),
            'http://localhost:4000',
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Token Phoenix jest nieprawidłowy.');

        $importer->importForUser($user);
    }

    public function testImportThrowsOn429(): void
    {
        $client = new MockHttpClient([
            new MockResponse('', ['http_code' => 429]),
        ]);

        $user = (new User())->setUsername('u')->setEmail('u@example.com')->setPhoenixApiToken('tok');

        $importer = new PhoenixPhotoImporter(
            $client,
            $this->createMock(PhotoRepository::class),
            $this->createMock(EntityManagerInterface::class),
            'http://localhost:4000',
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('429');

        $importer->importForUser($user);
    }

    public function testImportThrowsWhenTransportFails(): void
    {
        $client = new MockHttpClient(static function () {
            throw new TransportException('Connection refused.');
        });

        $user = (new User())->setUsername('u')->setEmail('u@example.com')->setPhoenixApiToken('tok');

        $importer = new PhoenixPhotoImporter(
            $client,
            $this->createMock(PhotoRepository::class),
            $this->createMock(EntityManagerInterface::class),
            'http://localhost:4000',
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Nie udało się połączyć z Phoenix API.');

        $importer->importForUser($user);
    }

    public function testImportPersistsNewPhotosAndSkipsDuplicates(): void
    {
        $payload = [
            'photos' => [
                ['id' => 1, 'photo_url' => 'https://example.com/a.jpg'],
                ['id' => 2, 'photo_url' => 'https://example.com/b.jpg'],
                'broken',
                ['id' => 1, 'photo_url' => 'https://example.com/a.jpg'],
                ['id' => 3, 'photo_url' => ''],
            ],
        ];

        $client = new MockHttpClient([
            new MockResponse(json_encode($payload), ['http_code' => 200]),
        ]);

        $user = (new User())->setUsername('u')->setEmail('u@example.com')->setPhoenixApiToken('secret');

        $existing = new Photo();
        $existing->setUser($user)->setImageUrl('https://example.com/a.jpg')->setPhoenixPhotoId(1);

        $repository = $this->createMock(PhotoRepository::class);
        $repository
            ->method('findOneByUserAndPhoenixPhotoId')
            ->willReturnCallback(static function (User $u, int $remoteId) use ($user, $existing): ?Photo {
                self::assertSame($user, $u);
                return $remoteId === 1 ? $existing : null;
            });

        $persisted = [];
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('flush');
        $em
            ->expects(self::once())
            ->method('persist')
            ->willReturnCallback(static function (Photo $photo) use (&$persisted, $user): void {
                $persisted[] = $photo;
                self::assertSame($user, $photo->getUser());
            });

        $importer = new PhoenixPhotoImporter($client, $repository, $em, 'http://phoenix.test');

        $result = $importer->importForUser($user);

        self::assertSame([
            'total' => 5,
            'imported' => 1,
            'skipped' => 2,
        ], $result);

        self::assertCount(1, $persisted);
        self::assertSame(2, $persisted[0]->getPhoenixPhotoId());
        self::assertSame('https://example.com/b.jpg', $persisted[0]->getImageUrl());
    }

    public function testRequestUsesTrimmedBaseUrlAndAccessTokenHeader(): void
    {
        $captured = [];
        $client = new MockHttpClient(static function ($method, $url, $options) use (&$captured) {
            $captured['method'] = $method;
            $captured['url'] = $url;
            $captured['headers'] = $options['headers'] ?? [];

            return new MockResponse(json_encode(['photos' => []]), ['http_code' => 200]);
        });

        $user = (new User())->setUsername('u')->setEmail('u@example.com')->setPhoenixApiToken('  my-token  ');

        $emptyRepo = $this->createMock(PhotoRepository::class);
        $emptyRepo->method('findOneByUserAndPhoenixPhotoId')->willReturn(null);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('flush');

        $importer = new PhoenixPhotoImporter($client, $emptyRepo, $em, 'http://phoenix.test///');

        $importer->importForUser($user);

        self::assertSame('GET', $captured['method']);
        self::assertSame('http://phoenix.test/api/photos', $captured['url']);
        self::assertContains('access-token: my-token', $captured['headers']);
    }
}