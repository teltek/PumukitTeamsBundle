<?php

declare(strict_types=1);

namespace Pumukit\TeamsBundle\Controller;

use Pumukit\TeamsBundle\Services\APIService;
use Pumukit\TeamsBundle\Services\SSOService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/teams", methods="POST")
 */
class APIController extends AbstractController
{
    private SSOService $ssoService;
    private APIService $apiService;

    public function __construct(SSOService $ssoService, APIService $APIService)
    {
        $this->ssoService = $ssoService;
        $this->apiService = $APIService;
    }

    /**
     * @Route("/import", name="teams.import")
     */
    public function import(Request $request): Response
    {
        $user = $this->ssoService->isAllowedUser($request->request->get('email'));
        if (!$user) {
            return new JsonResponse('User not allowed', Response::HTTP_FORBIDDEN);
        }

        $teamsId = $request->request->get('teamsId');
        $file = $request->files->get('file');

        $this->apiService->create($user, $teamsId, $file);

        return new JsonResponse('Imported!', Response::HTTP_OK);
    }

    /**
     * @Route("/check", name="teams.check")
     */
    public function wasImported(Request $request): Response
    {
        $user = $this->ssoService->isAllowedUser($request->request->get('email'));
        if (!$user) {
            return new JsonResponse('User not allowed', Response::HTTP_FORBIDDEN);
        }

        $teamsId = $request->request->get('teamsId');

        $wasImported = $this->apiService->find($teamsId);
        if ($wasImported) {
            return new JsonResponse(['imported' => true]);
        }

        return new JsonResponse(['imported' => false]);
    }
}
