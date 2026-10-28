<?php

namespace App\Setup;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * One-time code protecting the installation page: only someone with access to the server
 * (container logs, or the app:setup:code command) creates the family, not whoever reaches the
 * site first.
 */
final readonly class SetupCode
{
    /** No 0/O or 1/I: the code is read on a screen and typed by hand */
    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    private const LENGTH = 8;

    public function __construct(
        #[Autowire('%kernel.project_dir%/var/setup/%kernel.environment%.code')]
        private string $file,
        private LoggerInterface $logger,
    ) {
    }

    /** The current code, created on first use ("ABCD-EFGH"). */
    public function current(): string
    {
        if (is_file($this->file) && 1 === preg_match('/^[A-Z0-9]{'.self::LENGTH.'}$/', $code = trim((string) file_get_contents($this->file)))) {
            return self::format($code);
        }

        $code = '';
        for ($i = 0; $i < self::LENGTH; ++$i) {
            $code .= self::ALPHABET[random_int(0, \strlen(self::ALPHABET) - 1)];
        }
        if (!is_dir(\dirname($this->file))) {
            mkdir(\dirname($this->file), 0o770, true);
        }
        file_put_contents($this->file, $code);
        chmod($this->file, 0o660);
        $this->logger->notice('Installation code of TribuBudget: {code} (also given by "bin/console app:setup:code").', ['code' => self::format($code)]);

        return self::format($code);
    }

    /** Case, spaces and dashes do not matter. */
    public function matches(string $candidate): bool
    {
        $normalized = strtoupper((string) preg_replace('/[\s-]+/', '', $candidate));

        return '' !== $normalized && hash_equals(str_replace('-', '', $this->current()), $normalized);
    }

    /** Once installed, the code is useless. */
    public function clear(): void
    {
        if (is_file($this->file)) {
            unlink($this->file);
        }
    }

    private static function format(string $code): string
    {
        return substr($code, 0, 4).'-'.substr($code, 4);
    }
}
