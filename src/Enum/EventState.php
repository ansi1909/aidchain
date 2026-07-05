<?php

namespace App\Enum;

enum EventState: string
{
    // Solo tiene firma de origen, esperando confirmación de destino
    case EN_TRANSITO = 'en_transito';

    // Tiene firma de origen y destino, evento cerrado
    case CONSOLIDADO = 'consolidado';

    // Congelado por el control de doble cobro (Fase 3)
    case CONGELADO = 'congelado';

    // Extraído por Gemini desde WhatsApp con baja confianza, requiere revisión humana
    case PENDIENTE_REVISION = 'pendiente_revision';
}
