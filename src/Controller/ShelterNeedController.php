<?php

namespace App\Controller;

use App\Enum\ShelterNeedPriority;
use App\Enum\ShelterNeedStatus;
use App\Entity\ShelterNeed;
use App\Repository\ShelterNeedRepository;
use App\Repository\ShelterRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Endpoints para gestionar necesidades de refugios.
 * Solo los encargados de refugio pueden crear/actualizar/eliminar necesidades.
 */
#[Route('/api')]
class ShelterNeedController extends AbstractController
{
    public function __construct(
        private ShelterNeedRepository $needRepository,
        private ShelterRepository $shelterRepository,
    ) {
    }

    #[Route('/shelters/{id}/needs', name: 'api_shelter_needs_list', methods: ['GET'])]
    public function list(int $id, Request $request): JsonResponse
    {
        $shelter = $this->shelterRepository->find($id);
        if (!$shelter) {
            return $this->json(['error' => 'Refugio no encontrado'], 404);
        }

        $estado = $request->query->get('estado');
        $estadoEnum = $estado ? ShelterNeedStatus::tryFrom($estado) : null;

        $needs = $this->needRepository->findByShelterAndEstado($id, $estadoEnum);

        $data = array_map(
            static fn (ShelterNeed $n): array => [
                'id' => $n->getId(),
                'item' => $n->getItem(),
                'unidad' => $n->getUnidad(),
                'cantidadRequerida' => $n->getCantidadRequerida(),
                'cantidadRecibida' => $n->getCantidadRecibida(),
                'prioridad' => $n->getPrioridad()->value,
                'prioridadLabel' => $n->getPrioridad()->getLabel(),
                'estado' => $n->getEstado()->value,
                'estadoLabel' => $n->getEstado()->getLabel(),
                'notas' => $n->getNotas(),
                'porcentajeSatisfaccion' => $n->getPorcentajeSatisfaccion(),
                'fechaReporte' => $n->getFechaReporte()->format('Y-m-d H:i:s'),
                'fechaActualizacion' => $n->getFechaActualizacion()->format('Y-m-d H:i:s'),
            ],
            $needs,
        );

        return $this->json($data);
    }

    #[Route('/shelters/{id}/needs', name: 'api_shelter_needs_create', methods: ['POST'])]
    public function create(int $id, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $shelter = $this->shelterRepository->find($id);
        if (!$shelter) {
            return $this->json(['error' => 'Refugio no encontrado'], 404);
        }

        $data = json_decode($request->getContent(), true);
        if (!$data) {
            return $this->json(['error' => 'JSON inválido'], 400);
        }

        $item = trim((string) ($data['item'] ?? ''));
        $unidad = trim((string) ($data['unidad'] ?? ''));
        $cantidadRequerida = $data['cantidadRequerida'] ?? null;
        $prioridad = $data['prioridad'] ?? null;
        $notas = $data['notas'] ?? null;

        if ($item === '' || $unidad === '' || $cantidadRequerida === null || $cantidadRequerida === '' || !$prioridad) {
            return $this->json(['error' => 'Faltan campos requeridos: item, cantidadRequerida, unidad, prioridad'], 400);
        }

        $prioridadEnum = ShelterNeedPriority::tryFrom($prioridad);
        if (!$prioridadEnum) {
            return $this->json(['error' => 'Prioridad inválida. Valores: baja, media, alta, critica'], 400);
        }

        // Verificar si ya existe una necesidad para este item en este refugio
        $existente = $this->needRepository->findByShelterAndItem($id, $item);
        if ($existente) {
            return $this->json(['error' => 'Ya existe una necesidad para este item en este refugio'], 409);
        }

        $need = new ShelterNeed();
        $need->setShelter($shelter);
        $need->setItem($item);
        $need->setUnidad($unidad);
        $need->setCantidadRequerida((string) $cantidadRequerida);
        $need->setPrioridad($prioridadEnum);
        $need->setEstado(ShelterNeedStatus::PENDIENTE);
        if ($notas) {
            $need->setNotas($notas);
        }

        $em->persist($need);
        $em->flush();

        return $this->json([
            'id' => $need->getId(),
            'item' => $need->getItem(),
            'unidad' => $need->getUnidad(),
            'cantidadRequerida' => $need->getCantidadRequerida(),
            'cantidadRecibida' => $need->getCantidadRecibida(),
            'prioridad' => $need->getPrioridad()->value,
            'estado' => $need->getEstado()->value,
        ], 201);
    }

    #[Route('/shelters/{id}/needs/{needId}', name: 'api_shelter_needs_update', methods: ['PUT'])]
    public function update(int $id, int $needId, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $shelter = $this->shelterRepository->find($id);
        if (!$shelter) {
            return $this->json(['error' => 'Refugio no encontrado'], 404);
        }

        $need = $this->needRepository->find($needId);
        if (!$need) {
            return $this->json(['error' => 'Necesidad no encontrada'], 404);
        }

        if ($need->getShelter()->getId() !== $id) {
            return $this->json(['error' => 'La necesidad no pertenece a este refugio'], 400);
        }

        $data = json_decode($request->getContent(), true);
        if (!$data) {
            return $this->json(['error' => 'JSON inválido'], 400);
        }

        if (isset($data['item']) && trim((string) $data['item']) !== '') {
            $need->setItem(trim((string) $data['item']));
        }

        if (isset($data['unidad']) && trim((string) $data['unidad']) !== '') {
            $need->setUnidad(trim((string) $data['unidad']));
        }

        if (isset($data['cantidadRequerida'])) {
            $need->setCantidadRequerida((string) $data['cantidadRequerida']);
        }

        if (isset($data['prioridad'])) {
            $prioridadEnum = ShelterNeedPriority::tryFrom($data['prioridad']);
            if (!$prioridadEnum) {
                return $this->json(['error' => 'Prioridad inválida'], 400);
            }
            $need->setPrioridad($prioridadEnum);
        }

        if (isset($data['notas'])) {
            $need->setNotas($data['notas'] ?: null);
        }

        $em->flush();

        return $this->json([
            'id' => $need->getId(),
            'item' => $need->getItem(),
            'unidad' => $need->getUnidad(),
            'cantidadRequerida' => $need->getCantidadRequerida(),
            'cantidadRecibida' => $need->getCantidadRecibida(),
            'prioridad' => $need->getPrioridad()->value,
            'estado' => $need->getEstado()->value,
        ]);
    }

    #[Route('/shelters/{id}/needs/{needId}', name: 'api_shelter_needs_delete', methods: ['DELETE'])]
    public function delete(int $id, int $needId, EntityManagerInterface $em): JsonResponse
    {
        $shelter = $this->shelterRepository->find($id);
        if (!$shelter) {
            return $this->json(['error' => 'Refugio no encontrado'], 404);
        }

        $need = $this->needRepository->find($needId);
        if (!$need) {
            return $this->json(['error' => 'Necesidad no encontrada'], 404);
        }

        if ($need->getShelter()->getId() !== $id) {
            return $this->json(['error' => 'La necesidad no pertenece a este refugio'], 400);
        }

        $em->remove($need);
        $em->flush();

        return $this->json(null, 204);
    }

    #[Route('/needs/dashboard', name: 'api_needs_dashboard', methods: ['GET'])]
    public function dashboard(): JsonResponse
    {
        $needs = $this->needRepository->findForDashboard();

        $data = array_map(
            static fn (ShelterNeed $n): array => [
                'id' => $n->getId(),
                'item' => $n->getItem(),
                'unidad' => $n->getUnidad(),
                'cantidadRequerida' => $n->getCantidadRequerida(),
                'cantidadRecibida' => $n->getCantidadRecibida(),
                'prioridad' => $n->getPrioridad()->value,
                'prioridadLabel' => $n->getPrioridad()->getLabel(),
                'estado' => $n->getEstado()->value,
                'estadoLabel' => $n->getEstado()->getLabel(),
                'notas' => $n->getNotas(),
                'porcentajeSatisfaccion' => $n->getPorcentajeSatisfaccion(),
                'fechaReporte' => $n->getFechaReporte()->format('Y-m-d H:i:s'),
                'fechaActualizacion' => $n->getFechaActualizacion()->format('Y-m-d H:i:s'),
                'shelter' => [
                    'id' => $n->getShelter()->getId(),
                    'nombre' => $n->getShelter()->getNombre(),
                    'zona' => $n->getShelter()->getZona(),
                ],
            ],
            $needs,
        );

        return $this->json($data);
    }
}
