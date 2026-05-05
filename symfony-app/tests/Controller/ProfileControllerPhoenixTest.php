<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\ProfileController;
use App\Entity\User;
use App\Import\PhoenixPhotoImporter;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

class ProfileControllerPhoenixTest extends TestCase
{
    private function controllerAcceptingCsrf(bool $valid): ProfileController
    {
        return new class ($valid) extends ProfileController {
            /** @var array<string, list<string>> */
            public array $flashes = [];

            public function __construct(private readonly bool $csrfOk)
            {
            }

            protected function isCsrfTokenValid(string $id, ?string $token): bool
            {
                return $this->csrfOk;
            }

            public function addFlash(string $type, mixed $message): void
            {
                $this->flashes[$type][] = (string) $message;
            }

            protected function redirectToRoute(string $route, array $parameters = [], int $status = 302): RedirectResponse
            {
                return new RedirectResponse('/route/'.$route, $status);
            }
        };
    }

    private function sessionRequest(string $uri, string $method, array $parameters, int $userId): Request
    {
        $session = new Session(new MockArraySessionStorage());
        $session->set('user_id', $userId);

        $request = Request::create($uri, $method, $parameters);
        $request->setSession($session);

        return $request;
    }

    public function testImportPhotosInvalidCsrfAddsErrorFlash(): void
    {
        $controller = $this->controllerAcceptingCsrf(false);
        $request = $this->sessionRequest('/profile/import-photos', 'POST', ['_token' => 'x'], 1);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('getRepository');

        $response = $controller->importPhotos($request, $em, $this->createMock(PhoenixPhotoImporter::class));

        self::assertSame(Response::HTTP_FOUND, $response->getStatusCode());
        self::assertSame('/route/profile', $response->headers->get('Location'));
        self::assertSame(['Invalid CSRF token.'], $controller->flashes['error'] ?? []);
    }

    public function testImportPhotosRuntimeExceptionAddsErrorFlash(): void
    {
        $controller = $this->controllerAcceptingCsrf(true);
        $request = $this->sessionRequest('/profile/import-photos', 'POST', ['_token' => 'ok'], 99);

        $user = (new User())->setUsername('u')->setEmail('u@example.com');

        $repository = $this->createMock(EntityRepository::class);
        $repository->method('find')->with(99)->willReturn($user);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->with(User::class)->willReturn($repository);

        $importer = $this->createMock(PhoenixPhotoImporter::class);
        $importer->method('importForUser')->with($user)->willThrowException(new RuntimeException('API padło.'));

        $response = $controller->importPhotos($request, $em, $importer);

        self::assertSame(Response::HTTP_FOUND, $response->getStatusCode());
        self::assertSame(['API padło.'], $controller->flashes['error'] ?? []);
    }

    public function testImportPhotosSuccessAddsSummaryFlash(): void
    {
        $controller = $this->controllerAcceptingCsrf(true);
        $request = $this->sessionRequest('/profile/import-photos', 'POST', ['_token' => 'ok'], 7);

        $user = (new User())->setUsername('u')->setEmail('u@example.com');

        $repository = $this->createMock(EntityRepository::class);
        $repository->method('find')->with(7)->willReturn($user);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->with(User::class)->willReturn($repository);

        $importer = $this->createMock(PhoenixPhotoImporter::class);
        $importer->method('importForUser')->willReturn(['total' => 10, 'imported' => 3, 'skipped' => 7]);

        $response = $controller->importPhotos($request, $em, $importer);

        self::assertSame(Response::HTTP_FOUND, $response->getStatusCode());
        self::assertSame(
            ['Import zakończony: dodano 3, pominięto 7 (łącznie z API: 10).'],
            $controller->flashes['success'] ?? [],
        );
    }

    public function testSavePhoenixTokenClearsTokenWhenEmptyStringPosted(): void
    {
        $controller = $this->controllerAcceptingCsrf(true);
        $request = $this->sessionRequest('/profile/phoenix-token', 'POST', [
            '_token' => 'ok',
            'phoenix_api_token' => '   ',
        ], 3);

        $user = (new User())->setUsername('u')->setEmail('u@example.com')->setPhoenixApiToken('keep-me');

        $repository = $this->createMock(EntityRepository::class);
        $repository->method('find')->with(3)->willReturn($user);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->with(User::class)->willReturn($repository);
        $em->expects(self::once())->method('flush');

        $controller->savePhoenixToken($request, $em);

        self::assertNull($user->getPhoenixApiToken());
        self::assertSame(['Token Phoenix został zapisany.'], $controller->flashes['success'] ?? []);
    }

    public function testSavePhoenixTokenTrimsAndStoresValue(): void
    {
        $controller = $this->controllerAcceptingCsrf(true);
        $request = $this->sessionRequest('/profile/phoenix-token', 'POST', [
            '_token' => 'ok',
            'phoenix_api_token' => '  abc  ',
        ], 3);

        $user = (new User())->setUsername('u')->setEmail('u@example.com');

        $repository = $this->createMock(EntityRepository::class);
        $repository->method('find')->with(3)->willReturn($user);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->with(User::class)->willReturn($repository);
        $em->expects(self::once())->method('flush');

        $controller->savePhoenixToken($request, $em);

        self::assertSame('abc', $user->getPhoenixApiToken());
    }
}
