<?php

namespace App\Enum;

enum AlertSeverity: string
{
    case BAJA = 'baja';
    case MEDIA = 'media';
    case ALTA = 'alta';
    case CRITICA = 'critica';
}
