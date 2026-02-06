<?php

namespace Akhelij\LarabaseNotification\Exceptions;

use RuntimeException;

class PayloadTooLargeException extends RuntimeException
{
    public function __construct(int $size, int $limit = 4096)
    {
        parent::__construct("FCM payload size ({$size} bytes) exceeds the {$limit}-byte limit.");
    }
}
