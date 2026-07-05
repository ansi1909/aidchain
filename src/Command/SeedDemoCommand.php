<?php

namespace App\Command;

use App\Entity\ItemThreshold;
use App\Entity\Organization;
use App\Entity\Shelter;
use App\Enum\OrganizationType;
use App\Repository\ItemThresholdRepository;
use App\Repository\OrganizationRepository;
use App\Repository\ShelterRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Inserta organizaciones y refugios de demostración para poder probar la
 * app de terreno (Fase 7) sin datos previos. Es idempotente: no duplica si
 * ya existen registros.
 */
#[AsCommand(
    name: 'app:seed:demo',
    description: 'Carga organizaciones y refugios de demostración para la app de terreno.',
)]
class SeedDemoCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly OrganizationRepository $organizationRepository,
        private readonly ShelterRepository $shelterRepository,
        private readonly ItemThresholdRepository $thresholdRepository,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $orgsCreadas = 0;
        if ($this->organizationRepository->count([]) === 0) {
            $organizaciones = [
                ['Gobierno Nacional de Emergencias', OrganizationType::GOBIERNO],
                ['Cruz Roja', OrganizationType::CRUZ_ROJA],
                ['ONG Manos Solidarias', OrganizationType::ONG],
                ['Voluntariado Comunitario Zona Norte', OrganizationType::VOLUNTARIADO],
            ];
            foreach ($organizaciones as [$nombre, $tipo]) {
                $org = (new Organization())
                    ->setNombre($nombre)
                    ->setTipo($tipo)
                    // Placeholder: la clave real del coordinador se registra por su cuenta.
                    ->setPublicKey('-----BEGIN PUBLIC KEY-----\nPLACEHOLDER\n-----END PUBLIC KEY-----');
                $this->entityManager->persist($org);
                ++$orgsCreadas;
            }
        }

        $sheltersCreados = 0;
        if ($this->shelterRepository->count([]) === 0) {
            $refugios = [
                ['Refugio Central Zona A', 'Zona A', '10.4806000', '-66.9036000', 250, 'Gobierno Nacional de Emergencias'],
                ['Centro de Acopio Zona B', 'Zona B', '10.5000000', '-66.9167000', 400, 'Cruz Roja'],
                ['Refugio Escuela Zona C', 'Zona C', '10.4650000', '-66.8800000', 120, null], // compartido, sin org
            ];
            foreach ($refugios as [$nombre, $zona, $lat, $lon, $cap, $orgNombre]) {
                $shelter = (new Shelter())
                    ->setNombre($nombre)
                    ->setZona($zona)
                    ->setLatitud($lat)
                    ->setLongitud($lon)
                    ->setCapacidadCensada($cap);
                if ($orgNombre !== null) {
                    $org = $this->organizationRepository->findOneBy(['nombre' => $orgNombre]);
                    if ($org !== null) {
                        $shelter->setOrganization($org);
                    }
                }
                $this->entityManager->persist($shelter);
                ++$sheltersCreados;
            }
        }

        $thresholdsCreados = 0;
        if ($this->thresholdRepository->count([]) === 0) {
            $umbrales = [
                ['Agua potable', '20', 'litros', 24, 'Máximo diario por persona'],
                ['Alimentos no perecederos', '5', 'kg', 48, 'Máximo cada 48 horas por persona'],
                ['Medicamentos básicos', '10', 'unidades', 72, 'Máximo cada 72 horas por persona'],
                ['Kits de higiene', '2', 'kits', 168, 'Máximo semanal por persona'],
            ];
            foreach ($umbrales as [$item, $cantidad, $unidad, $horas, $descripcion]) {
                $threshold = (new ItemThreshold())
                    ->setItem($item)
                    ->setCantidadMaxima($cantidad)
                    ->setUnidad($unidad)
                    ->setVentanaHoras($horas)
                    ->setDescripcion($descripcion);
                $this->entityManager->persist($threshold);
                ++$thresholdsCreados;
            }
        }

        $this->entityManager->flush();

        $io->success(sprintf(
            'Seed completo. Organizaciones creadas: %d. Refugios creados: %d. Umbrales creados: %d.',
            $orgsCreadas,
            $sheltersCreados,
            $thresholdsCreados,
        ));

        return Command::SUCCESS;
    }
}
