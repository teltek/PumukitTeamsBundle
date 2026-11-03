<?php

declare(strict_types=1);

namespace Pumukit\TeamsBundle\Services;

use Doctrine\ODM\MongoDB\DocumentManager;
use MongoDB\BSON\ObjectId;
use Psr\Log\LoggerInterface;
use Pumukit\CoreBundle\Services\i18nService;
use Pumukit\EncoderBundle\Services\DTO\JobOptions;
use Pumukit\EncoderBundle\Services\JobCreator;
use Pumukit\SchemaBundle\Document\MultimediaObject;
use Pumukit\SchemaBundle\Document\Series;
use Pumukit\SchemaBundle\Document\User;
use Pumukit\SchemaBundle\Services\FactoryService;
use Pumukit\SchemaBundle\Services\PersonalSeriesService;
use Pumukit\TeamsBundle\PumukitTeamsBundle;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Contracts\Translation\TranslatorInterface;

final class ImportService
{
    public function __construct(
        private readonly DocumentManager $documentManager,
        private readonly FactoryService $factoryService,
        private readonly JobCreator $jobCreator,
        private readonly i18nService $i18nService,
        private readonly TranslatorInterface $translator,
        private readonly LoggerInterface $logger,
        private readonly string $profile,
        private readonly int $priority,
        private readonly string $language,
        private readonly array $locales,
    ) {}

    public function isImported(string $teamsId): bool
    {
        return null !== $this->documentManager->getRepository(MultimediaObject::class)->findOneBy([
            'properties.'.PumukitTeamsBundle::TEAMS_ID_PROPERTY => $teamsId,
        ]);
    }

    public function import(User $user, string $teamsId, UploadedFile $file, ?string $title = null): MultimediaObject
    {
        $multimediaObject = $this->factoryService->createMultimediaObject($this->personalSeries($user), true, $user);
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

    private function personalSeries(User $user): Series
    {
        if ($user->getPersonalSeries() && preg_match('/^[a-f\d]{24}$/i', (string) $user->getPersonalSeries())) {
            $series = $this->documentManager->getRepository(Series::class)->find(new ObjectId($user->getPersonalSeries()));
            if ($series instanceof Series) {
                return $series;
            }
        }

        $title = [];
        foreach ($this->locales as $locale) {
            $title[$locale] = $this->translator->trans('Videos of ', [], null, $locale).$user->getUsername();
        }

        $series = $this->factoryService->createSeries($user, $title);
        $series->setProperty(PersonalSeriesService::DEFAULT_PERSONAL_SERIES_PROPERTY, true);
        $user->setPersonalSeries($series->getId());
        $this->documentManager->flush();

        return $series;
    }
}
