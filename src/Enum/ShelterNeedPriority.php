<?php

namespace App\Enum;

enum ShelterNeedPriority: string
{
    case BAJA = 'baja';
    case MEDIA = 'media';
    case ALTA = 'alta';
    case CRITICA = 'critica';

    public function getLabel(): string
    {
        return match ($this) {
            self::BAJA => 'Baja',
            self::MEDIA => 'Media',
            self::ALTA => 'Alta',
            self::CRITICA => 'Crítica',
        };
    }

    public function getWeight(): int
    {
        return match ($this) {
            self::BAJA => 1,
            self::MEDIA => 2,
            self::ALTA => 3,
            self::CRITICA => 4,
        };
    }
}
