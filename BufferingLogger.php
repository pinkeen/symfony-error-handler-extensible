<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ErrorHandler;

use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;

/**
 * A buffering logger that stacks logs for later.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class BufferingLogger extends AbstractLogger implements BufferingLoggerInterface
{
    protected $logs = [];
    protected $channel = null;

    protected const LEVEL_PRIORITY_MAPPING = [
        LogLevel::DEBUG     => 100,
        LogLevel::INFO      => 200,
        LogLevel::NOTICE    => 250,
        LogLevel::WARNING   => 300,
        LogLevel::ERROR     => 400,
        LogLevel::CRITICAL  => 500,
        LogLevel::ALERT     => 550,
        LogLevel::EMERGENCY => 600,
    ];

    public function __construct(string $channel = null)
    {
        $this->channel = $channel;
    }

    public static function createFromArray(array $logs = [], string $channel = null): self
    {
        $instance = new static($channel);
        $instance->logs = $logs;

        return $instance;
    }

    public function log($level, $message, array $context = []): void
    {
        $this->logs[] = [
            'level' => $level,
            'message' => $message ?: '',
            'context' => $context,
            'timestamp' => time(),
        ];
    }

    public function cleanLogs(): array
    {
        $logs = $this->logs;
        $this->logs = [];

        return $logs;
    }

    public function getLogs(bool $extended = false): array
    {
        if ($extended) {
            return array_map(
                function(array $log) use ($extended) : array {
                    $channel = $log['channel'] 
                        ?? $log['context']['channel'] 
                        ?? $this->channel;
                    $priority = $log['priority'] 
                        ?? $log['context']['priority'] 
                        ?? self::LEVEL_PRIORITY_MAPPING[$log['level']] 
                        ?? 0;

                    unset($log['context']['channel']);
                    unset($log['context']['priority']);

                    return array_merge($log, [
                        'channel'       => $channel,
                        'priority'      => $priority,
                        'priorityName'  => $log['priorityName'] ?? $log['level'],
                    ]);
                },
                $this->logs
            );
        }

        return $this->logs;
    }


    /**
     * @param string|int|null $above
     * @return int
     */
    public function count($above = null): int
    {
        if (null === $above) {
            return count($this->logs);
        }

        $above = is_string($above)
            ? (self::LEVEL_PRIORITY_MAPPING[$above] ?? 0)
            : $above;

        return array_reduce(
            $this->getLogs(true),
            function(int $carry, array $log) use ($above) : int {
                return $carry + ($log['priority'] >= $above ? 1 : 0);
            },
            0
        );
    }

    public function __destruct()
    {
        foreach ($this->getLogs(true) as ['level' => $level, 'message' => $message, 'context' => $context, 'channel' => $channel]) {
            if (false !== strpos($message, '{')) {
                foreach ($context as $key => $val) {
                    if (null === $val || is_scalar($val) || (\is_object($val) && \is_callable([$val, '__toString']))) {
                        $message = str_replace("{{$key}}", $val, $message);
                    } elseif ($val instanceof \DateTimeInterface) {
                        $message = str_replace("{{$key}}", $val->format(\DateTime::RFC3339), $message);
                    } elseif (\is_object($val)) {
                        $message = str_replace("{{$key}}", '[object '.\get_class($val).']', $message);
                    } else {
                        $message = str_replace("{{$key}}", '['.\gettype($val).']', $message);
                    }
                }
            }

            error_log(sprintf('%s [%s%s] %s',
                date(\DateTime::RFC3339, $log['timestamp'] ?? time()),
                $channel ? $channel . '.' : '',
                $level,
                $message
            ));
        }
    }
}
