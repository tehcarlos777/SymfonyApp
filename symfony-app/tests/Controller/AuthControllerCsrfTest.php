<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Functional CSRF coverage for the /login form (WebTestCase).
 */
class AuthControllerCsrfTest extends WebTestCase
{
    public function testLoginGetRendersCsrfField(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/login');

        self::assertResponseIsSuccessful();
        self::assertSame(1, $crawler->filter('input[name="_csrf_token"]')->count());
        self::assertNotSame('', (string) $crawler->filter('input[name="_csrf_token"]')->attr('value'));
    }

    public function testLoginPostWithInvalidCsrfRedirectsWithErrorFlash(): void
    {
        $client = static::createClient();
        $client->request('POST', '/login', [
            '_csrf_token' => 'invalid-csrf-token',
            'token' => 'some-token',
        ]);

        self::assertResponseRedirects('/login');

        $crawler = $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Invalid CSRF token.', $crawler->filter('.flash-message.error')->text());
    }
}
