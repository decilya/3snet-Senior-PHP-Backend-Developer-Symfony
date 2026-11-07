<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Интеграционные тесты для API удаления сущностей.
 *
 * Проверяют полный HTTP-цикл: Security, Rate Limiting, Validation, RFC 7807.
 */
final class DeletionApiTest extends WebTestCase
{
    private KernelBrowser $client;
    private string $authHeader;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->client = self::createClient();

        // Очищаем кэш Rate Limiter перед каждым тестом, чтобы лимиты не пересекались
        $container = self::getContainer();
        $container->get('cache.app')->clear();

        // Формируем Basic Auth заголовок (api:dev_only_change_me)
        $this->authHeader = 'Basic ' . base64_encode('api:dev_only_change_me');
    }

    /**
     * Проверяет, что запрос без заголовка Authorization возвращает 401.
     *
     * @return void
     */
    public function testUnauthorizedWithoutCredentials(): void
    {
        $this->client->request(
            method: 'POST',
            uri: '/api/v1/deletion',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: (string) json_encode(['entityClass' => 'App\Entity\Product', 'ids' => [1]])
        );

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    /**
     * Проверяет, что ошибка валидации DTO возвращает 422 в формате RFC 7807.
     *
     * @return void
     */
    public function testValidationErrorReturnsRfc7807(): void
    {
        $this->client->request(
            method: 'POST',
            uri: '/api/v1/deletion',
            server: [
                'HTTP_AUTHORIZATION' => $this->authHeader,
                'CONTENT_TYPE' => 'application/json'
            ],
            content: (string) json_encode(['entityClass' => 'stdClass', 'ids' => [1]])
        );

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);

        $response = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertSame('about:blank', $response['type']);
        self::assertSame('Validation Failed', $response['title']);
        self::assertSame(422, $response['status']);
    }

    /**
     * Проверяет, что 6-й запрос подряд возвращает 429 и заголовок Retry-After.
     *
     * @return void
     */
    public function testRateLimitExceededReturns429AndRetryAfter(): void
    {
        $payload = (string) json_encode(['entityClass' => 'App\Entity\Product', 'ids' => [1]]);

        // Делаем 5 запросов (они должны пройти валидацию и либо удалить, либо вернуть 200/500, но не 429)
        for ($i = 0; $i < 5; $i++) {
            $this->client->request(
                method: 'POST',
                uri: '/api/v1/deletion',
                server: ['HTTP_AUTHORIZATION' => $this->authHeader, 'CONTENT_TYPE' => 'application/json'],
                content: $payload
            );
            self::assertNotSame(Response::HTTP_TOO_MANY_REQUESTS, $this->client->getResponse()->getStatusCode());
        }

        // 6-й запрос должен быть заблокирован
        $this->client->request(
            method: 'POST',
            uri: '/api/v1/deletion',
            server: ['HTTP_AUTHORIZATION' => $this->authHeader, 'CONTENT_TYPE' => 'application/json'],
            content: $payload
        );

        self::assertResponseStatusCodeSame(Response::HTTP_TOO_MANY_REQUESTS);

        $response = $this->client->getResponse();
        self::assertTrue($response->headers->has('Retry-After'));

        $content = json_decode((string) $response->getContent(), true);
        self::assertSame('Too Many Requests', $content['title']);
        self::assertSame(429, $content['status']);
    }

    /**
     * Проверяет успешный сценарий удаления (HTTP 200).
     *
     * @return void
     */
    public function testSuccessfulDeletionReturns200(): void
    {
        // Очищаем лимитер на всякий случай
        self::getContainer()->get('cache.app')->clear();

        $this->client->request(
            method: 'POST',
            uri: '/api/v1/deletion',
            server: [
                'HTTP_AUTHORIZATION' => $this->authHeader,
                'CONTENT_TYPE' => 'application/json'
            ],
            // передаем [1] вместо []
            content: (string) json_encode(['entityClass' => 'App\Entity\Product', 'ids' => [1]])
        );

        self::assertResponseStatusCodeSame(Response::HTTP_OK);

        $response = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertSame('success', $response['status']);
        self::assertSame(0, $response['data']['total']);
    }
}
