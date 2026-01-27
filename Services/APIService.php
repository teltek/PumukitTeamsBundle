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

    private $documentManager;
    private $multimediaObjectUpdaterService;
    private $jobCreator;
    private $factoryService;

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
        $multimediaObject = $this->documentManager->getRepository(MultimediaObject::class)->findOneBy([
            'properties.teamsId' => $teamsId,
        ]);

        if (!$multimediaObject instanceof MultimediaObject) {
            return false;
        }

        return true;
    }

    public function create(User $user, string $teamsId, UploadedFile $file): void
    {
        $series = $this->getOrCreatePersonalSeriesForApi($user);

        $multimediaObject = $this->factoryService->createMultimediaObject($series);
        $this->multimediaObjectUpdaterService->addTeamsProperty($multimediaObject, $teamsId);

        $jobOptions = new JobOptions(self::DEFAULT_PROFILE, 2, 'en', []);
        $this->jobCreator->fromUploadedFile($multimediaObject, $file, $jobOptions);
    }

    private function getOrCreatePersonalSeriesForApi(User $user): Series
    {
        if ($user->getPersonalSeries()) {
            $series = $this->documentManager
                ->getRepository(Series::class)
                ->findOneBy(['_id' => new ObjectId($user->getPersonalSeries())])
            ;

            if ($series instanceof Series) {
                return $series;
            }
        }

        return $this->createPersonalSeriesForApi($user);
    }

    private function createPersonalSeriesForApi(User $user): Series
    {
        $series = $this->factoryService->createSeries(
            $user,
            $this->generateDefaultPersonalSeriesTitleForApi($user)
        );

        $series->setProperty(PersonalSeriesService::DEFAULT_PERSONAL_SERIES_PROPERTY, true);

        $user->setPersonalSeries($series->getId());
        $this->documentManager->flush();

        return $series;
    }

    private function generateDefaultPersonalSeriesTitleForApi(User $user): array
    {
        return [
            'en' => 'Videos of '.$user->getUsername(),
            'es' => 'Vídeos de '.$user->getUsername(),
            'ca' => 'Vídeos de '.$user->getUsername(),
            'eu' => $user->getUsername().'-ren bideoak',
            'gl' => 'Vídeos de '.$user->getUsername(),
            'fr' => 'Vidéos de '.$user->getUsername(),
            'de' => 'Videos von '.$user->getUsername(),
            'it' => 'Video di '.$user->getUsername(),
            'pt' => 'Vídeos de '.$user->getUsername(),
            'va' => 'Vídeos de '.$user->getUsername(),
            'ru' => 'Видео пользователя '.$user->getUsername(),
            'zh' => $user->getUsername().'的影片',
            'ja' => $user->getUsername().'のビデオ',
            'ko' => $user->getUsername().'님의 동영상',
        ];
    }
}
