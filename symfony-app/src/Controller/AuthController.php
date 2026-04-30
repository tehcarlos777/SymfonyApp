<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\AuthToken;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AuthController extends AbstractController
{
    #[Route('/auth/{username}/{token}', name: 'auth_login')]
    public function login(string $username, string $token, EntityManagerInterface $entityManager, Request $request): Response
    {
        $tokenEntity = $entityManager
            ->getRepository(AuthToken::class)
            ->findOneBy(['token' => $token]);

        if (!$tokenEntity instanceof AuthToken) {
            return new Response('Invalid token', 401);
        }

        $userEntity = $entityManager
            ->getRepository(User::class)
            ->findOneBy(['username' => $username]);

        if (!$userEntity instanceof User) {
            return new Response('User not found', 404);
        }

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
