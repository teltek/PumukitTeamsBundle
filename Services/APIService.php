<?php

declare(strict_types=1);

namespace Pumukit\TeamsBundle\Services;

use Doctrine\ODM\MongoDB\DocumentManager;
use MongoDB\BSON\ObjectId;
use Pumukit\EncoderBundle\Services\DTO\JobOptions;
use Pumukit\EncoderBundle\Services\JobCreator;
use Pumukit\SchemaBundle\Document\MultimediaObject;
use Pumukit\SchemaBundle\Document\Series;
use Pumukit\SchemaBundle\Document\User;
use Pumukit\SchemaBundle\Services\FactoryService;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class APIService
{
    public const DEFAULT_PROFILE = 'master_copy';

    private $documentManager;
    private $multimediaObjectUpdaterService;
    private $jobCreator;
    private $factoryService;

    public function __construct(
        DocumentManager $documentManager,
        MultimediaObjectUpdaterService $multimediaObjectUpdaterService,
        JobCreator $jobCreator,
        FactoryService $factoryService
    )
    {
        $this->documentManager = $documentManager;
        $this->multimediaObjectUpdaterService = $multimediaObjectUpdaterService;
        $this->jobCreator = $jobCreator;
        $this->factoryService = $factoryService;
    }

    public function find(string $teamsId): bool
    {
        $multimediaObject = $this->documentManager->getRepository(MultimediaObject::class)->findBy([
            'properties.teamsId' => $teamsId
        ]);

        if(!$multimediaObject instanceof MultimediaObject) {
            return false;
        }

        return true;
    }

    public function create(User $user, string $teamsId, UploadedFile $file): void
    {
        $userSeries = $user->getPersonalSeries();
        $series = $this->documentManager->getRepository(Series::class)->findOneBy(['_id' => new ObjectId($userSeries)]);

        $multimediaObject = $this->factoryService->createMultimediaObject($series);
        $this->multimediaObjectUpdaterService->addTeamsProperty($multimediaObject, $teamsId);

        $jobOptions = new JobOptions(self::DEFAULT_PROFILE, 2, 'en', []);
        $this->jobCreator->fromUploadedFile($multimediaObject, $file, $jobOptions);
    }
}
