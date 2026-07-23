<?php

namespace App\Dto;

use App\Entity\Beneficiary;
use App\Entity\Coordinator;
use App\Entity\Organization;
use App\Entity\Shelter;
use App\Enum\EventChannel;
use App\Enum\EventState;
use App\Enum\EventType;

/**
 * Objeto de transporte con los datos necesarios para dar de alta un
 * InventoryEvent en el ledger a través de CryptoLedgerService::appendEvent().
 *
 * Es intencionalmente inmutable: representa la "intención" de un evento
 * antes de ser encadenado y persistido.
 */
final class EventData
{
    public function __construct(
        public readonly EventType $tipo,
        public readonly ?string $item = null,
        public readonly ?string $cantidad = null,
        public readonly ?string $unidad = null,
        public readonly ?Shelter $shelter = null,
        public readonly ?Organization $organization = null,
        public readonly EventChannel $canalOrigen = EventChannel::WEB,
        /** Firma ECDSA P-256 (base64) generada por el origen sobre el payload canónico. */
        public readonly string $firmaOrigen = '',
        /**
         * Coordinador que origina y firma el evento. Su clave pública valida
         * `firmaOrigen`. Puede ser null en canales delegados (WhatsApp/Excel),
         * donde la verificación de firma se omite o delega.
         */
        public readonly ?Coordinator $coordinatorOrigen = null,
        public readonly ?Beneficiary $beneficiary = null,
        public readonly ?string $loteId = null,
        /**
         * Estado inicial del evento. Si no se especifica, se deriva del tipo:
         * un OUT_DISPATCH nace EN_TRANSITO (espera firma de destino), el resto
         * nace CONSOLIDADO.
         */
        public readonly ?EventState $estado = null,
        /**
         * Datos de configuración (JSON) para eventos de tipo CONFIG_*.
         * Contiene los datos específicos de la acción administrativa.
         */
        public readonly ?array $datosConfiguracion = null,
    ) {
    }

    /**
     * Estado efectivo del evento aplicando la regla por defecto según el tipo.
     * OUT_DISPATCH nace EN_TRANSITO (espera firma de destino).
     * OUT_BENEFICIARY nace CONSOLIDADO (es entrega local, no requiere recepción).
     * Los eventos de configuración (CONFIG_*) nacen CONSOLIDADO.
     * El resto nace CONSOLIDADO.
     */
    public function resolveEstado(): EventState
    {
        if ($this->estado !== null) {
            return $this->estado;
        }

        if ($this->tipo === EventType::OUT_DISPATCH) {
            return EventState::EN_TRANSITO;
        }

        // Eventos de configuración y el resto nacen consolidados
        return EventState::CONSOLIDADO;
    }
}
