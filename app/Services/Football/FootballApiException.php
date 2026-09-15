<?php

namespace App\Services\Football;

use RuntimeException;

final class FootballApiException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly ?int $httpStatus = null,
        private readonly ?int $retryAfter = null
    ) {
        parent::__construct($message, $httpStatus ?? 0);
    }

    public function httpStatus(): ?int
    {
        return $this->httpStatus;
    }

    public function retryAfter(): ?int
    {
        return $this->retryAfter;
    }

    public function isRateLimited(): bool
    {
        return $this->httpStatus === 429;
    }
}
