<?php

namespace App\Service;

use App\Entity\AuditAlert;
use App\Entity\InventoryEvent;
use App\Entity\ItemThreshold;
use App\Enum\EventState;
use App\Enum\EventType;
use App\Repository\AuditAlertRepository;
use App\Repository\InventoryEventRepository;
use App\Repository\ItemThresholdRepository;

/**
 * Servicio para el control de doble cobro (Fase 3).
 *
 * Valida si un despacho excede los umbrales configurados para un beneficiario
 * y genera alertas automáticas cuando se detectan duplicados.
 */
class DoubleChargeService
{
    public function __construct(
        private readonly InventoryEventRepository $eventRepository,
        private readonly ItemThresholdRepository $thresholdRepository,
        private readonly AuditAlertRepository $alertRepository,
    ) {
    }

    /**
     * Valida si una entrega a beneficiario debe congelarse por posible doble cobro.
     *
     * @param string $beneficiaryToken Token del beneficiario
     * @param string $item Item a entregar
     * @param float $cantidad Cantidad a entregar
     * @return array ['congelar' => bool, 'motivo' => ?string, 'umbral' => ?ItemThreshold]
     */
    public function validarDespacho(string $beneficiaryToken, string $item, float $cantidad): array
    {
        // Buscar umbral configurado para este item
        $umbral = $this->thresholdRepository->findByItem($item);

        // Si no hay umbral configurado, no se congela (modo permisivo)
        if (!$umbral) {
            return [
                'congelar' => false,
                'motivo' => null,
                'umbral' => null,
            ];
        }

        // Calcular cantidad ya recibida en la ventana de tiempo (solo OUT_BENEFICIARY)
        $cantidadRecibida = $this->eventRepository->sumaCantidadPorBeneficiario(
            $beneficiaryToken,
            $item,
            $umbral->getVentanaHoras(),
        );

        // Calcular total después de la entrega
        $totalDespues = $cantidadRecibida + $cantidad;
        $maximo = (float) $umbral->getCantidadMaxima();

        // Si excede el umbral, debe congelarse
        if ($totalDespues > $maximo) {
            return [
                'congelar' => true,
                'motivo' => sprintf(
                    'El beneficiario ya recibió %s %s de %s en las últimas %d horas. Esta entrega sumaría %s %s, excediendo el máximo de %s %s.',
                    $cantidadRecibida,
                    $umbral->getUnidad(),
                    $item,
                    $umbral->getVentanaHoras(),
                    $totalDespues,
                    $umbral->getUnidad(),
                    $maximo,
                    $umbral->getUnidad(),
                ),
                'umbral' => $umbral,
            ];
        }

        return [
            'congelar' => false,
            'motivo' => null,
            'umbral' => $umbral,
        ];
    }

    /**
     * Congela un evento por doble cobro y genera la alerta correspondiente.
     *
     * @param InventoryEvent $evento Evento a congelar
     * @param string $motivo Motivo del congelamiento
     * @return AuditAlert Alerta generada
     */
    public function congelarEvento(InventoryEvent $evento, string $motivo): AuditAlert
    {
        // Cambiar estado a CONGELADO
        $evento->setEstado(EventState::CONGELADO);

        // Generar alerta de severidad alta
        $alerta = new AuditAlert();
        $alerta->setTipo('DOBLE_COBRO');
        $alerta->setMensaje($motivo);
        $alerta->setSeveridad('alta');
        $alerta->setZonaAfectada($evento->getShelter()->getZona());
        $alerta->setInventoryEvent($evento);
        $alerta->setResuelto(false);

        $this->alertRepository->getEntityManager()->persist($alerta);
        $this->alertRepository->getEntityManager()->flush();

        return $alerta;
    }

    /**
     * Libera un evento congelado (aprobación manual del auditor).
     *
     * @param InventoryEvent $evento Evento a liberar
     */
    public function liberarEvento(InventoryEvent $evento): void
    {
        if ($evento->getEstado() !== EventState::CONGELADO) {
            throw new \InvalidArgumentException('Solo se pueden liberar eventos en estado CONGELADO');
        }

        // Cambiar estado a EN_TRANSITO para que pueda consolidarse
        $evento->setEstado(EventState::EN_TRANSITO);

        // Marcar alerta como resuelta
        $alerta = $this->alertRepository->findOneBy(['inventoryEvent' => $evento, 'tipo' => 'DOBLE_COBRO']);
        if ($alerta) {
            $alerta->setResuelto(true);
        }

        $this->alertRepository->getEntityManager()->flush();
    }

    /**
     * Rechaza un evento congelado (cancelación manual del auditor).
     *
     * @param InventoryEvent $evento Evento a rechazar
     */
    public function rechazarEvento(InventoryEvent $evento): void
    {
        if ($evento->getEstado() !== EventState::CONGELADO) {
            throw new \InvalidArgumentException('Solo se pueden rechazar eventos en estado CONGELADO');
        }

        // Cambiar estado a un estado de rechazo (podríamos agregar RECHAZADO al enum)
        // Por ahora lo dejamos en CONGELADO pero marcamos la alerta como resuelta
        $alerta = $this->alertRepository->findOneBy(['inventoryEvent' => $evento, 'tipo' => 'DOBLE_COBRO']);
        if ($alerta) {
            $alerta->setResuelto(true);
            $alerta->setMensaje($alerta->getMensaje() . ' - RECHAZADO POR AUDITOR');
        }

        $this->alertRepository->getEntityManager()->flush();
    }

    /**
     * Obtiene eventos congelados pendientes de revisión.
     *
     * @return InventoryEvent[]
     */
    public function obtenerEventosCongelados(): array
    {
        return $this->eventRepository->findBy(
            ['estado' => EventState::CONGELADO],
            ['createdAt' => 'DESC'],
        );
    }
}
