<?php

namespace App\Service;

use App\Entity\InventoryEvent;
use App\Entity\ShelterStock;
use App\Enum\EventType;
use App\Repository\ShelterStockRepository;

/**
 * Servicio para gestionar el stock de insumos en refugios.
 * Actualiza ShelterStock cuando llegan despachos logísticos
 * y valida stock disponible al entregar a beneficiarios.
 */
class ShelterStockService
{
    public function __construct(
        private readonly ShelterStockRepository $stockRepository,
    ) {
    }

    /**
     * Incrementa el stock de un refugio cuando llega un despacho logístico (OUT_DISPATCH).
     * Solo aplica si el evento está CONSOLIDADO (firma cruzada completada).
     */
    public function incrementarPorDespacho(InventoryEvent $event): void
    {
        if ($event->getTipo() !== EventType::OUT_DISPATCH) {
            return;
        }

        if ($event->getEstado() !== \App\Enum\EventState::CONSOLIDADO) {
            return;
        }

        // No incrementar si tiene beneficiario (eso es entrega directa, no logística)
        if ($event->getBeneficiary() !== null) {
            return;
        }

        $this->stockRepository->incrementarStock(
            $event->getShelter()->getId(),
            $event->getItem(),
            $event->getCantidad(),
            $event->getUnidad(),
        );
    }

    /**
     * Valida que haya suficiente stock disponible para una entrega a beneficiario.
     * @throws \RuntimeException si no hay suficiente stock.
     */
    public function validarStockDisponible(int $shelterId, string $item, string $cantidad): void
    {
        $stock = $this->stockRepository->findByShelterAndItem($shelterId, $item);

        if ($stock === null) {
            throw new \RuntimeException(sprintf(
                'No existe stock para el item "%s" en el refugio %d',
                $item,
                $shelterId,
            ));
        }

        $disponible = (float) $stock->getCantidadDisponible();
        $solicitado = (float) $cantidad;

        if ($disponible < $solicitado) {
            throw new \RuntimeException(sprintf(
                'Stock insuficiente: disponible %s %s, solicitado %s %s',
                $disponible,
                $stock->getUnidad(),
                $solicitado,
                $stock->getUnidad(),
            ));
        }
    }

    /**
     * Decrementa el stock cuando se entrega a un beneficiario (OUT_BENEFICIARY).
     */
    public function decrementarPorEntrega(InventoryEvent $event): void
    {
        if ($event->getTipo() !== EventType::OUT_BENEFICIARY) {
            return;
        }

        if ($event->getBeneficiary() === null) {
            return;
        }

        $this->stockRepository->decrementarStock(
            $event->getShelter()->getId(),
            $event->getItem(),
            $event->getCantidad(),
        );
    }

    /**
     * Obtiene el stock disponible de un item en un refugio.
     */
    public function obtenerStock(int $shelterId, string $item): ?ShelterStock
    {
        return $this->stockRepository->findByShelterAndItem($shelterId, $item);
    }

    /**
     * Obtiene todo el stock de un refugio.
     * @return ShelterStock[]
     */
    public function obtenerStockPorRefugio(int $shelterId): array
    {
        return $this->stockRepository->findByShelter($shelterId);
    }

    /**
     * Obtiene el inventario consolidado de todos los refugios.
     * @return ShelterStock[]
     */
    public function obtenerInventarioConsolidado(): array
    {
        return $this->stockRepository->findForDashboard();
    }
}
