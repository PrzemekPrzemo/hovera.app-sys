<?php

declare(strict_types=1);

namespace Tests\Feature\Support;

use App\Support\UiIcons;
use ReflectionClass;
use Tests\TestCase;

/**
 * Sanity test dla UiIcons — kazdy const powinien byc niepustym stringiem
 * w formacie `heroicon-{o|s}-...`. Cel: gdy ktos doda nowa stala bez
 * literala, test sypie zanim trafi na produkcje.
 */
class UiIconsTest extends TestCase
{
    public function test_all_constants_are_heroicon_format(): void
    {
        $constants = (new ReflectionClass(UiIcons::class))->getConstants();

        $this->assertNotEmpty($constants, 'UiIcons should expose constants.');

        foreach ($constants as $name => $value) {
            $this->assertIsString($value, "UiIcons::{$name} should be a string.");
            $this->assertMatchesRegularExpression(
                '/^heroicon-[os]-[a-z0-9-]+$/',
                $value,
                "UiIcons::{$name} = '{$value}' is not a valid heroicon name."
            );
        }
    }

    public function test_horse_and_client_icons_are_distinct(): void
    {
        // Horse i Client to dwa najczesciej widoczne entity w panelu stable —
        // muszą sie różnić wizualnie żeby user nie pomylil zakładek.
        $this->assertNotSame(UiIcons::HORSE, UiIcons::CLIENT);
    }
}
