<?php

namespace App\Repository;

use App\Entity\AuditAlert;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AuditAlert>
 */
class AuditAlertRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AuditAlert::class);
    }

    /** @return AuditAlert[] */
    public function findActivas(): array
    {
        return $this->findBy(['resuelto' => false], ['createdAt' => 'DESC']);
    }
}
