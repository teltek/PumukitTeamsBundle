<?php

declare(strict_types=1);

namespace Pumukit\TeamsBundle\Services;

use Doctrine\ODM\MongoDB\DocumentManager;
use MongoDB\BSON\Regex;
use Pumukit\SchemaBundle\Document\User;
use Pumukit\TeamsBundle\PumukitTeamsBundle;
use Symfony\Component\HttpFoundation\Request;

final class TeamsAuthenticator
{
    public function __construct(
        private readonly TokenValidator $tokenValidator,
        private readonly DocumentManager $documentManager,
    ) {}

    public function authenticate(Request $request): User
    {
        if (!preg_match('/^Bearer\s+([\w-]+\.[\w-]+\.[\w-]+)$/', (string) $request->headers->get('Authorization'), $matches)) {
            throw TeamsApiException::unauthorized('Missing Teams token');
        }

        foreach ($this->tokenValidator->emails($matches[1]) as $email) {
            $user = $this->documentManager->getRepository(User::class)->findOneBy(['email' => new Regex('^'.preg_quote($email).'$', 'i')]);
            if (!$user instanceof User) {
                continue;
            }
            if (!$user->isEnabled() || !$user->hasRole(PumukitTeamsBundle::PERMISSION)) {
                throw TeamsApiException::forbidden();
            }

            return $user;
        }

        throw TeamsApiException::forbidden();
    }
}
