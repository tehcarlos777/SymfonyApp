<?php

declare(strict_types=1);

namespace App\Import;

use App\Entity\Photo;
use App\Entity\User;
use App\Repository\PhotoRepository;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class PhoenixPhotoImporter
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly PhotoRepository $photoRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly string $phoenixBaseUrl,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchPayload(string $endpoint, string $token): array
    {
        try {
            $response = $this->httpClient->request('GET', $endpoint, [
                'headers' => ['access-token' => $token],
                'timeout' => 10,
            ]);

            // getStatusCode() and toArray() can also throw TransportExceptionInterface
            // in lazy HTTP clients (CurlHttpClient), so they must be inside the same try block.
            $statusCode = $response->getStatusCode();
        } catch (TransportExceptionInterface) {
            throw new RuntimeException('Nie udało się połączyć z Phoenix API.');
        }

        if ($statusCode === 401) {
            throw new RuntimeException('Token Phoenix jest nieprawidłowy.');
        }
        if ($statusCode === 429) {
            throw new RuntimeException('Przekroczono limit importu w Phoenix API (429). Spróbuj ponownie później.');
        }
        if ($statusCode < 200 || $statusCode >= 300) {
            throw new RuntimeException(sprintf('Phoenix API zwróciło nieoczekiwany status: %d.', $statusCode));
        }

        try {
            return $response->toArray(false);
        } catch (TransportExceptionInterface) {
            throw new RuntimeException('Nie udało się odczytać odpowiedzi Phoenix API.');
        }
    }

    /**
     * @return array{total:int, imported:int, skipped:int}
     */
    public function importForUser(User $user): array
    {
        $token = trim((string) $user->getPhoenixApiToken());
        if ($token === '') {
            throw new RuntimeException('Brak tokenu Phoenix. Uzupełnij go w profilu.');
        }

        $endpoint = rtrim($this->phoenixBaseUrl, '/') . '/api/photos';

        $payload = $this->fetchPayload($endpoint, $token);
        $photos = is_array($payload['photos'] ?? null) ? $payload['photos'] : [];

        $imported = 0;
        $skipped = 0;

        foreach ($photos as $remotePhoto) {
            if (!is_array($remotePhoto)) {
                continue;
            }

            $remoteId = $remotePhoto['id'] ?? null;
            $photoUrl = trim((string) ($remotePhoto['photo_url'] ?? ''));

            if (!is_int($remoteId) || $photoUrl === '') {
                continue;
            }

            $existing = $this->photoRepository->findOneByUserAndPhoenixPhotoId($user, $remoteId);
            if ($existing instanceof Photo) {
                ++$skipped;
                continue;
            }

            $photo = new Photo();
            $photo
                ->setUser($user)
                ->setImageUrl($photoUrl)
                ->setPhoenixPhotoId($remoteId);

            $this->entityManager->persist($photo);
            ++$imported;
        }

        $this->entityManager->flush();

        return [
            'total' => count($photos),
            'imported' => $imported,
            'skipped' => $skipped,
        ];
    }
}
