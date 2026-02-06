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
    private const TEAMS_DEFAULT_SERIES_PROPERTY = 'teams.default_series';

    private DocumentManager $documentManager;
    private MultimediaObjectUpdaterService $multimediaObjectUpdaterService;
    private JobCreator $jobCreator;
    private FactoryService $factoryService;

    public function __construct(
        DocumentManager $documentManager,
        MultimediaObjectUpdaterService $multimediaObjectUpdaterService,
        JobCreator $jobCreator,
        FactoryService $factoryService
    ) {
        $this->documentManager = $documentManager;
        $this->multimediaObjectUpdaterService = $multimediaObjectUpdaterService;
        $this->jobCreator = $jobCreator;
        $this->factoryService = $factoryService;
    }

    public function find(string $teamsId): bool
    {
        $multimediaObject = $this->documentManager
            ->getRepository(MultimediaObject::class)
            ->findOneBy(['properties.teamsId' => $teamsId]);

        return $multimediaObject instanceof MultimediaObject;
    }

    public function create(User $user, string $teamsId, UploadedFile $file): void
    {
        $series = $this->getSeriesForTeamsRecording($user);

        $multimediaObject = $this->factoryService->createMultimediaObject($series);
        $this->multimediaObjectUpdaterService->addTeamsProperty($multimediaObject, $teamsId);

        $jobOptions = new JobOptions(self::DEFAULT_PROFILE, 2, 'en', []);
        $this->jobCreator->fromUploadedFile($multimediaObject, $file, $jobOptions);
    }


    private function getSeriesForTeamsRecording(User $user): Series
    {
        $personalSeries = $this->getPersonalSeries($user);

        if ($personalSeries instanceof Series) {
            return $personalSeries;
        }

        return $this->getOrCreateTeamsDefaultSeries();
    }


    private function getPersonalSeries(User $user): ?Series
    {
        if (!$user->getPersonalSeries()) {
            return null;
        }

        $series = $this->documentManager
            ->getRepository(Series::class)
            ->findOneBy(['_id' => new ObjectId($user->getPersonalSeries())]);

        return $series instanceof Series ? $series : null;
    }


    private function getOrCreateTeamsDefaultSeries(): Series
    {
        $criteria = [
            'properties.' . self::TEAMS_DEFAULT_SERIES_PROPERTY => true,
        ];

        $series = $this->documentManager
            ->getRepository(Series::class)
            ->findOneBy($criteria);

        if ($series instanceof Series) {
            return $series;
        }

        return $this->createTeamsDefaultSeries();
    }

    private function createTeamsDefaultSeries(): Series
    {
        $series = $this->factoryService->createSeries(
            null,
            $this->getTeamsDefaultSeriesTitle()
        );

        $series->setProperty(self::TEAMS_DEFAULT_SERIES_PROPERTY, true);

        $this->documentManager->flush();

        return $series;
    }

    private function getTeamsDefaultSeriesTitle(): array
    {
        return [
            'en' => 'Teams Recordings',
            'es' => 'Grabaciones de Teams',
            'ca' => 'Gravacions de Teams',
            'gl' => 'Gravacións de Teams',
            'fr' => 'Enregistrements Teams',
            'de' => 'Teams-Aufzeichnungen',
            'it' => 'Registrazioni di Teams',
            'pt' => 'Gravações do Teams',
        ];
    }
}

