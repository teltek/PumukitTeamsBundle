<?php

declare(strict_types=1);

namespace Pumukit\TeamsBundle\Tests\Controller;

use Firebase\JWT\JWT;
use MongoDB\BSON\ObjectId;
use Pumukit\EncoderBundle\Document\Job;
use Pumukit\SchemaBundle\Document\MultimediaObject;
use Pumukit\SchemaBundle\Document\Series;
use Pumukit\SchemaBundle\Services\PersonalSeriesService;
use Pumukit\TeamsBundle\Tests\TeamsTestCase;

/**
 * @internal
 *
 * @coversNothing
 */
class APIControllerTest extends TeamsTestCase
{
    public function testTheRequestsWithoutAValidTokenAreRejected(): void
    {
        $this->user();
        $now = time();
        $tokens = [
            'missing' => null,
            'garbage' => 'a.b.c',
            'expired' => $this->token(['iat' => $now - 7200, 'nbf' => $now - 7200, 'exp' => $now - 3600]),
            'without expiration' => $this->token(['exp' => null]),
            'other tenant' => $this->token(['tid' => 'ffffffff-ffff-ffff-ffff-ffffffffffff', 'iss' => 'https://sts.windows.net/ffffffff-ffff-ffff-ffff-ffffffffffff/']),
            'other issuer' => $this->token(['iss' => 'https://evil.example.com/']),
            'other audience' => $this->token(['aud' => 'https://graph.microsoft.com']),
            'other scope' => $this->token(['scp' => 'User.Read']),
            'unknown key' => $this->token([], 'other-key'),
            'hmac' => JWT::encode(['tid' => self::TENANT, 'exp' => $now + 3600], str_repeat('s', 64), 'HS256', self::KEY_ID),
        ];

        foreach ($tokens as $case => $token) {
            $this->post('/teams/check', $token, ['teamsId' => 'recording']);
            $this->assertResponseStatusCodeSame(401, $case);
            $this->assertResponseHeaderSame('WWW-Authenticate', 'Bearer');

            $this->post('/teams/import', $token, ['teamsId' => 'recording'], ['file' => $this->recording()]);
            $this->assertResponseStatusCodeSame(401, $case);
        }

        $this->assertSame(0, $this->dm->getRepository(MultimediaObject::class)->count());
    }

    public function testOnlyTheEnabledUsersWithThePermissionAreAllowed(): void
    {
        $this->user('nopermission@example.com', []);
        $this->user('disabled@example.com', enabled: false);

        foreach (['unknown@example.com', 'nopermission@example.com', 'disabled@example.com'] as $email) {
            $response = $this->post('/teams/check', $this->token(['upn' => $email]), ['teamsId' => 'recording']);
            $this->assertResponseStatusCodeSame(403, $email);
            $this->assertSame(['error' => 'User not allowed'], $response);
        }
    }

    public function testTheRecordingIsImportedOnceIntoThePersonalSeries(): void
    {
        $user = $this->user();
        $token = $this->token(['upn' => 'TEACHER@example.com']);

        $this->assertSame(['imported' => false], $this->post('/teams/check', $token, ['teamsId' => 'recording-1']));

        $this->assertSame(['imported' => true], $this->post('/teams/import', $token, ['teamsId' => 'recording-1', 'title' => ' Meeting & co '], ['file' => $this->recording()]));
        $this->assertResponseIsSuccessful();

        $this->dm->clear();
        $multimediaObject = $this->dm->getRepository(MultimediaObject::class)->findOneBy(['properties.teamsId' => 'recording-1']);
        $this->assertInstanceOf(MultimediaObject::class, $multimediaObject);
        $this->assertSame(['Meeting & co'], array_values(array_unique($multimediaObject->getI18nTitle())));
        $series = $this->dm->getRepository(Series::class)->find($multimediaObject->getSeries()->getId());
        $this->assertTrue($series->getProperty(PersonalSeriesService::DEFAULT_PERSONAL_SERIES_PROPERTY));
        $this->assertSame($series->getId(), $this->dm->getRepository($user::class)->find($user->getId())->getPersonalSeries());
        $job = $this->dm->getRepository(Job::class)->findOneBy(['mm_id' => $multimediaObject->getId()]);
        $this->assertSame('master_copy', $job->getProfile());

        $this->assertSame(['imported' => true], $this->post('/teams/check', $token, ['teamsId' => 'recording-1']));
        $this->assertSame(['imported' => true], $this->post('/teams/import', $token, ['teamsId' => 'recording-1'], ['file' => $this->recording()]));
        $this->assertResponseStatusCodeSame(409);

        $this->post('/teams/import', $token, ['teamsId' => 'recording-2'], ['file' => $this->recording()]);
        $this->assertResponseIsSuccessful();
        $this->assertSame(2, $this->dm->getRepository(MultimediaObject::class)->createStandardQueryBuilder()->field('series')->equals(new ObjectId($series->getId()))->count()->getQuery()->execute());
        $this->assertSame(1, $this->dm->getRepository(Series::class)->count());
    }

    public function testTheImportValidatesTheRequest(): void
    {
        $this->user();
        $token = $this->token();

        $this->post('/teams/import', $token, ['teamsId' => ''], ['file' => $this->recording()]);
        $this->assertResponseStatusCodeSame(400);
        $this->post('/teams/import', $token, ['teamsId' => 'recording']);
        $this->assertResponseStatusCodeSame(400);
        $this->post('/teams/import', $token, ['teamsId' => 'recording'], ['file' => $this->recording('recording.exe')]);
        $this->assertResponseStatusCodeSame(400);

        $this->assertSame(0, $this->dm->getRepository(MultimediaObject::class)->createStandardQueryBuilder()->field('properties.teamsId')->exists(true)->count()->getQuery()->execute());
    }

    public function testTheVersion2TokensAndTheEmailClaimAreAccepted(): void
    {
        $this->user();

        $this->post('/teams/check', $this->token([
            'aud' => self::CLIENT_ID,
            'iss' => 'https://login.microsoftonline.com/'.self::TENANT.'/v2.0',
            'upn' => null,
            'preferred_username' => 'u123@example.com',
            'email' => 'teacher@example.com',
        ]), ['teamsId' => 'recording']);

        $this->assertResponseIsSuccessful();
    }

    public function testTheKeysAreCachedAndRefreshedOnceForAnUnknownKey(): void
    {
        $this->user();

        $this->post('/teams/check', $this->token(), ['teamsId' => 'recording']);
        $this->post('/teams/check', $this->token(), ['teamsId' => 'recording']);
        $requests = [$this->keyRequests];

        $this->post('/teams/check', $this->token([], 'rotated-key'), ['teamsId' => 'recording']);
        $this->assertResponseStatusCodeSame(401);
        $requests[] = $this->keyRequests;

        $this->post('/teams/check', $this->token([], 'another-key'), ['teamsId' => 'recording']);
        $requests[] = $this->keyRequests;
        $this->assertSame([1, 2, 2], $requests);

        $this->post('/teams/check', $this->token(), ['teamsId' => 'recording']);
        $this->assertResponseIsSuccessful();
    }
}
