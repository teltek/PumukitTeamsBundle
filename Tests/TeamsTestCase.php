<?php

declare(strict_types=1);

namespace Pumukit\TeamsBundle\Tests;

use Firebase\JWT\JWT;
use Pumukit\CoreBundle\Tests\PumukitTestCase;
use Pumukit\SchemaBundle\Document\Role;
use Pumukit\SchemaBundle\Document\User;
use Pumukit\TeamsBundle\DependencyInjection\PumukitTeamsExtension;
use Pumukit\TeamsBundle\PumukitTeamsBundle;
use Pumukit\TeamsBundle\Services\TokenValidator;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;

abstract class TeamsTestCase extends PumukitTestCase
{
    protected const CLIENT_ID = '11111111-2222-3333-4444-555555555555';
    protected const TENANT = 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee';
    protected const KEY_ID = 'test-key';

    protected KernelBrowser $client;
    protected int $keyRequests = 0;
    private static array $keys = [];
    private array $files = [];

    public function setUp(): void
    {
        self::ensureKernelShutdown();
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->dm = self::getContainer()->get('doctrine_mongodb.odm.document_manager');
        $this->clearBBDD();
        $owner = new Role();
        $owner->setCod('owner');
        $this->dm->persist($owner);
        $this->dm->flush();

        $cache = self::getContainer()->get(PumukitTeamsExtension::CACHE_POOL);
        $cache->clear();
        $jwks = ['keys' => [self::jwk(self::KEY_ID)]];
        $httpClient = new MockHttpClient(function () use ($jwks): MockResponse {
            ++$this->keyRequests;

            return new MockResponse(json_encode($jwks, JSON_THROW_ON_ERROR));
        });
        self::getContainer()->set(TokenValidator::class, new TokenValidator($httpClient, $cache, self::CLIENT_ID, [self::TENANT], 'https://keys.example.com/'));
    }

    public function tearDown(): void
    {
        foreach ($this->files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        parent::tearDown();
    }

    protected function user(string $email = 'Teacher@Example.com', array $roles = [PumukitTeamsBundle::PERMISSION], bool $enabled = true): User
    {
        $user = new User();
        $user->setUsername(strstr($email, '@', true));
        $user->setEmail($email);
        foreach ($roles as $role) {
            $user->addRole($role);
        }
        $user->setEnabled($enabled);
        $this->dm->persist($user);
        $this->dm->flush();

        return $user;
    }

    protected function token(array $claims = [], string $keyId = self::KEY_ID): string
    {
        $now = time();
        $payload = array_filter(array_merge([
            'aud' => 'api://teams.example.com/'.self::CLIENT_ID,
            'iss' => 'https://sts.windows.net/'.self::TENANT.'/',
            'tid' => self::TENANT,
            'scp' => 'access_as_user',
            'upn' => 'teacher@example.com',
            'iat' => $now,
            'nbf' => $now,
            'exp' => $now + 3600,
        ], $claims), static fn ($value): bool => null !== $value);

        return JWT::encode($payload, self::privateKey($keyId), 'RS256', $keyId);
    }

    protected function post(string $uri, ?string $token, array $parameters = [], array $files = []): array
    {
        $server = null === $token ? [] : ['HTTP_AUTHORIZATION' => 'Bearer '.$token];
        $this->client->request('POST', $uri, $parameters, $files, $server);

        return json_decode((string) $this->client->getResponse()->getContent(), true) ?? [];
    }

    protected function recording(string $name = 'recording.mp4'): UploadedFile
    {
        $file = sys_get_temp_dir().'/pumukit-teams-'.bin2hex(random_bytes(4)).'.mp4';
        copy(self::getContainer()->getParameter('kernel.project_dir').'/tests/files/pumukit.mp4', $file);
        $this->files[] = $file;

        return new UploadedFile($file, $name, 'video/mp4', null, true);
    }

    private static function privateKey(string $keyId): string
    {
        if (!isset(self::$keys[$keyId])) {
            $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
            openssl_pkey_export($key, $pem);
            self::$keys[$keyId] = $pem;
        }

        return self::$keys[$keyId];
    }

    private static function jwk(string $keyId): array
    {
        $details = openssl_pkey_get_details(openssl_pkey_get_private(self::privateKey($keyId)));

        return [
            'kty' => 'RSA',
            'use' => 'sig',
            'kid' => $keyId,
            'n' => JWT::urlsafeB64Encode($details['rsa']['n']),
            'e' => JWT::urlsafeB64Encode($details['rsa']['e']),
        ];
    }
}
