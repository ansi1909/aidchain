<?php

namespace App\Enum;

enum EventChannel: string
{
    case WEB = 'web';
    case APP_TERRENO = 'app_terreno';
    case WHATSAPP = 'whatsapp';
    case EXCEL = 'excel';
}
