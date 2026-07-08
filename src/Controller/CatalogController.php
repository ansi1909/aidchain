<?php

namespace App\Controller;

use App\Entity\Organization;
use App\Repository\OrganizationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Endpoints de solo lectura para poblar los selectores de la app de terreno.
 * El listado de refugios vive en ShelterController (módulo de administración).
 */
#[Route('/api')]
class CatalogController extends AbstractController
{
    #[Route('/organizations', name: 'api_organizations_list', methods: ['GET'])]
    public function organizations(OrganizationRepository $repository): JsonResponse
    {
        $data = array_map(
            static fn (Organization $o): array => [
                'id' => $o->getId(),
                'nombre' => $o->getNombre(),
                'tipo' => $o->getTipo()->value,
            ],
            $repository->findBy([], ['nombre' => 'ASC']),
        );

        return $this->json($data);
    }
}
