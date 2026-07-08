<?php

namespace App\Controller;

use App\Dto\EventData;
use App\Entity\InventoryEvent;
use App\Enum\CoordinatorRole;
use App\Enum\EventChannel;
use App\Enum\EventType;
use App\Exception\InvalidSignatureException;
use App\Exception\ReceptionNotAllowedException;
use App\Repository\BeneficiaryRepository;
use App\Repository\CoordinatorRepository;
use App\Repository\InventoryEventRepository;
use App\Repository\ShelterNeedRepository;
use App\Repository\ShelterRepository;
use App\Service\CryptoLedgerService;
use App\Service\DoubleChargeService;
use App\Service\ShelterStockService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Endpoints de integridad del ledger (Fase 2).
 */
#[Route('/api/ledger')]
class LedgerController extends AbstractController
{
    public function __construct(
        private readonly CryptoLedgerService $ledgerService,
        private readonly CoordinatorRepository $coordinatorRepository,
        private readonly ShelterRepository $shelterRepository,
        private readonly BeneficiaryRepository $beneficiaryRepository,
        private readonly InventoryEventRepository $eventRepository,
        private readonly ShelterNeedRepository $shelterNeedRepository,
        private readonly DoubleChargeService $doubleChargeService,
        private readonly ShelterStockService $stockService,
    ) {
    }

    /**
     * Da de alta un evento firmado en el ledger (canal App de Terreno).
     * El cuerpo debe incluir la firma ECDSA (base64) generada por el
     * coordinador sobre el payload canónico; si no valida, se rechaza con 422.
     */
    #[Route('/events', name: 'api_ledger_events_create', methods: ['POST'])]
    public function createEvent(Request $request): JsonResponse
    {
        try {
            $body = $request->toArray();
        } catch (\JsonException) {
            return $this->json(['error' => 'JSON inválido.'], Response::HTTP_BAD_REQUEST);
        }

        $tipo = EventType::tryFrom((string) ($body['tipo'] ?? ''));
        if ($tipo === null) {
            return $this->json([
                'error' => sprintf('tipo inválido. Válidos: %s.', implode(', ', array_column(EventType::cases(), 'value'))),
            ], Response::HTTP_BAD_REQUEST);
        }

        $canal = EventChannel::tryFrom((string) ($body['canalOrigen'] ?? EventChannel::APP_TERRENO->value));
        if ($canal === null) {
            return $this->json(['error' => 'canalOrigen inválido.'], Response::HTTP_BAD_REQUEST);
        }

        $item = trim((string) ($body['item'] ?? ''));
        $cantidad = (string) ($body['cantidad'] ?? '');
        $unidad = trim((string) ($body['unidad'] ?? ''));
        $firmaOrigen = (string) ($body['firmaOrigen'] ?? '');

        if ($item === '' || $cantidad === '' || $unidad === '' || $firmaOrigen === '') {
            return $this->json(['error' => 'item, cantidad, unidad y firmaOrigen son obligatorios.'], Response::HTTP_BAD_REQUEST);
        }

        if (!is_numeric($cantidad) || (float) $cantidad <= 0) {
            return $this->json(['error' => 'cantidad debe ser un número positivo.'], Response::HTTP_BAD_REQUEST);
        }

        $coordinator = isset($body['coordinatorId']) ? $this->coordinatorRepository->find($body['coordinatorId']) : null;
        if ($coordinator === null) {
            return $this->json(['error' => 'coordinatorId no encontrado.'], Response::HTTP_BAD_REQUEST);
        }

        // Capacidad requerida según la etapa del flujo de dos etapas (Fase 8):
        //  - Logística (OUT_DISPATCH) e ingreso a bodega (IN_STOCK) → DESPACHADOR.
        //  - Última milla (OUT_BENEFICIARY): la entrega individual la realiza el
        //    refugio contra su propio stock, por lo que exige ENCARGADO_REFUGIO.
        // La confirmación de recepción (firma cruzada) tiene su propio endpoint.
        if (in_array($tipo, [EventType::OUT_DISPATCH, EventType::IN_STOCK], true)
            && !$coordinator->hasRole(CoordinatorRole::DESPACHADOR)) {
            return $this->json([
                'error' => 'Solo un coordinador con rol DESPACHADOR puede originar despachos logísticos o ingresos a bodega.',
            ], Response::HTTP_FORBIDDEN);
        }

        if ($tipo === EventType::OUT_BENEFICIARY && !$coordinator->hasRole(CoordinatorRole::ENCARGADO_REFUGIO)) {
            return $this->json([
                'error' => 'Solo un coordinador con rol ENCARGADO_REFUGIO puede registrar entregas a beneficiarios (última milla).',
            ], Response::HTTP_FORBIDDEN);
        }

        $shelter = isset($body['shelterId']) ? $this->shelterRepository->find($body['shelterId']) : null;
        if ($shelter === null) {
            return $this->json(['error' => 'shelterId no encontrado.'], Response::HTTP_BAD_REQUEST);
        }

        // Validar beneficiary según el tipo de evento
        $beneficiary = null;
        if ($tipo === EventType::OUT_DISPATCH) {
            // OUT_DISPATCH es logística a granel: NO debe tener beneficiario
            if (!empty($body['beneficiaryToken'])) {
                return $this->json([
                    'error' => 'OUT_DISPATCH es para despachos logísticos al refugio (sin beneficiario específico). Usa OUT_BENEFICIARY para entregas individuales.',
                ], Response::HTTP_BAD_REQUEST);
            }
        } elseif ($tipo === EventType::OUT_BENEFICIARY) {
            // OUT_BENEFICIARY es entrega individual: REQUIERE beneficiario
            if (empty($body['beneficiaryToken'])) {
                return $this->json([
                    'error' => 'OUT_BENEFICIARY requiere un beneficiaryToken para la entrega individual.',
                ], Response::HTTP_BAD_REQUEST);
            }
            $beneficiary = $this->beneficiaryRepository->findOneByToken((string) $body['beneficiaryToken']);
            if ($beneficiary === null) {
                return $this->json(['error' => 'beneficiaryToken no encontrado.'], Response::HTTP_BAD_REQUEST);
            }

            // Validar que el beneficiario pertenezca al refugio
            if ($beneficiary->getShelter()->getId() !== $shelter->getId()) {
                return $this->json([
                    'error' => sprintf(
                        'El beneficiario pertenece al refugio "%s", pero el despacho es para "%s".',
                        $beneficiary->getShelter()->getNombre(),
                        $shelter->getNombre(),
                    ),
                ], Response::HTTP_BAD_REQUEST);
            }

            // Validar stock disponible antes de crear el evento
            try {
                $this->stockService->validarStockDisponible($shelter->getId(), $item, $cantidad);
            } catch (\RuntimeException $e) {
                return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
            }
        } else {
            // Para otros tipos (IN_STOCK, IN_RECEPTION), beneficiary es opcional
            if (!empty($body['beneficiaryToken'])) {
                $beneficiary = $this->beneficiaryRepository->findOneByToken((string) $body['beneficiaryToken']);
                if ($beneficiary === null) {
                    return $this->json(['error' => 'beneficiaryToken no encontrado.'], Response::HTTP_BAD_REQUEST);
                }
            }
        }

        $data = new EventData(
            tipo: $tipo,
            item: $item,
            cantidad: $cantidad,
            unidad: $unidad,
            shelter: $shelter,
            organization: $coordinator->getOrganization(),
            canalOrigen: $canal,
            firmaOrigen: $firmaOrigen,
            coordinatorOrigen: $coordinator,
            beneficiary: $beneficiary,
            loteId: isset($body['loteId']) ? (string) $body['loteId'] : null,
        );

        try {
            $event = $this->ledgerService->appendEvent($data);
        } catch (InvalidSignatureException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Error al registrar el evento: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        // Integración post-registro según tipo de evento
        try {
            if ($tipo === EventType::OUT_DISPATCH) {
                // OUT_DISPATCH: actualizar ShelterNeed (Fase 7b)
                $need = $this->shelterNeedRepository->findByShelterAndItem($shelter->getId(), $item);
                if ($need !== null) {
                    $need->agregarCantidadRecibida($cantidad);
                    $this->shelterNeedRepository->getEntityManager()->flush();
                }
            } elseif ($tipo === EventType::OUT_BENEFICIARY) {
                // OUT_BENEFICIARY: decrementar stock y validar doble cobro
                $this->stockService->decrementarPorEntrega($event);

                $validacion = $this->doubleChargeService->validarDespacho(
                    $beneficiary->getBeneficiaryToken(),
                    $item,
                    (float) $cantidad,
                );

                if ($validacion['congelar']) {
                    $this->doubleChargeService->congelarEvento($event, $validacion['motivo']);
                }
            }
        } catch (\Exception $e) {
            // Si falla la integración post-registro, no fallar el registro del evento
            error_log('Error en integración post-registro: ' . $e->getMessage());
        }

        return $this->json([
            'id' => $event->getId(),
            'tipo' => $event->getTipo()->value,
            'item' => $event->getItem(),
            'cantidad' => $event->getCantidad(),
            'unidad' => $event->getUnidad(),
            'estado' => $event->getEstado()->value,
            'hashActual' => $event->getHashActual(),
            'hashAnterior' => $event->getHashAnterior(),
            'loteId' => $event->getLoteId(),
            'createdAt' => $event->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ], Response::HTTP_CREATED);
    }

    /**
     * Obtiene eventos congelados por doble cobro (Fase 3).
     */
    #[Route('/dispatches/frozen', name: 'api_ledger_dispatches_frozen', methods: ['GET'])]
    public function frozenDispatches(): JsonResponse
    {
        $eventos = $this->doubleChargeService->obtenerEventosCongelados();

        return $this->json(array_map(
            fn (InventoryEvent $e): array => [
                'id' => $e->getId(),
                'item' => $e->getItem(),
                'cantidad' => $e->getCantidad(),
                'unidad' => $e->getUnidad(),
                'estado' => $e->getEstado()->value,
                'loteId' => $e->getLoteId(),
                'createdAt' => $e->getCreatedAt()->format(\DateTimeInterface::ATOM),
                'shelter' => [
                    'id' => $e->getShelter()->getId(),
                    'nombre' => $e->getShelter()->getNombre(),
                    'zona' => $e->getShelter()->getZona(),
                ],
                'beneficiary' => $e->getBeneficiary() ? [
                    'id' => $e->getBeneficiary()->getId(),
                    'token' => $e->getBeneficiary()->getBeneficiaryToken(),
                    'nombre' => $e->getBeneficiary()->getNombre(),
                ] : null,
            ],
            $eventos,
        ));
    }

    /**
     * Libera un evento congelado (aprobación manual del auditor).
     */
    #[Route('/dispatches/{id}/release', name: 'api_ledger_dispatch_release', methods: ['POST'])]
    public function releaseDispatch(int $id): JsonResponse
    {
        $event = $this->eventRepository->find($id);
        if (!$event) {
            return $this->json(['error' => 'Evento no encontrado'], Response::HTTP_NOT_FOUND);
        }

        try {
            $this->doubleChargeService->liberarEvento($event);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        return $this->json([
            'id' => $event->getId(),
            'estado' => $event->getEstado()->value,
        ]);
    }

    /**
     * Rechaza un evento congelado (cancelación manual del auditor).
     */
    #[Route('/dispatches/{id}/reject', name: 'api_ledger_dispatch_reject', methods: ['POST'])]
    public function rejectDispatch(int $id): JsonResponse
    {
        $event = $this->eventRepository->find($id);
        if (!$event) {
            return $this->json(['error' => 'Evento no encontrado'], Response::HTTP_NOT_FOUND);
        }

        try {
            $this->doubleChargeService->rechazarEvento($event);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        return $this->json([
            'id' => $event->getId(),
            'estado' => $event->getEstado()->value,
        ]);
    }

    /**
     * Lista los despachos pendientes de confirmación de recepción (Fase 4):
     * eventos OUT_DISPATCH que siguen EN_TRANSITO. Devuelve los campos necesarios
     * para que el receptor reconstruya el payload canónico y lo firme.
     */
    #[Route('/dispatches/pending', name: 'api_ledger_dispatches_pending', methods: ['GET'])]
    public function pendingDispatches(Request $request): JsonResponse
    {
        $shelterId = $request->query->get('shelterId');
        $despachos = $this->eventRepository->findDespachosPendientes(
            $shelterId !== null ? (int) $shelterId : null,
        );

        return $this->json(array_map(
            fn (InventoryEvent $e): array => $this->serializePendingDispatch($e),
            $despachos,
        ));
    }

    /**
     * Firma cruzada de doble vía (Fase 4): el ENCARGADO_REFUGIO de destino
     * confirma la recepción de un lote firmando el mismo payload canónico del
     * despacho. Si la firma valida, el evento pasa de EN_TRANSITO a CONSOLIDADO.
     */
    #[Route('/dispatches/{loteId}/receive', name: 'api_ledger_dispatch_receive', methods: ['POST'])]
    public function receiveDispatch(string $loteId, Request $request): JsonResponse
    {
        try {
            $body = $request->toArray();
        } catch (\JsonException) {
            return $this->json(['error' => 'JSON inválido.'], Response::HTTP_BAD_REQUEST);
        }

        $firmaDestino = (string) ($body['firmaDestino'] ?? '');
        if ($firmaDestino === '') {
            return $this->json(['error' => 'firmaDestino es obligatoria.'], Response::HTTP_BAD_REQUEST);
        }

        $receiver = isset($body['coordinatorId']) ? $this->coordinatorRepository->find($body['coordinatorId']) : null;
        if ($receiver === null) {
            return $this->json(['error' => 'coordinatorId no encontrado.'], Response::HTTP_BAD_REQUEST);
        }

        if (!$receiver->hasRole(CoordinatorRole::ENCARGADO_REFUGIO)) {
            return $this->json([
                'error' => 'Solo un coordinador con rol ENCARGADO_REFUGIO puede confirmar la recepción.',
            ], Response::HTTP_FORBIDDEN);
        }

        $despacho = $this->eventRepository->findDespachoPendienteByLote($loteId);
        if ($despacho === null) {
            return $this->json([
                'error' => 'No se encontró un despacho EN_TRANSITO para ese lote.',
            ], Response::HTTP_NOT_FOUND);
        }

        // El encargado solo puede confirmar la recepción de lotes dirigidos a su
        // propio refugio. Se compara el refugio de destino del despacho con el
        // refugio asignado al coordinador receptor.
        $refugioReceptor = $receiver->getShelter();
        $refugioDestino = $despacho->getShelter();
        if ($refugioReceptor === null || $refugioReceptor->getId() !== $refugioDestino->getId()) {
            return $this->json([
                'error' => sprintf(
                    'No autorizado: este lote está dirigido a "%s", pero tu identidad está asignada a %s. Solo el encargado del refugio de destino puede confirmar la recepción.',
                    $refugioDestino->getNombre(),
                    $refugioReceptor !== null ? sprintf('"%s"', $refugioReceptor->getNombre()) : 'ningún refugio',
                ),
            ], Response::HTTP_FORBIDDEN);
        }

        try {
            $event = $this->ledgerService->confirmReception($despacho, $receiver, $firmaDestino);
        } catch (InvalidSignatureException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (ReceptionNotAllowedException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_CONFLICT);
        }

        return $this->json([
            'id' => $event->getId(),
            'loteId' => $event->getLoteId(),
            'estado' => $event->getEstado()->value,
            'hashActual' => $event->getHashActual(),
            'firmaOrigen' => $event->getFirmaOrigen(),
            'firmaDestino' => $event->getFirmaDestino(),
            'coordinatorOrigen' => $event->getCoordinatorOrigen()?->getNombre(),
            'coordinatorDestino' => $event->getCoordinatorDestino()?->getNombre(),
        ], Response::HTTP_OK);
    }

    /**
     * Serializa un despacho pendiente con los campos exactos que el frontend
     * necesita para reconstruir el payload canónico (buildCanonicalPayload) y
     * firmarlo, más datos legibles para la interfaz.
     */
    private function serializePendingDispatch(InventoryEvent $e): array
    {
        return [
            'id' => $e->getId(),
            // Campos del payload canónico (deben coincidir con crypto.js).
            'tipo' => $e->getTipo()->value,
            'item' => $e->getItem(),
            'cantidad' => $e->getCantidad(),
            'unidad' => $e->getUnidad(),
            'beneficiaryToken' => $e->getBeneficiary()?->getBeneficiaryToken(),
            'shelterId' => $e->getShelter()->getId(),
            'organizationId' => $e->getOrganization()->getId(),
            'coordinatorId' => $e->getCoordinatorOrigen()?->getId(),
            'canalOrigen' => $e->getCanalOrigen()->value,
            'loteId' => $e->getLoteId(),
            // Datos legibles para la vista.
            'estado' => $e->getEstado()->value,
            'shelterNombre' => $e->getShelter()->getNombre(),
            'coordinatorOrigenNombre' => $e->getCoordinatorOrigen()?->getNombre(),
            'createdAt' => $e->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }

    /**
     * Obtiene el historial de eventos de un beneficiario específico (Fase 8).
     * Útil para mostrar las entregas recibidas en la vista de entrega a beneficiarios.
     */
    #[Route('/beneficiary/{token}/events', name: 'api_ledger_beneficiary_events', methods: ['GET'])]
    public function beneficiaryEvents(string $token): JsonResponse
    {
        $beneficiary = $this->beneficiaryRepository->findOneByToken($token);
        if ($beneficiary === null) {
            return $this->json(['error' => 'beneficiaryToken no encontrado'], Response::HTTP_NOT_FOUND);
        }

        $eventos = $this->eventRepository->findBy(
            ['beneficiary' => $beneficiary],
            ['createdAt' => 'DESC'],
        );

        return $this->json(array_map(
            fn (InventoryEvent $e): array => [
                'id' => $e->getId(),
                'tipo' => $e->getTipo()->value,
                'item' => $e->getItem(),
                'cantidad' => $e->getCantidad(),
                'unidad' => $e->getUnidad(),
                'estado' => $e->getEstado()->value,
                'createdAt' => $e->getCreatedAt()->format(\DateTimeInterface::ATOM),
                'shelter' => [
                    'id' => $e->getShelter()->getId(),
                    'nombre' => $e->getShelter()->getNombre(),
                ],
            ],
            $eventos,
        ));
    }

    /**
     * Recorre la cadena completa y reporta si está íntegra o dónde se rompió.
     * Devuelve 200 si la cadena es válida y 409 (Conflict) si detecta rupturas,
     * para que el frontend pueda disparar la "alarma perimetral".
     */
    #[Route('/verify', name: 'api_ledger_verify', methods: ['GET'])]
    public function verify(): JsonResponse
    {
        $resultado = $this->ledgerService->verifyChain();

        $status = $resultado['valid'] ? Response::HTTP_OK : Response::HTTP_CONFLICT;

        return $this->json($resultado, $status);
    }
}
