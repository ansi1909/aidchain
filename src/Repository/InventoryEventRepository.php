<?php

namespace App\Repository;

use App\Entity\InventoryEvent;
use App\Enum\EventState;
use App\Enum\EventType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<InventoryEvent>
 */
class InventoryEventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InventoryEvent::class);
    }

    /**
     * Devuelve el último evento encadenado (por fecha) para poder calcular
     * el hashAnterior del próximo bloque. Ver CryptoLedgerService (Fase 2).
     */
    public function findUltimoEvento(): ?InventoryEvent
    {
        return $this->createQueryBuilder('e')
            ->orderBy('e.createdAt', 'DESC')
            // Desempate determinista: createdAt tiene precisión de segundos, por
            // lo que varios eventos en el mismo segundo empatarían. El id (auto-
            // incremental) garantiza que siempre devolvemos el último real.
            ->addOrderBy('e.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Despachos pendientes de confirmación de recepción (Fase 4): eventos
     * OUT_DISPATCH que siguen EN_TRANSITO (aún sin firma de destino).
     * Opcionalmente se filtra por el refugio de destino.
     *
     * @return InventoryEvent[]
     */
    public function findDespachosPendientes(?int $shelterId = null): array
    {
        $qb = $this->createQueryBuilder('e')
            ->andWhere('e.tipo = :tipo')
            ->andWhere('e.estado = :estado')
            ->setParameter('tipo', EventType::OUT_DISPATCH->value)
            ->setParameter('estado', EventState::EN_TRANSITO->value)
            ->orderBy('e.createdAt', 'DESC')
            ->addOrderBy('e.id', 'DESC');

        if ($shelterId !== null) {
            $qb->andWhere('e.shelter = :shelter')->setParameter('shelter', $shelterId);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Despacho EN_TRANSITO asociado a un identificador de lote (el que viaja en
     * el QR físico). Devuelve el más reciente si hubiera más de uno.
     */
    public function findDespachoPendienteByLote(string $loteId): ?InventoryEvent
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.loteId = :lote')
            ->andWhere('e.tipo = :tipo')
            ->andWhere('e.estado = :estado')
            ->setParameter('lote', $loteId)
            ->setParameter('tipo', EventType::OUT_DISPATCH->value)
            ->setParameter('estado', EventState::EN_TRANSITO->value)
            ->orderBy('e.createdAt', 'DESC')
            ->addOrderBy('e.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Suma la cantidad recibida por un beneficiario para un item específico
     * en una ventana de horas. Solo cuenta OUT_BENEFICIARY (entregas individuales).
     * Base del control de "doble cobro" (Fase 3).
     */
    public function sumaCantidadPorBeneficiario(string $beneficiaryToken, string $item, int $horas): float
    {
        $desde = new \DateTimeImmutable(sprintf('-%d hours', $horas));

        $resultado = $this->createQueryBuilder('e')
            ->select('COALESCE(SUM(e.cantidad), 0) as total')
            ->join('e.beneficiary', 'b')
            ->andWhere('b.beneficiaryToken = :token')
            ->andWhere('e.item = :item')
            ->andWhere('e.createdAt >= :desde')
            ->andWhere('e.tipo = :tipo')
            ->setParameter('token', $beneficiaryToken)
            ->setParameter('item', $item)
            ->setParameter('desde', $desde)
            ->setParameter('tipo', \App\Enum\EventType::OUT_BENEFICIARY->value)
            ->getQuery()
            ->getSingleScalarResult();

        return (float) $resultado;
    }
}
