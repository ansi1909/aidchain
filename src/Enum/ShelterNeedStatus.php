<?php

namespace App\Enum;

enum ShelterNeedStatus: string
{
    case PENDIENTE = 'pendiente';
    case PARCIALMENTE_SATISFECHO = 'parcialmente_satisfecho';
    case SATISFECHO = 'satisfecho';

    public function getLabel(): string
    {
        return match ($this) {
            self::PENDIENTE => 'Pendiente',
            self::PARCIALMENTE_SATISFECHO => 'Parcialmente satisfecho',
            self::SATISFECHO => 'Satisfecho',
        };
    }

    /**
     * Calcula el estado basado en el progreso de satisfacción.
     */
    public static function fromProgress(float $requerida, float $recibida): self
    {
        if ($recibida >= $requerida) {
            return self::SATISFECHO;
        }
        if ($recibida > 0) {
            return self::PARCIALMENTE_SATISFECHO;
        }
        return self::PENDIENTE;
    }
}
