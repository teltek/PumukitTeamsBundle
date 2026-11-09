<?php

declare(strict_types=1);

namespace Pumukit\TeamsBundle\Controller;

use Pumukit\TeamsBundle\Services\ImportService;
use Pumukit\TeamsBundle\Services\TeamsApiException;
use Pumukit\TeamsBundle\Services\TeamsAuthenticator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/teams', methods: ['POST'])]
class APIController extends AbstractController
{
    public function __construct(
        private readonly TeamsAuthenticator $authenticator,
        private readonly ImportService $importService,
    ) {}

    #[Route('/import', name: 'teams.import')]
    public function import(Request $request): Response
    {
        try {
            $user = $this->authenticator->authenticate($request);
            $teamsId = $this->teamsId($request);
            $file = $request->files->get('file');
            if (!$file instanceof UploadedFile || !$file->isValid()) {
                throw TeamsApiException::badRequest('Missing recording');
            }
            if ($this->importService->isImported($teamsId)) {
                return new JsonResponse(['imported' => true], Response::HTTP_CONFLICT);
            }

            $this->importService->import($user, $teamsId, $file, $request->request->getString('title'));
        } catch (TeamsApiException $exception) {
            return $this->error($exception);
        }

        return new JsonResponse(['imported' => true]);
    }

    #[Route('/check', name: 'teams.check')]
    public function wasImported(Request $request): Response
    {
        try {
            $this->authenticator->authenticate($request);

            return new JsonResponse(['imported' => $this->importService->isImported($this->teamsId($request))]);
        } catch (TeamsApiException $exception) {
            return $this->error($exception);
        }
    }

    private function teamsId(Request $request): string
    {
        $teamsId = trim($request->request->getString('teamsId'));
        if ('' === $teamsId || strlen($teamsId) > 1024) {
            throw TeamsApiException::badRequest('Missing teamsId');
        }

        return $teamsId;
    }

    private function error(TeamsApiException $exception): JsonResponse
    {
        $headers = Response::HTTP_UNAUTHORIZED === $exception->status ? ['WWW-Authenticate' => 'Bearer'] : [];

        return new JsonResponse(['error' => $exception->getMessage()], $exception->status, $headers);
    }
}
