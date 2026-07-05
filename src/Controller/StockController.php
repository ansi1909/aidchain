<?php

namespace App\Controller;

use App\Entity\ShelterStock;
use App\Repository\ShelterRepository;
use App\Repository\ShelterStockRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Endpoints para consultar el inventario (stock) de los refugios (Fase 8).
 * El stock se incrementa al confirmar despachos logísticos (OUT_DISPATCH)
 * y se decrementa al entregar a beneficiarios (OUT_BENEFICIARY).
 */
#[Route('/api')]
class StockController extends AbstractController
{
    public function __construct(
        private readonly ShelterStockRepository $stockRepository,
        private readonly ShelterRepository $shelterRepository,
    ) {
    }

    /**
     * Inventario disponible de un refugio.
     */
    #[Route('/shelters/{id}/stock', name: 'api_shelter_stock_list', methods: ['GET'])]
    public function list(int $id): JsonResponse
    {
        $shelter = $this->shelterRepository->find($id);
        if (!$shelter) {
            return $this->json(['error' => 'Refugio no encontrado'], 404);
        }

        $stock = $this->stockRepository->findByShelter($id);

        return $this->json(array_map($this->serializeStock(...), $stock));
    }

    /**
     * Vista consolidada del inventario de todos los refugios (para dashboard/inventario).
     */
    #[Route('/stock/dashboard', name: 'api_stock_dashboard', methods: ['GET'])]
    public function dashboard(): JsonResponse
    {
        $stock = $this->stockRepository->findForDashboard();

        return $this->json(array_map($this->serializeStock(...), $stock));
    }

    private function serializeStock(ShelterStock $s): array
    {
        return [
            'id' => $s->getId(),
            'item' => $s->getItem(),
            'unidad' => $s->getUnidad(),
            'cantidadDisponible' => $s->getCantidadDisponible(),
            'updatedAt' => $s->getUpdatedAt()->format(\DateTimeInterface::ATOM),
            'shelter' => [
                'id' => $s->getShelter()->getId(),
                'nombre' => $s->getShelter()->getNombre(),
                'zona' => $s->getShelter()->getZona(),
            ],
        ];
    }
}
