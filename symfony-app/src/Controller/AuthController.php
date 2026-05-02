<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\AuthToken;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

class AuthController extends AbstractController
{
    public function __construct(private readonly string $tokenHmacSecret)
    {
    }

    private function hashToken(string $plaintext): string
    {
        return hash_hmac('sha256', $plaintext, $this->tokenHmacSecret);
    }

    #[Route('/login', name: 'auth_login', methods: ['GET', 'POST'])]
    public function login(EntityManagerInterface $entityManager, Request $request, CsrfTokenManagerInterface $csrfTokenManager): Response
    {
        if ($request->isMethod('GET')) {
            return $this->render('auth/login.html.twig', [
                'csrf_token' => $csrfTokenManager->getToken('login')->getValue(),
            ]);
        }

        if (!$this->isCsrfTokenValid('login', $request->request->get('_csrf_token'))) {
            $this->addFlash('error', 'Invalid CSRF token.');
            return $this->redirectToRoute('auth_login');
        }

        $plaintext = $request->request->get('token', '');
        $hash = $this->hashToken($plaintext);

        $tokenEntity = $entityManager
            ->getRepository(AuthToken::class)
            ->findOneBy(['token' => $hash]);

        if (!$tokenEntity instanceof AuthToken || !hash_equals($tokenEntity->getToken(), $hash)) {
            $this->addFlash('error', 'Invalid token.');
            return $this->redirectToRoute('auth_login');
        }

        $userEntity = $tokenEntity->getUser();

        $session = $request->getSession();
        $session->set('user_id', $userEntity->getId());
        $session->set('username', $userEntity->getUsername());

        $this->addFlash('success', 'Welcome back, ' . $userEntity->getUsername() . '!');

        return $this->redirectToRoute('home');
    }

    #[Route('/logout', name: 'logout')]
    public function logout(Request $request): Response
    {
        $session = $request->getSession();
        $session->clear();

        $this->addFlash('info', 'You have been logged out successfully.');

        return $this->redirectToRoute('home');
    }
}
