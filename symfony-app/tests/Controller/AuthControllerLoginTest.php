<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\AuthController;
use App\Entity\AuthToken;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Unit-style login checks; avoids mocking EntityManager inside WebTestCase.
 */
class AuthControllerLoginTest extends TestCase
{
    public function testPostWithValidCsrfAndInvalidTokenRedirectsToLoginWithError(): void
    {
        $repository = $this->createMock(ObjectRepository::class);
        $repository
            ->method('findOneBy')
            ->with(['token' => hash_hmac('sha256', 'wrong-token', 'test-hmac-secret')])
            ->willReturn(null);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager
            ->method('getRepository')
            ->with(AuthToken::class)
            ->willReturn($repository);

        $csrfTokenManager = $this->createMock(CsrfTokenManagerInterface::class);

        $controller = new class () extends AuthController {
            /** @var array<string, string> */
            public array $capturedFlash = [];

            public function __construct()
            {
                parent::__construct('test-hmac-secret');
            }

            protected function isCsrfTokenValid(string $id, ?string $token): bool
            {
                return true;
            }

            public function addFlash(string $type, mixed $message): void
            {
                $this->capturedFlash[$type] = (string) $message;
            }

            protected function redirectToRoute(string $route, array $parameters = [], int $status = 302): RedirectResponse
            {
                return new RedirectResponse('/login', $status);
            }
        };

        $request = Request::create('/login', 'POST', [
            '_csrf_token' => 'valid-csrf-token',
            'token' => 'wrong-token',
        ]);

        $response = $controller->login($entityManager, $request, $csrfTokenManager);

        self::assertSame(Response::HTTP_FOUND, $response->getStatusCode());
        self::assertSame('/login', $response->headers->get('Location'));
        self::assertSame('Invalid token.', $controller->capturedFlash['error'] ?? null);
    }
}
