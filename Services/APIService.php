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
use Pumukit\SchemaBundle\Services\PersonalSeriesService;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class APIService
{
    public const DEFAULT_PROFILE = 'master_copy';

    private DocumentManager $documentManager;
    private MultimediaObjectUpdaterService $multimediaObjectUpdaterService;
    private JobCreator $jobCreator;
    private FactoryService $factoryService;
    private PersonalSeriesService $personalSeriesService;

    public function __construct(
        DocumentManager $documentManager,
        MultimediaObjectUpdaterService $multimediaObjectUpdaterService,
        JobCreator $jobCreator,
        FactoryService $factoryService,
        PersonalSeriesService $personalSeriesService
    ) {
        $this->documentManager = $documentManager;
        $this->multimediaObjectUpdaterService = $multimediaObjectUpdaterService;
        $this->jobCreator = $jobCreator;
        $this->factoryService = $factoryService;
        $this->personalSeriesService = $personalSeriesService;
    }

    public function find(string $teamsId): bool
    {
        $multimediaObject = $this->documentManager
            ->getRepository(MultimediaObject::class)
            ->findOneBy(['properties.teamsId' => $teamsId]);

        return $multimediaObject instanceof MultimediaObject;
    }

    public function create(string $email, string $teamsId, UploadedFile $file): void
    {
        $user = $this->findUserByEmail($email);

        if (!$user) {
            throw new \RuntimeException(sprintf(
                'User with email "%s" not found in PuMuKIT.',
                $email
            ));
        }

        $series = $this->resolveSeriesForUser($user);

        $multimediaObject = $this->factoryService->createMultimediaObject($series);
        $this->multimediaObjectUpdaterService->addTeamsProperty($multimediaObject, $teamsId);

        $jobOptions = new JobOptions(self::DEFAULT_PROFILE, 2, 'en', []);
        $this->jobCreator->fromUploadedFile($multimediaObject, $file, $jobOptions);
    }

    private function findUserByEmail(string $email): ?User
    {
        $user = $this->documentManager
            ->getRepository(User::class)
            ->findOneBy(['email' => $email]);

        return $user instanceof User ? $user : null;
    }

    private function resolveSeriesForUser(User $user): Series
    {
        if ($user->getPersonalSeries()) {
            $series = $this->documentManager
                ->getRepository(Series::class)
                ->findOneBy(['_id' => new ObjectId($user->getPersonalSeries())]);

            if ($series instanceof Series) {
                return $series;
            }
        }

        // Create personal series if missing
        return $this->personalSeriesService->createFromUser($user);
    }
}