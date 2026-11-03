<?php

declare(strict_types=1);

namespace Pumukit\TeamsBundle\Services;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class TokenValidator
{
    public const SCOPE = 'access_as_user';
    private const KEYS = 'pumukit_teams_jwks';
    private const REFRESHED = 'pumukit_teams_jwks_refreshed';
    private const KEYS_TTL = 86400;
    private const REFRESH_INTERVAL = 300;
    private const LEEWAY = 120;
    private const EMAIL_CLAIMS = ['email', 'preferred_username', 'upn'];

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly CacheInterface $cache,
        private readonly string $clientId,
        private readonly array $tenants,
        private readonly string $jwksUri,
    ) {}

    public function emails(string $token): array
    {
        $claims = $this->claims($token);

        $tenant = is_string($claims['tid'] ?? null) ? strtolower($claims['tid']) : '';
        if (!in_array($tenant, array_map('strtolower', $this->tenants), true)) {
            throw TeamsApiException::unauthorized('Tenant not allowed');
        }

        $issuers = ['https://login.microsoftonline.com/'.$tenant.'/v2.0', 'https://sts.windows.net/'.$tenant.'/'];
        if (!in_array(strtolower((string) ($claims['iss'] ?? '')), $issuers, true)) {
            throw TeamsApiException::unauthorized('Invalid issuer');
        }

        $audience = (string) ($claims['aud'] ?? '');
        if ($audience !== $this->clientId && !preg_match('#^api://[^/]+/'.preg_quote($this->clientId, '#').'$#i', $audience)) {
            throw TeamsApiException::unauthorized('Invalid audience');
        }

        if (!in_array(self::SCOPE, explode(' ', (string) ($claims['scp'] ?? '')), true)) {
            throw TeamsApiException::unauthorized('Invalid scope');
        }

        $emails = [];
        foreach (self::EMAIL_CLAIMS as $claim) {
            $email = $claims[$claim] ?? null;
            if (is_string($email) && false !== filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $emails[] = strtolower($email);
            }
        }

        return array_values(array_unique($emails));
    }

    private function claims(string $token): array
    {
        $keyId = $this->keyId($token);
        $keys = $this->keys();
        if (!isset($keys[$keyId])) {
            $this->cache->get(self::REFRESHED, function (ItemInterface $item): int {
                $item->expiresAfter(self::REFRESH_INTERVAL);
                $this->cache->delete(self::KEYS);

                return time();
            });
            $keys = $this->keys();
        }
        if (!isset($keys[$keyId])) {
            throw TeamsApiException::unauthorized('Unknown signing key');
        }

        JWT::$leeway = self::LEEWAY;

        try {
            $claims = json_decode(json_encode(JWT::decode($token, $keys), JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
        } catch (\DomainException|\InvalidArgumentException|\JsonException|\UnexpectedValueException $exception) {
            throw TeamsApiException::unauthorized('Invalid token', $exception);
        }

        if (!is_int($claims['exp'] ?? null)) {
            throw TeamsApiException::unauthorized('Invalid token');
        }

        return $claims;
    }

    private function keyId(string $token): string
    {
        $header = json_decode((string) JWT::urlsafeB64Decode(explode('.', $token)[0]), true);
        if (!is_array($header) || !is_string($header['kid'] ?? null) || 'RS256' !== ($header['alg'] ?? null)) {
            throw TeamsApiException::unauthorized('Invalid token');
        }

        return $header['kid'];
    }

    private function keys(): array
    {
        try {
            $jwks = $this->cache->get(self::KEYS, function (ItemInterface $item): array {
                $item->expiresAfter(self::KEYS_TTL);

                return $this->httpClient->request('GET', $this->jwksUri, ['timeout' => 10])->toArray();
            });

            return JWK::parseKeySet($jwks, 'RS256');
        } catch (\DomainException|ExceptionInterface|\InvalidArgumentException|\UnexpectedValueException $exception) {
            throw new TeamsApiException(Response::HTTP_SERVICE_UNAVAILABLE, 'Signing keys not available', $exception);
        }
    }
}
