<?php

namespace App\Controller;

use App\Dto\EventData;
use App\Entity\Coordinator;
use App\Entity\Shelter;
use App\Enum\CoordinatorRole;
use App\Enum\EventChannel;
use App\Enum\EventType;
use App\Repository\CoordinatorRepository;
use App\Repository\OrganizationRepository;
use App\Repository\ShelterRepository;
use App\Service\CryptoLedgerService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Gestión de refugios / centros de acopio (módulo de administración).
 *
 * Listado, creación, edición e inactivación (soft-delete). La inactivación
 * conserva el refugio por integridad histórica: el censo (Beneficiary) y el
 * ledger inmutable (InventoryEvent) lo referencian, por lo que nunca se borra
 * físicamente; solo se oculta de los selectores operativos.
 */
#[Route('/api/shelters')]
class ShelterController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ShelterRepository $shelterRepository,
        private readonly OrganizationRepository $organizationRepository,
        private readonly CoordinatorRepository $coordinatorRepository,
        private readonly CryptoLedgerService $cryptoLedgerService,
    ) {
    }

    /**
     * Lista refugios. Por defecto devuelve todos (para el módulo admin).
     * Con ?soloActivos=true devuelve solo los activos (para los selectores
     * operativos: censo, despacho, padrón).
     */
    #[Route('', name: 'api_shelters_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $criteria = [];
        if ($request->query->getBoolean('soloActivos')) {
            $criteria['activo'] = true;
        }

        $data = array_map(
            $this->serialize(...),
            $this->shelterRepository->findBy($criteria, ['nombre' => 'ASC']),
        );

        return $this->json($data);
    }

    #[Route('', name: 'api_shelters_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        try {
            $body = $request->toArray();
        } catch (\JsonException) {
            return $this->json(['error' => 'JSON inválido.'], Response::HTTP_BAD_REQUEST);
        }

        $error = $this->validarCampos($body);
        if ($error !== null) {
            return $this->json(['error' => $error], Response::HTTP_BAD_REQUEST);
        }

        // Validar firma para trazabilidad
        if (!isset($body['coordinatorId']) || !isset($body['firma'])) {
            return $this->json(['error' => 'Se requiere coordinatorId y firma para trazabilidad.'], Response::HTTP_BAD_REQUEST);
        }

        $coordinator = $this->coordinatorRepository->find($body['coordinatorId']);
        if ($coordinator === null) {
            return $this->json(['error' => 'Coordinador no encontrado.'], Response::HTTP_BAD_REQUEST);
        }

        // Verificar rol admin
        if (!$coordinator->hasRole(CoordinatorRole::ADMIN)) {
            return $this->json(['error' => 'Solo administradores pueden crear refugios.'], Response::HTTP_FORBIDDEN);
        }

        $shelter = new Shelter();
        $this->aplicarCampos($shelter, $body);

        $organizationError = $this->aplicarOrganizacion($shelter, $body);
        if ($organizationError !== null) {
            return $this->json(['error' => $organizationError], Response::HTTP_BAD_REQUEST);
        }

        $this->entityManager->persist($shelter);
        $this->entityManager->flush();

        // Registrar evento de configuración en el ledger
        // Usar los mismos datos que envió el frontend para que el payload canónico coincida
        $configData = [
            'nombre' => $body['nombre'],
            'zona' => $body['zona'],
            'latitud' => $body['latitud'],
            'longitud' => $body['longitud'],
            'capacidadCensada' => $body['capacidadCensada'],
            'organizationId' => $body['organizationId'],
        ];

        $eventData = new EventData(
            tipo: EventType::CONFIG_SHELTER_CREATE,
            item: null,
            cantidad: null,
            unidad: null,
            shelter: null,
            organization: null,
            canalOrigen: EventChannel::WEB,
            firmaOrigen: $body['firma'],
            coordinatorOrigen: $coordinator,
            datosConfiguracion: $configData,
        );

        try {
            $this->cryptoLedgerService->appendEvent($eventData);
        } catch (\Exception $e) {
            // Si falla el ledger, intentar revertir el cambio si el EntityManager aún está abierto
            if ($this->entityManager->isOpen()) {
                $this->entityManager->remove($shelter);
                $this->entityManager->flush();
            }
            return $this->json(['error' => 'Error al registrar en ledger: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->json($this->serialize($shelter), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'api_shelters_update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $shelter = $this->shelterRepository->find($id);
        if ($shelter === null) {
            return $this->json(['error' => 'Refugio no encontrado.'], Response::HTTP_NOT_FOUND);
        }

        try {
            $body = $request->toArray();
        } catch (\JsonException) {
            return $this->json(['error' => 'JSON inválido.'], Response::HTTP_BAD_REQUEST);
        }

        $error = $this->validarCampos($body);
        if ($error !== null) {
            return $this->json(['error' => $error], Response::HTTP_BAD_REQUEST);
        }

        // Validar firma para trazabilidad
        if (!isset($body['coordinatorId']) || !isset($body['firma'])) {
            return $this->json(['error' => 'Se requiere coordinatorId y firma para trazabilidad.'], Response::HTTP_BAD_REQUEST);
        }

        $coordinator = $this->coordinatorRepository->find($body['coordinatorId']);
        if ($coordinator === null) {
            return $this->json(['error' => 'Coordinador no encontrado.'], Response::HTTP_BAD_REQUEST);
        }

        // Verificar rol admin
        if (!$coordinator->hasRole(CoordinatorRole::ADMIN)) {
            return $this->json(['error' => 'Solo administradores pueden editar refugios.'], Response::HTTP_FORBIDDEN);
        }

        // Guardar estado anterior para el ledger
        $estadoAnterior = $this->serialize($shelter);

        $this->aplicarCampos($shelter, $body);

        $organizationError = $this->aplicarOrganizacion($shelter, $body);
        if ($organizationError !== null) {
            return $this->json(['error' => $organizationError], Response::HTTP_BAD_REQUEST);
        }

        $this->entityManager->flush();

        // Registrar evento de configuración en el ledger
        $configData = [
            'shelter_id' => $shelter->getId(),
            'estado_anterior' => $estadoAnterior,
            'estado_nuevo' => $this->serialize($shelter),
        ];

        $eventData = new EventData(
            tipo: EventType::CONFIG_SHELTER_UPDATE,
            item: null,
            cantidad: null,
            unidad: null,
            shelter: null,
            organization: null,
            canalOrigen: EventChannel::WEB,
            firmaOrigen: $body['firma'],
            coordinatorOrigen: $coordinator,
            datosConfiguracion: $configData,
        );

        try {
            $this->cryptoLedgerService->appendEvent($eventData);
        } catch (\Exception $e) {
            // Si falla el ledger, revertir el cambio (necesitaríamos restore del estado anterior)
            return $this->json(['error' => 'Error al registrar en ledger: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->json($this->serialize($shelter));
    }

    /**
     * Activa o inactiva un refugio (soft-delete). Body: { "activo": bool, "coordinatorId": int, "firma": string }.
     */
    #[Route('/{id}/estado', name: 'api_shelters_estado', methods: ['PATCH'], requirements: ['id' => '\d+'])]
    public function cambiarEstado(int $id, Request $request): JsonResponse
    {
        $shelter = $this->shelterRepository->find($id);
        if ($shelter === null) {
            return $this->json(['error' => 'Refugio no encontrado.'], Response::HTTP_NOT_FOUND);
        }

        try {
            $body = $request->toArray();
        } catch (\JsonException) {
            return $this->json(['error' => 'JSON inválido.'], Response::HTTP_BAD_REQUEST);
        }

        if (!\array_key_exists('activo', $body) || !\is_bool($body['activo'])) {
            return $this->json(['error' => 'El campo "activo" (boolean) es obligatorio.'], Response::HTTP_BAD_REQUEST);
        }

        // Validar firma para trazabilidad
        if (!isset($body['coordinatorId']) || !isset($body['firma'])) {
            return $this->json(['error' => 'Se requiere coordinatorId y firma para trazabilidad.'], Response::HTTP_BAD_REQUEST);
        }

        $coordinator = $this->coordinatorRepository->find($body['coordinatorId']);
        if ($coordinator === null) {
            return $this->json(['error' => 'Coordinador no encontrado.'], Response::HTTP_BAD_REQUEST);
        }

        // Verificar rol admin
        if (!$coordinator->hasRole(CoordinatorRole::ADMIN)) {
            return $this->json(['error' => 'Solo administradores pueden cambiar el estado de refugios.'], Response::HTTP_FORBIDDEN);
        }

        $activoAnterior = $shelter->isActivo();
        $shelter->setActivo($body['activo']);
        $this->entityManager->flush();

        // Registrar evento de configuración en el ledger
        $configData = [
            'shelter_id' => $shelter->getId(),
            'activo_anterior' => $activoAnterior,
            'activo_nuevo' => $shelter->isActivo(),
        ];

        $eventData = new EventData(
            tipo: EventType::CONFIG_SHELTER_INACTIVATE,
            item: null,
            cantidad: null,
            unidad: null,
            shelter: null,
            organization: null,
            canalOrigen: EventChannel::WEB,
            firmaOrigen: $body['firma'],
            coordinatorOrigen: $coordinator,
            datosConfiguracion: $configData,
        );

        try {
            $this->cryptoLedgerService->appendEvent($eventData);
        } catch (\Exception $e) {
            // Si falla el ledger, revertir el cambio
            $shelter->setActivo($activoAnterior);
            $this->entityManager->flush();
            return $this->json(['error' => 'Error al registrar en ledger: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->json($this->serialize($shelter));
    }

    /**
     * @param array<string, mixed> $body
     */
    private function validarCampos(array $body): ?string
    {
        $nombre = isset($body['nombre']) ? trim((string) $body['nombre']) : '';
        $zona = isset($body['zona']) ? trim((string) $body['zona']) : '';

        if ($nombre === '') {
            return 'El nombre del refugio es obligatorio.';
        }
        if ($zona === '') {
            return 'La zona del refugio es obligatoria.';
        }

        return null;
    }

    /**
     * @param array<string, mixed> $body
     */
    private function aplicarCampos(Shelter $shelter, array $body): void
    {
        $shelter->setNombre(trim((string) $body['nombre']));
        $shelter->setZona(trim((string) $body['zona']));

        $latitud = $body['latitud'] ?? null;
        $longitud = $body['longitud'] ?? null;
        $shelter->setLatitud($latitud !== null && $latitud !== '' ? (string) $latitud : null);
        $shelter->setLongitud($longitud !== null && $longitud !== '' ? (string) $longitud : null);

        $capacidad = $body['capacidadCensada'] ?? null;
        $shelter->setCapacidadCensada($capacidad !== null && $capacidad !== '' ? (int) $capacidad : null);
    }

    /**
     * @param array<string, mixed> $body
     */
    private function aplicarOrganizacion(Shelter $shelter, array $body): ?string
    {
        $organizationId = $body['organizationId'] ?? null;
        if ($organizationId === null || $organizationId === '') {
            $shelter->setOrganization(null);

            return null;
        }

        $organization = $this->organizationRepository->find($organizationId);
        if ($organization === null) {
            return 'organizationId no encontrado.';
        }

        $shelter->setOrganization($organization);

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(Shelter $s): array
    {
        return [
            'id' => $s->getId(),
            'nombre' => $s->getNombre(),
            'zona' => $s->getZona(),
            'latitud' => $s->getLatitud(),
            'longitud' => $s->getLongitud(),
            'capacidadCensada' => $s->getCapacidadCensada(),
            'activo' => $s->isActivo(),
            'organizationId' => $s->getOrganization()?->getId(),
            'organizationNombre' => $s->getOrganization()?->getNombre(),
        ];
    }
}
