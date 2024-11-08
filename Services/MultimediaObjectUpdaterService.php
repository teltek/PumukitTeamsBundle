<?php

namespace Pumukit\TeamsBundle\Services;

use Doctrine\ODM\MongoDB\DocumentManager;
use Pumukit\SchemaBundle\Document\MultimediaObject;

class MultimediaObjectUpdaterService
{
    private DocumentManager $documentManager;

    public function __construct(DocumentManager $documentManager)
    {
        $this->documentManager = $documentManager;
    }

    public function addTeamsProperty(MultimediaObject $multimediaObject, string $teamsId): void
    {
        $multimediaObject->setProperty('teamsId', $teamsId);
        $this->documentManager->flush();
    }
}
