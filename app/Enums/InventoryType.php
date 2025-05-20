<?php

namespace App\Enums;

enum InventoryType: int
{
    case INPUT = 1;
    case OUTPUT = 2;

    public function label(): string
    {
        return match ($this) {
            self::INPUT => 'Input',
            self::OUTPUT => 'Output',
        };
    }

    public static function fromLabel(string $label): ?self
    {
        return match (strtolower($label)) {
            'input' => self::INPUT,
            'output' => self::OUTPUT,
            default => null,
        };
    }
}
