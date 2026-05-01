<?php

declare(strict_types=1);

namespace Tests\Unit\Helper;

use App\Helper\FlavorTextExtractor;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class FlavorTextExtractorTest extends TestCase
{
    private FlavorTextExtractor $extractor;

    protected function setUp(): void
    {
        $this->extractor = new FlavorTextExtractor();
    }

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

    #[DataProvider('flavorTextExtractionProvider')]
    public function test_extract(string $pokemonName, array $entries, ?string $expected): void
    {
        $result = $this->extractor->extract($entries, $pokemonName);

        $this->assertSame($expected, $result);
    }

    public function test_returns_null_when_entries_are_empty(): void
    {
        $result = $this->extractor->extract([], 'mewtwo');

        $this->assertNull($result);
    }
}