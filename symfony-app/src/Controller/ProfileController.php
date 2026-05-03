<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Import\PhoenixPhotoImporter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ProfileController extends AbstractController
{
    #[Route('/profile', name: 'profile')]
    public function profile(Request $request, EntityManagerInterface $em): Response
    {
        $session = $request->getSession();
        $userId = $session->get('user_id');

        if (!$userId) {
            return $this->redirectToRoute('home');
        }

        $user = $em->getRepository(User::class)->find($userId);

        if (!$user) {
            $session->clear();
            return $this->redirectToRoute('home');
        }

        return $this->render('profile/index.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/profile/phoenix-token', name: 'profile_phoenix_token_save', methods: ['POST'])]
    public function savePhoenixToken(Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('save_phoenix_token', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid CSRF token.');
            return $this->redirectToRoute('profile');
        }

        $session = $request->getSession();
        $userId = $session->get('user_id');

        if (!$userId) {
            return $this->redirectToRoute('home');
        }

        $user = $em->getRepository(User::class)->find($userId);
        if (!$user instanceof User) {
            $session->clear();
            return $this->redirectToRoute('home');
        }

        $token = trim((string) $request->request->get('phoenix_api_token', ''));
        $user->setPhoenixApiToken($token === '' ? null : $token);
        $em->flush();

        $this->addFlash('success', 'Token Phoenix został zapisany.');

        return $this->redirectToRoute('profile');
    }

    #[Route('/profile/import-photos', name: 'profile_import_photos', methods: ['POST'])]
    public function importPhotos(Request $request, EntityManagerInterface $em, PhoenixPhotoImporter $phoenixPhotoImporter): Response
    {
        if (!$this->isCsrfTokenValid('import_photos', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid CSRF token.');
            return $this->redirectToRoute('profile');
        }

        $session = $request->getSession();
        $userId = $session->get('user_id');
        if (!$userId) {
            return $this->redirectToRoute('home');
        }

        $user = $em->getRepository(User::class)->find($userId);
        if (!$user instanceof User) {
            $session->clear();
            return $this->redirectToRoute('home');
        }

        try {
            $result = $phoenixPhotoImporter->importForUser($user);
        } catch (\RuntimeException $exception) {
            $this->addFlash('error', $exception->getMessage());
            return $this->redirectToRoute('profile');
        }

        $this->addFlash(
            'success',
            sprintf(
                'Import zakończony: dodano %d, pominięto %d (łącznie z API: %d).',
                $result['imported'],
                $result['skipped'],
                $result['total'],
            ),
        );

        return $this->redirectToRoute('profile');
    }
}
