<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Photo;
use App\Entity\User;
use DateTimeImmutable;
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
        /** @var list<Photo> $result */
        $result = $this->createQueryBuilder('photo')
            ->where('photo.user = :user')
            ->andWhere('photo.phoenixPhotoId = :phoenixPhotoId')
            ->setParameter('user', $user)
            ->setParameter('phoenixPhotoId', $phoenixPhotoId)
            ->orderBy('photo.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getResult();

        return $result[0] ?? null;
    }

    /**
     * @param array{
     *     location?: string,
     *     camera?: string,
     *     description?: string,
     *     username?: string,
     *     taken_at?: string
     * } $filters
     *
     * @return list<Photo>
     */
    public function findWithUsersAndFilters(array $filters = []): array
    {
        $qb = $this->createQueryBuilder('photo')
            ->leftJoin('photo.user', 'user')
            ->addSelect('user')
            ->orderBy('photo.id', 'ASC');

        $location = trim((string) ($filters['location'] ?? ''));
        if ($location !== '') {
            $qb->andWhere('LOWER(photo.location) LIKE :filterLocation')
                ->setParameter('filterLocation', '%' . mb_strtolower($location) . '%');
        }

        $camera = trim((string) ($filters['camera'] ?? ''));
        if ($camera !== '') {
            $qb->andWhere('LOWER(photo.camera) LIKE :filterCamera')
                ->setParameter('filterCamera', '%' . mb_strtolower($camera) . '%');
        }

        $description = trim((string) ($filters['description'] ?? ''));
        if ($description !== '') {
            $qb->andWhere('LOWER(photo.description) LIKE :filterDescription')
                ->setParameter('filterDescription', '%' . mb_strtolower($description) . '%');
        }

        $username = trim((string) ($filters['username'] ?? ''));
        if ($username !== '') {
            $qb->andWhere('LOWER(user.username) LIKE :filterUsername')
                ->setParameter('filterUsername', '%' . mb_strtolower($username) . '%');
        }

        $takenAtRaw = trim((string) ($filters['taken_at'] ?? ''));
        if ($takenAtRaw !== '') {
            $dayStart = DateTimeImmutable::createFromFormat('!d.m.Y', $takenAtRaw);
            if (!$dayStart instanceof DateTimeImmutable) {
                $dayStart = DateTimeImmutable::createFromFormat('!Y/m/d', $takenAtRaw);
            }
            if (!$dayStart instanceof DateTimeImmutable) {
                $dayStart = DateTimeImmutable::createFromFormat('!Y-m-d', $takenAtRaw);
            }
            if ($dayStart instanceof DateTimeImmutable) {
                $dayEnd = $dayStart->modify('+1 day');
                $qb->andWhere('photo.takenAt IS NOT NULL')
                    ->andWhere('photo.takenAt >= :filterTakenAtStart')
                    ->andWhere('photo.takenAt < :filterTakenAtEnd')
                    ->setParameter('filterTakenAtStart', $dayStart)
                    ->setParameter('filterTakenAtEnd', $dayEnd);
            }
        }

        /** @var list<Photo> */
        return $qb->getQuery()->getResult();
    }

}
