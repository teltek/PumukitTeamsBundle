<?php

declare(strict_types=1);

namespace Pumukit\TeamsBundle\Services;

use Doctrine\ODM\MongoDB\DocumentManager;
use Psr\Log\LoggerInterface;
use Pumukit\CoreBundle\Services\i18nService;
use Pumukit\EncoderBundle\Services\DTO\JobOptions;
use Pumukit\EncoderBundle\Services\JobCreator;
use Pumukit\SchemaBundle\Document\MultimediaObject;
use Pumukit\SchemaBundle\Document\User;
use Pumukit\SchemaBundle\Services\FactoryService;
use Pumukit\SchemaBundle\Services\PersonalSeriesService;
use Pumukit\TeamsBundle\PumukitTeamsBundle;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class ImportService
{
    public function __construct(
        private readonly DocumentManager $documentManager,
        private readonly FactoryService $factoryService,
        private readonly PersonalSeriesService $personalSeriesService,
        private readonly JobCreator $jobCreator,
        private readonly i18nService $i18nService,
        private readonly LoggerInterface $logger,
        private readonly string $profile,
        private readonly int $priority,
        private readonly string $language,
    ) {}

    public function isImported(string $teamsId): bool
    {
        return null !== $this->documentManager->getRepository(MultimediaObject::class)->findOneBy([
            'properties.'.PumukitTeamsBundle::TEAMS_ID_PROPERTY => $teamsId,
        ]);
    }

    public function import(User $user, string $teamsId, UploadedFile $file, ?string $title = null): MultimediaObject
    {
        $multimediaObject = $this->factoryService->createMultimediaObject($this->personalSeriesService->getOrCreate($user), true, $user);
        $title = trim((string) $title);
        if ('' !== $title) {
            $multimediaObject->setI18nTitle($this->i18nService->generateI18nText($title));
        }
        $multimediaObject->setProperty(PumukitTeamsBundle::TEAMS_ID_PROPERTY, $teamsId);
        $this->documentManager->flush();

        try {
            $this->jobCreator->fromUploadedFile($multimediaObject, $file, new JobOptions($this->profile, $this->priority, $this->language, []));
        } catch (\Exception $exception) {
            $this->logger->warning('Teams recording not imported', ['teamsId' => $teamsId, 'user' => $user->getUsername(), 'exception' => $exception]);
            $this->factoryService->deleteMultimediaObject($multimediaObject);

            throw TeamsApiException::badRequest('Invalid recording', $exception);
        }

        return $multimediaObject;
    }
}
