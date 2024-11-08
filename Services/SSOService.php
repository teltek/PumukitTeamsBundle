<?php

namespace Pumukit\TeamsBundle\Services;

use Doctrine\ODM\MongoDB\DocumentManager;
use Pumukit\SchemaBundle\Document\User;

class SSOService
{
    private DocumentManager $documentManager;

    public function __construct(DocumentManager $documentManager)
    {
        $this->documentManager = $documentManager;
    }

    public function isAllowedUser(string $email): ?User
    {
        $user = $this->documentManager->getRepository(User::class)->findOneBy(['email' => $email]);
        if (!$user instanceof User) {
            return null;
        }

        return $user;
    }

}
