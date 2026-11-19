<?php

namespace Lab;

/**
 * Cheap language guess for post text: Chinese by script, then Malay vs English by common words.
 * Good enough to group posts by language; not a general-purpose detector.
 */
class LanguageGuesser
{
    private const MALAY_WORDS = [
        'saya', 'tidak', 'tak', 'yang', 'dan', 'untuk', 'bila', 'dengan', 'ini', 'itu', 'ke', 'di', 'pada', 'selepas',
        'tolong', 'boleh', 'akaun', 'aplikasi', 'nak', 'ada', 'dalam', 'sangat', 'terlalu', 'guna', 'juga', 'sudah',
        'belum', 'mahu', 'lagi', 'semua', 'setiap', 'masuk', 'keluar', 'baharu', 'tiada', 'tetapi', 'tapi', 'kali',
        'sendiri', 'skrin', 'drama', 'kemas', 'kini', 'muat', 'naik', 'sari', 'kata', 'syiling', 'cari', 'ikut', 'mod',
    ];

    private const ENGLISH_WORDS = [
        'the', 'and', 'i', 'to', 'is', 'it', 'my', 'of', 'in', 'on', 'for', 'but', 'with', 'when', 'this', 'that',
        'please', 'app', 'can', 'not', 'a', 'an', 'me', 'be', 'are', 'was', 'have', 'has', 'after', 'every', 'let',
        'want', 'like', 'so', 'or', 'from', 'all', 'show', 'shows', 'add', 'just', 'what', 'how', 'way', 'only',
    ];

    public static function guess(string $text): string
    {
        if (preg_match_all('/\p{Han}/u', $text) >= 2) {
            return 'zh';
        }

        $malay = array_flip(self::MALAY_WORDS);
        $english = array_flip(self::ENGLISH_WORDS);
        $malayHits = 0;
        $englishHits = 0;

        foreach (preg_split('/[^\p{L}]+/u', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY) as $word) {
            $malayHits += isset($malay[$word]) ? 1 : 0;
            $englishHits += isset($english[$word]) ? 1 : 0;
        }

        return $malayHits > $englishHits ? 'ms' : 'en';
    }
}
