<?php

declare(strict_types=1);

namespace App\Actions\Calendar;

use Illuminate\Support\Collection;
use RuntimeException;

class CalendarConflictException extends RuntimeException
{
    /**
     * @param  array{horse:Collection,instructor:Collection,arena:Collection}  $conflicts
     */
    public function __construct(public readonly array $conflicts)
    {
        $resources = [];
        if ($conflicts['horse']->isNotEmpty()) {
            $resources[] = 'koń';
        }
        if ($conflicts['instructor']->isNotEmpty()) {
            $resources[] = 'instruktor';
        }
        if ($conflicts['arena']->isNotEmpty()) {
            $resources[] = 'ujeżdżalnia';
        }

        parent::__construct(
            'Konflikt rezerwacji ('.implode(', ', $resources).') — zasób jest już zajęty w tym czasie.'
        );
    }
}
