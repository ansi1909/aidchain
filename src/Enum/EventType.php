<?php

namespace App\Enum;

enum EventType: string
{
    // Salida de un centro de acopio hacia un refugio (logística a granel, sin beneficiario específico)
    case OUT_DISPATCH = 'out_dispatch';

    // Entrega individual desde el stock del refugio a un beneficiario específico (última milla)
    case OUT_BENEFICIARY = 'out_beneficiary';

    // Recepción confirmada en destino (refugio o beneficiario final)
    case IN_RECEPTION = 'in_reception';

    // Ingreso de mercancía a un centro de acopio (donación, compra, etc.)
    case IN_STOCK = 'in_stock';

    // Eventos de configuración (trazabilidad de acciones admin)
    case CONFIG_SHELTER_CREATE = 'config_shelter_create';
    case CONFIG_SHELTER_UPDATE = 'config_shelter_update';
    case CONFIG_SHELTER_INACTIVATE = 'config_shelter_inactivate';
}
