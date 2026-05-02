<?php

declare(strict_types=1);

namespace App\Helper;

/**
 * Extracts the best available English flavor text from PokéAPI species data,
 * applying a multi-step fallback strategy to avoid hardware-era text artifacts.
 */
class FlavorTextExtractor
{
    /**
     * Version priority list, from most recent generation to oldest.
     * We prefer recent versions as they have more natural wording
     * and the Pokémon name in its current form.
     */
    private const VERSION_PRIORITY = [
        ['scarlet', 'violet'],
        ['sword', 'shield'],
        ['sun', 'moon'],
        ['omega-ruby', 'alpha-sapphire'],
        ['x', 'y'],
        ['black-2', 'white-2'],
        ['black', 'white'],
        ['heartgold', 'soulsilver'],
        ['platinum', 'diamond', 'pearl'],
        ['firered', 'leafgreen'],
        ['ruby', 'sapphire'],
        ['crystal', 'gold', 'silver'],
        ['yellow', 'red', 'blue'],
    ];


    /**
     * Extract the best available English flavor text from the PokéAPI entries.
     * Returns null if no English entries are found.
     *
     * Selection strategy (degrading fallback chain):
     *   1. Most recent version with clean text (Pokémon name not in ALL CAPS)
     *   2. Any version with clean text, regardless of game version
     *   3. Any version without hard artifacts (\f, soft hyphen) — \n is sanitized
     *   4. Last resort: first English entry, fully sanitized
     */
    public function extract(array $flavorTextEntries, string $pokemonName): ?string
    {
        $englishEntries = $this->filterEnglish($flavorTextEntries);
        if (empty($englishEntries)) {
            return null;
        }

        $text = $this->findByPriorityAndClean($englishEntries, $pokemonName);
        if ($text !== null) {
            return $text;
        }

        $text = $this->findClean($englishEntries, $pokemonName);
        if ($text !== null) {
            return $text;
        }

        $text = $this->findWithoutHardArtifacts($englishEntries);
        if ($text !== null) {
            return $text;
        }

        return $this->lastResort($englishEntries);
    }

    /**
     * Filters the given flavor text entries to English-language entries only.
     *
     * @param array $entries
     * @return array
     */
    private function filterEnglish(array $entries): array
    {
        return array_values(array_filter(
            $entries,
            fn($entry) => ($entry['language']['name'] ?? '') === 'en'
        ));
    }

    /**
     * Find the most recent version entry that passes the clean text check.
     */
    private function findByPriorityAndClean(array $entries, string $pokemonName): ?string
    {
        $indexed = [];
        foreach ($entries as $entry) {
            $indexed[$entry['version']['name'] ?? ''][] = $entry;
        }

        foreach (self::VERSION_PRIORITY as $generation) {
            foreach ($generation as $version) {
                foreach ($indexed[$version] ?? [] as $entry) {
                    $sanitized = $this->sanitize($entry['flavor_text']);
                    if ($this->isClean($sanitized, $pokemonName)) {
                        return $sanitized;
                    }
                }
            }
        }
        return null;
    }

    /**
     * Find any English entry with clean text, regardless of version.
     */
    private function findClean(array $entries, string $pokemonName): ?string
    {
        foreach ($entries as $entry) {
            $sanitized = $this->sanitize($entry['flavor_text']);
            if ($this->isClean($sanitized, $pokemonName)) {
                return $sanitized;
            }
        }
        return null;
    }

    /**
     * Find any English entry without hard artifacts (\f and soft hyphen).
     * Newlines are acceptable at this stage and will be sanitized.
     */
    private function findWithoutHardArtifacts(array $entries): ?string
    {
        foreach ($entries as $entry) {
            $text = $entry['flavor_text'];
            if (!str_contains($text, "\f") && !str_contains($text, "\xC2\xAD")) {
                return $this->sanitize($text);
            }
        }
        return null;
    }

    /**
     * Last resort: return the first available English entry, fully sanitized.
     * Should never be reached in practice for real Pokémon data.
     */
    private function lastResort(array $entries): ?string
    {
        return $this->sanitize($entries[0]['flavor_text']);
    }

    /**
     * A text is considered clean if it does not contain the Pokémon name in ALL CAPS.
     * ALL CAPS names are artifacts from old game hardware character sets
     * (e.g. MEWTWO, GENGAR in Gen I/II entries).
     * Hyphenated names (e.g. mr-mime) are normalized to spaces before comparison.
     */
    private function isClean(string $text, string $pokemonName): bool
    {
        $normalizedName = strtoupper(str_replace('-', ' ', $pokemonName));
        return !str_contains($text, $normalizedName);
    }

    /**
     * Sanitize raw flavor text:
     * - Remove form feed (\f) and soft hyphen characters (hardware artifacts)
     * - Normalize all newline variants to spaces
     * - Collapse multiple spaces into one
     */
    private function sanitize(string $text): string
    {
        $text = str_replace(["\f", "\xC2\xAD"], ' ', $text);
        $text = str_replace(["\r\n", "\r", "\n"], ' ', $text);
        $text = preg_replace('/\s+/', ' ', $text);
        return trim($text);
    }
}