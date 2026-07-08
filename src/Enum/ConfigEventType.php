<?php

namespace App\Enum;

enum ConfigEventType: string
{
    case SHELTER_CREATE = 'shelter_create';
    case SHELTER_UPDATE = 'shelter_update';
    case SHELTER_INACTIVATE = 'shelter_inactivate';
}
