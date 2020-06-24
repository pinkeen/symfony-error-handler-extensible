<?php

namespace Symfony\Component\ErrorHandler;

/**
 * A buffering logger that stacks logs for later.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
interface BufferingLoggerInterface
{
    public function log($level, $message, array $context = []): void;

    public function cleanLogs(): array;

    public function getLogs(): array;

    public function count($above = null): int;
}