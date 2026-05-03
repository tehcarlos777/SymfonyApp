<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Photo;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class PhotoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Photo::class);
    }

    public function findOneByUserAndPhoenixPhotoId(User $user, int $phoenixPhotoId): ?Photo
    {
        return $this->createQueryBuilder('photo')
            ->where('photo.user = :user')
            ->andWhere('photo.phoenixPhotoId = :phoenixPhotoId')
            ->setParameter('user', $user)
            ->setParameter('phoenixPhotoId', $phoenixPhotoId)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findAllWithUsers(): array
    {
        return $this->createQueryBuilder('photo')
            ->leftJoin('photo.user', 'user')
            ->addSelect('user')
            ->orderBy('photo.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
