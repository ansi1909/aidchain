<?php

namespace App\Repository;

use App\Entity\ShelterStock;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ShelterStock>
 */
class ShelterStockRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ShelterStock::class);
    }

    /**
     * Busca el stock de un item en un refugio específico.
     */
    public function findByShelterAndItem(int $shelterId, string $item): ?ShelterStock
    {
        return $this->findOneBy([
            'shelter' => $shelterId,
            'item' => $item,
        ]);
    }

    /**
     * Obtiene todo el stock de un refugio.
     * @return ShelterStock[]
     */
    public function findByShelter(int $shelterId): array
    {
        return $this->findBy(['shelter' => $shelterId], ['item' => 'ASC']);
    }

    /**
     * Vista consolidada para el dashboard de inventario: todo el stock con info del refugio.
     * @return ShelterStock[]
     */
    public function findForDashboard(): array
    {
        return $this->createQueryBuilder('ss')
            ->innerJoin('ss.shelter', 's')
            ->addSelect('s')
            ->orderBy('s.nombre', 'ASC')
            ->addOrderBy('ss.item', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Crea o actualiza el stock de un item en un refugio.
     * Si no existe, lo crea con la cantidad especificada.
     * Si existe, incrementa la cantidad.
     */
    public function incrementarStock(int $shelterId, string $item, string $cantidad, string $unidad): ShelterStock
    {
        $stock = $this->findByShelterAndItem($shelterId, $item);

        if ($stock === null) {
            $stock = new ShelterStock();
            $stock->setShelter($this->getEntityManager()->getReference(\App\Entity\Shelter::class, $shelterId));
            $stock->setItem($item);
            $stock->setUnidad($unidad);
            $stock->setCantidadDisponible($cantidad);
            $this->getEntityManager()->persist($stock);
        } else {
            $stock->incrementar($cantidad);
        }

        $this->getEntityManager()->flush();
        return $stock;
    }

    /**
     * Decrementa el stock de un item en un refugio.
     * @throws \RuntimeException si no hay suficiente stock.
     */
    public function decrementarStock(int $shelterId, string $item, string $cantidad): void
    {
        $stock = $this->findByShelterAndItem($shelterId, $item);

        if ($stock === null) {
            throw new \RuntimeException(sprintf(
                'No existe stock para el item "%s" en el refugio %d',
                $item,
                $shelterId,
            ));
        }

        $stock->decrementar($cantidad);
        $this->getEntityManager()->flush();
    }
}
