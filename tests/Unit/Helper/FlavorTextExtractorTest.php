<?php

declare(strict_types=1);

namespace Tests\Unit\Helper;

use App\Helper\FlavorTextExtractor;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for FlavorTextExtractor.
 */
class FlavorTextExtractorTest extends TestCase
{
    private FlavorTextExtractor $extractor;

    /**
     * Instantiates the extractor under test.
     */
    protected function setUp(): void
    {
        $this->extractor = new FlavorTextExtractor();
    }

    /**
     * Provides flavor text entry sets with the expected extracted string for each selection scenario.
     *
     * @return array<string, array{pokemonName: string, entries: array, expected: string|null}>
     */
    public static function flavorTextExtractionProvider(): array
    {
        return [
            'prefers most recent clean version over older ALL CAPS entry' => [
                'pokemonName' => 'mewtwo',
                'entries' => [
                    [
                        'flavor_text' => "OLD MEWTWO description from red version.",
                        'language'    => ['name' => 'en'],
                        'version'     => ['name' => 'red'],
                    ],
                    [
                        'flavor_text' => "It was created by a scientist after years of horrific gene splicing and DNA engineering experiments.",
                        'language'    => ['name' => 'en'],
                        'version'     => ['name' => 'sword'],
                    ],
                ],
                'expected' => "It was created by a scientist after years of horrific gene splicing and DNA engineering experiments.",
            ],

            'falls back to any clean version when no priority version is available' => [
                'pokemonName' => 'mewtwo',
                'entries' => [
                    [
                        'flavor_text' => "OLD MEWTWO description from unknown version.",
                        'language'    => ['name' => 'en'],
                        'version'     => ['name' => 'unknownVersion1'],
                    ],
                    [
                        'flavor_text' => "It was created by a scientist after years of horrific gene splicing and DNA engineering experiments.",
                        'language'    => ['name' => 'en'],
                        'version'     => ['name' => 'unknownVersion2'],
                    ],
                ],
                'expected' => "It was created by a scientist after years of horrific gene splicing and DNA engineering experiments.",
            ],

            'falls back to entry without hard artifacts when no clean text is available' => [
                'pokemonName' => 'mewtwo',
                'entries' => [
                    [
                        'flavor_text' => "OLD MEWTWO description\f from unknown version.",
                        'language'    => ['name' => 'en'],
                        'version'     => ['name' => 'unknownVersion1'],
                    ],
                    [
                        'flavor_text' => "MEWTWO was created by a scientist after years of horrific gene splicing and DNA engineering experiments.",
                        'language'    => ['name' => 'en'],
                        'version'     => ['name' => 'unknownVersion2'],
                    ],
                ],
                'expected' => "MEWTWO was created by a scientist after years of horrific gene splicing and DNA engineering experiments.",
            ],

            'falls back to last resort when all entries have hard artifacts' => [
                'pokemonName' => 'mewtwo',
                'entries' => [
                    [
                        'flavor_text' => "OLD MEWTWO description\f from unknown version.",
                        'language'    => ['name' => 'en'],
                        'version'     => ['name' => 'unknownVersion1'],
                    ],
                    [
                        'flavor_text' => "MEWTWO was created by a scientist\f after years of horrific gene splicing and DNA engineering experiments.",
                        'language'    => ['name' => 'en'],
                        'version'     => ['name' => 'unknownVersion2'],
                    ],
                ],
                'expected' => "OLD MEWTWO description from unknown version.",
            ],
        ];
    }

    /**
     * @param string      $pokemonName
     * @param array       $entries
     * @param string|null $expected
     */
    #[DataProvider('flavorTextExtractionProvider')]
    public function test_extract(string $pokemonName, array $entries, ?string $expected): void
    {
        $result = $this->extractor->extract($entries, $pokemonName);

        $this->assertSame($expected, $result);
    }

    /** Verifies that null is returned when the entries array is empty. */
    public function test_returns_null_when_entries_are_empty(): void
    {
        $result = $this->extractor->extract([], 'mewtwo');

        $this->assertNull($result);
    }

    /** Verifies that null is returned when entries exist but none are in English. */
    public function test_returns_null_when_no_english_entries(): void
    {
        $entries = [
            [
                'flavor_text' => 'Une description en français.',
                'language'    => ['name' => 'fr'],
                'version'     => ['name' => 'sword'],
            ],
            [
                'flavor_text' => '日本語の説明。',
                'language'    => ['name' => 'ja'],
                'version'     => ['name' => 'sword'],
            ],
        ];

        $result = $this->extractor->extract($entries, 'mewtwo');

        $this->assertNull($result);
    }
}