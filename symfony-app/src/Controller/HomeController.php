<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Likes\LikeRepository;
use App\Repository\PhotoRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class HomeController extends AbstractController
{
    public function __construct(
        private readonly PhotoRepository $photoRepository,
        private readonly LikeRepository $likeRepository,
    ) {}

    #[Route('/', name: 'home')]
    public function index(Request $request, EntityManagerInterface $em): Response
    {
        $photos = $this->photoRepository->findAllWithUsers();

        $session = $request->getSession();
        $userId = $session->get('user_id');
        $currentUser = null;
        $userLikes = [];

        if ($userId) {
            $currentUser = $em->getRepository(User::class)->find($userId);

            if ($currentUser) {
                $photoIds = array_map(static fn ($photo) => $photo->getId(), $photos);
                $likedPhotoIds = $this->likeRepository->findLikedPhotoIdsForUser($currentUser, $photoIds);
                $likedLookup = array_flip($likedPhotoIds);

                foreach ($photos as $photo) {
                    $userLikes[$photo->getId()] = isset($likedLookup[$photo->getId()]);
                }
            }
        }

        return $this->render('home/index.html.twig', [
            'photos' => $photos,
            'currentUser' => $currentUser,
            'userLikes' => $userLikes,
        ]);
    }
}
