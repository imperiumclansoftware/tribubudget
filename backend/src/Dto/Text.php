<?php

namespace App\Dto;

final class Text
{
    /** Trimmed, with runs of spaces collapsed */
    public static function clean(string $text): string
    {
        return trim(preg_replace('/\s+/u', ' ', $text));
    }
}
