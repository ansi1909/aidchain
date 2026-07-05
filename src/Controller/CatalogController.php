<?php

namespace App\Controller;

use App\Entity\Organization;
use App\Entity\Shelter;
use App\Repository\OrganizationRepository;
use App\Repository\ShelterRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Endpoints de solo lectura para poblar los selectores de la app de terreno
 * (organizaciones y refugios). Los datos se cargan con app:seed:demo o por
 * administración; aquí solo se listan.
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

    #[Route('/shelters', name: 'api_shelters_list', methods: ['GET'])]
    public function shelters(ShelterRepository $repository): JsonResponse
    {
        $data = array_map(
            static fn (Shelter $s): array => [
                'id' => $s->getId(),
                'nombre' => $s->getNombre(),
                'zona' => $s->getZona(),
                'latitud' => $s->getLatitud(),
                'longitud' => $s->getLongitud(),
                'capacidadCensada' => $s->getCapacidadCensada(),
                'organizationId' => $s->getOrganization()?->getId(),
                'organizationNombre' => $s->getOrganization()?->getNombre(),
            ],
            $repository->findBy([], ['nombre' => 'ASC']),
        );

        return $this->json($data);
    }
}
