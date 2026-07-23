<?php

namespace App\Repository;

use App\Entity\Beneficiary;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Beneficiary>
 */
class BeneficiaryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Beneficiary::class);
    }

    public function findOneByToken(string $token): ?Beneficiary
    {
        return $this->findOneBy(['beneficiaryToken' => $token]);
    }

    /**
     * @return Beneficiary[]
     */
    public function findRepresentantesByShelterAndQuery(?int $shelterId, ?string $query, int $limit = 30): array
    {
        $qb = $this->createQueryBuilder('b')
            ->andWhere('b.esRepresentante = :esRep')
            ->andWhere('b.representante IS NULL')
            ->setParameter('esRep', true)
            ->orderBy('b.createdAt', 'DESC')
            ->setMaxResults($limit);

        if ($shelterId !== null) {
            $qb->andWhere('b.shelter = :shelterId')
               ->setParameter('shelterId', $shelterId);
        }

        if ($query !== null && trim($query) !== '') {
            $q = '%' . trim($query) . '%';
            $qb->andWhere('b.nombre LIKE :q OR b.documento LIKE :q')
               ->setParameter('q', $q);
        }

        return $qb->getQuery()->getResult();
    }

    public function findRepresentanteByDocumentoAndShelter(string $documento, ?int $shelterId): ?Beneficiary
    {
        $qb = $this->createQueryBuilder('b')
            ->andWhere('b.documento = :doc')
            ->andWhere('b.esRepresentante = :esRep')
            ->andWhere('b.representante IS NULL')
            ->setParameter('doc', trim($documento))
            ->setParameter('esRep', true);

        if ($shelterId !== null) {
            $qb->andWhere('b.shelter = :shelterId')
               ->setParameter('shelterId', $shelterId);
        }

        return $qb->getQuery()->getOneOrNullResult();
    }
}
