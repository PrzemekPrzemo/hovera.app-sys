<?php

declare(strict_types=1);

namespace App\Enums;

enum CalendarEntryType: string
{
    case LessonIndividual = 'lesson_individual';
    case LessonGroup = 'lesson_group';
    case Training = 'training';
    case Care = 'care';
    case Event = 'event';
    case Block = 'block';

    public function label(): string
    {
        return __('enums.calendar_entry_type.'.$this->value);
    }

    /**
     * @return array<string,string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $c) => [$c->value => $c->label()])
            ->all();
    }

    public function requiresHorse(): bool
    {
        return match ($this) {
            self::LessonIndividual, self::Training, self::Care => true,
            default => false,
        };
    }

    public function requiresInstructor(): bool
    {
        return match ($this) {
            self::LessonIndividual, self::LessonGroup, self::Training => true,
            default => false,
        };
    }
}
