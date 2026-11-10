<?php

namespace App\Logging;

use Monolog\Attribute\AsMonologProcessor;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/** Masks passwords, PINs, tokens, cookies and authentication headers in the context of the logs. */
#[AsMonologProcessor]
final class SensitiveDataProcessor implements ProcessorInterface
{
    private const string PATTERN = '/pass(word)?|pin$|secret|token|authorization|cookie|api[_-]?key|card|iban/i';

    public function __invoke(LogRecord $record): LogRecord
    {
        return $record->with(context: self::mask($record->context), extra: self::mask($record->extra));
    }

    /**
     * @param array<mixed> $data
     *
     * @return array<mixed>
     */
    private static function mask(array $data): array
    {
        foreach ($data as $key => $value) {
            if (\is_string($key) && 1 === preg_match(self::PATTERN, $key)) {
                $data[$key] = '***';
            } elseif (\is_array($value)) {
                $data[$key] = self::mask($value);
            }
        }

        return $data;
    }
}
