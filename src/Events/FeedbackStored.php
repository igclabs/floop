<?php

namespace IgcLabs\Floop\Events;

/**
 * Dispatched after a new work order is written to the pending directory.
 */
class FeedbackStored
{
    public function __construct(
        public readonly string $filename,
        public readonly string $type,
        public readonly string $message,
    ) {}
}
