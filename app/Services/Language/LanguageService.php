<?php

namespace App\Services\Language;

class LanguageService
{
    /**
     * Get the languages.
     *
     * @return array
     */
    public static function getLanguages(): array
    {
        return [
            [
                'name' => 'English',
                'code' => 'en'
            ]
        ];
    }

    /**
     * Find language matching given the language code.
     *
     * @param string $code - "EN" for the english language.
     * @return array|null
     */
    public static function findLanguageByCode(string $code): array|null
    {
        return collect(self::getLanguages())->firstWhere('code', $code);
    }
}
