<?php

namespace IgcLabs\Floop\Events;

/**
 * Dispatched after a work order is moved from pending to actioned.
 */
class FeedbackActioned
{
    public function __construct(
        public readonly string $filename,
    ) {}
}
