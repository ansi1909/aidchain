<?php

namespace App\Enum;

enum OrganizationType: string
{
    case GOBIERNO = 'gobierno';
    case ONG = 'ong';
    case VOLUNTARIADO = 'voluntariado';
    case CRUZ_ROJA = 'cruz_roja';
}
