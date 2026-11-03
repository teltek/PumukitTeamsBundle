<?php

declare(strict_types=1);

namespace Pumukit\TeamsBundle\Services;

use Symfony\Component\HttpFoundation\Response;

final class TeamsApiException extends \RuntimeException
{
    public function __construct(public readonly int $status, string $message, ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

    public static function unauthorized(string $message, ?\Throwable $previous = null): self
    {
        return new self(Response::HTTP_UNAUTHORIZED, $message, $previous);
    }

    public static function forbidden(): self
    {
        return new self(Response::HTTP_FORBIDDEN, 'User not allowed');
    }

    public static function badRequest(string $message, ?\Throwable $previous = null): self
    {
        return new self(Response::HTTP_BAD_REQUEST, $message, $previous);
    }
}
