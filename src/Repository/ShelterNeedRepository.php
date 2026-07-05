<?php

namespace App\Repository;

use App\Entity\ShelterNeed;
use App\Enum\ShelterNeedStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ShelterNeed>
 */
class ShelterNeedRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ShelterNeed::class);
    }

    /**
     * Busca una necesidad pendiente para un refugio e item específico.
     */
    public function findPendingByShelterAndItem(int $shelterId, string $item): ?ShelterNeed
    {
        return $this->findOneBy([
            'shelter' => $shelterId,
            'item' => $item,
            'estado' => ShelterNeedStatus::PENDIENTE,
        ]);
    }

    /**
     * Busca cualquier necesidad (de cualquier estado) para un refugio e item.
     */
    public function findByShelterAndItem(int $shelterId, string $item): ?ShelterNeed
    {
        return $this->findOneBy([
            'shelter' => $shelterId,
            'item' => $item,
        ]);
    }

    /**
     * Obtiene necesidades de un refugio filtradas por estado.
     */
    public function findByShelterAndEstado(int $shelterId, ?ShelterNeedStatus $estado = null): array
    {
        $criteria = ['shelter' => $shelterId];
        if ($estado !== null) {
            $criteria['estado'] = $estado;
        }
        return $this->findBy($criteria, ['prioridad' => 'DESC', 'fechaReporte' => 'DESC']);
    }

    /**
     * Obtiene todas las necesidades pendientes ordenadas por prioridad.
     */
    public function findPendingOrderedByPriority(): array
    {
        return $this->findBy(
            ['estado' => ShelterNeedStatus::PENDIENTE],
            ['prioridad' => 'DESC', 'fechaReporte' => 'ASC']
        );
    }

    /**
     * Vista consolidada para el dashboard: todas las necesidades con info del refugio.
     */
    public function findForDashboard(): array
    {
        $qb = $this->createQueryBuilder('sn')
            ->innerJoin('sn.shelter', 's')
            ->addSelect('s')
            ->orderBy('sn.prioridad', 'DESC')
            ->addOrderBy('sn.estado', 'ASC')
            ->addOrderBy('sn.fechaReporte', 'DESC');

        return $qb->getQuery()->getResult();
    }
}
