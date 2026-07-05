<?php

namespace App\Repository;

use App\Entity\ItemThreshold;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ItemThreshold>
 */
class ItemThresholdRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ItemThreshold::class);
    }

    /**
     * Busca un umbral por nombre de item.
     */
    public function findByItem(string $item): ?ItemThreshold
    {
        return $this->findOneBy(['item' => $item]);
    }

    /**
     * Obtiene todos los umbrales ordenados por item.
     */
    public function findAllOrdered(): array
    {
        return $this->findBy([], ['item' => 'ASC']);
    }
}
