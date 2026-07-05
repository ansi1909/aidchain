<?php

namespace App\Enum;

enum EventChannel: string
{
    case APP_TERRENO = 'app_terreno';
    case WHATSAPP = 'whatsapp';
    case EXCEL = 'excel';
}
