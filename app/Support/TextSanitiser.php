<?php

namespace App\Support;

/**
 * Heuristic UTF-8 mojibake repair.
 *
 * Some upstream APIs hand us strings that are already double-encoded
 * (UTF-8 bytes interpreted as Latin-1 / Windows-1252 and re-encoded as
 * UTF-8). Result: "peça" → "peÃ§a". This class detects and unwinds the
 * round-trip when the giveaway markers are present, but leaves clean
 * strings alone.
 */
class TextSanitiser
{
    private const SUSPECT_PATTERNS = [
        // Common pairs that appear when UTF-8 was decoded as Latin-1.
        'Ã¡', 'Ã©', 'Ã­', 'Ã³', 'Ãº',
        'Ã ', 'Ã¨', 'Ã¬', 'Ã²', 'Ã¹',
        'Ã¢', 'Ãª', 'Ã®', 'Ã´', 'Ã»',
        'Ã£', 'Ãµ', 'Ã±',
        'Ã§', 'Ã‡',
        'Ã„', 'Ã‹', 'Ã', 'Ã–', 'Ãœ',
        'â€™', 'â€œ', 'â€', 'â€“', 'â€”', 'â€¦',
    ];

    public static function fix(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        if (! self::looksMojibake($value)) {
            return $value;
        }

        // Try the standard Latin-1 → UTF-8 round-trip undo.
        $decoded = @iconv('UTF-8', 'ISO-8859-1//IGNORE', $value);
        if ($decoded !== false && mb_check_encoding($decoded, 'UTF-8')) {
            // If decoding cleared the markers, return it.
            if (! self::looksMojibake($decoded)) {
                return $decoded;
            }
        }

        // Fallback: try Windows-1252 (covers smart quotes/em-dashes).
        $decoded = @mb_convert_encoding($value, 'ISO-8859-1', 'UTF-8');
        if ($decoded !== false && ! self::looksMojibake($decoded)) {
            return $decoded;
        }

        return $value;
    }

    private static function looksMojibake(string $value): bool
    {
        foreach (self::SUSPECT_PATTERNS as $needle) {
            if (str_contains($value, $needle)) {
                return true;
            }
        }

        return false;
    }
}
