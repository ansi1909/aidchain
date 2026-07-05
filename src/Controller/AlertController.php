<?php

namespace App\Controller;

use App\Entity\AuditAlert;
use App\Repository\AuditAlertRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Exposición de alertas de auditoría para el dashboard (consumido por
 * frontend/src/stores/alerts.js). Aunque el panel completo es de la Fase 9,
 * este endpoint mínimo permite que el dashboard cargue datos reales.
 */
#[Route('/api/alerts')]
class AlertController extends AbstractController
{
    public function __construct(
        private readonly AuditAlertRepository $alertRepository,
    ) {
    }

    /**
     * Lista alertas. Por defecto (o con ?resuelto=false) devuelve solo las
     * activas; con ?resuelto=true devuelve las ya resueltas.
     */
    #[Route('', name: 'api_alerts_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $resueltoParam = $request->query->get('resuelto');
        $resuelto = filter_var($resueltoParam ?? 'false', FILTER_VALIDATE_BOOLEAN);

        $alertas = $this->alertRepository->findBy(
            ['resuelto' => $resuelto],
            ['createdAt' => 'DESC'],
        );

        $data = array_map($this->serializeAlert(...), $alertas);

        return $this->json($data);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeAlert(AuditAlert $alerta): array
    {
        $evento = $alerta->getInventoryEvent();

        return [
            'id' => $alerta->getId(),
            'tipo' => $alerta->getTipo(),
            'mensaje' => $alerta->getMensaje(),
            'severidad' => $alerta->getSeveridad()->value,
            'zonaAfectada' => $alerta->getZonaAfectada(),
            'resuelto' => $alerta->isResuelto(),
            'inventoryEventId' => $evento?->getId(),
            'createdAt' => $alerta->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }
}
