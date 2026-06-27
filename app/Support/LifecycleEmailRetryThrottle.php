<?php

namespace App\Support;

class LifecycleEmailRetryThrottle
{
    public const MAX_REQUESTS_PER_SECOND = 1;

    public const INTERVAL_MICROSECONDS = 1000000;

    /**
     * @var callable(int): void
     */
    private $sleeper;

    public function __construct(?callable $sleeper = null)
    {
        $this->sleeper = $sleeper ?? static function (int $microseconds): void {
            usleep($microseconds);
        };
    }

    public function pauseBeforeSend(int $sendAttempt): void
    {
        if ($sendAttempt <= 0) {
            return;
        }

        ($this->sleeper)(self::INTERVAL_MICROSECONDS);
    }
}
