<?php

namespace App\Enum;

/**
 * Rol operativo de un coordinador dentro del ecosistema humanitario.
 * Determina qué tipo de eventos puede originar o confirmar en el ledger.
 */
enum CoordinatorRole: string
{
    // Origina salidas de un centro de acopio (OUT_DISPATCH)
    case DESPACHADOR = 'despachador';

    // Confirma la recepción en un refugio de destino (IN_RECEPTION) — Fase 4
    case ENCARGADO_REFUGIO = 'encargado_refugio';

    // Perfil de auditoría/administración (lectura y liberación de bloqueos)
    case AUDITOR = 'auditor';

    // Perfil de configuración del sistema (gestión de refugios, umbrales, catálogos)
    case ADMIN = 'admin';
}
