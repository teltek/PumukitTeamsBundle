<?php

declare(strict_types=1);

namespace Pumukit\TeamsBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class PumukitTeamsBundle extends Bundle
{
    public const PERMISSION = 'ROLE_ACCESS_TEAMS_API';
    public const TEAMS_ID_PROPERTY = 'teamsId';
}
