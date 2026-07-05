<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Stock disponible de un item en un refugio.
 * Se actualiza cuando llegan despachos logísticos (OUT_DISPATCH)
 * y se decrementa cuando se entregan a beneficiarios (OUT_BENEFICIARY).
 */
#[ORM\Entity(repositoryClass: \App\Repository\ShelterStockRepository::class)]
#[ORM\Table(name: 'shelter_stock')]
#[ORM\Index(columns: ['shelter_id', 'item'], name: 'idx_shelter_item')]
class ShelterStock
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Shelter::class)]
    #[ORM\JoinColumn(name: 'shelter_id', nullable: false)]
    private Shelter $shelter;

    #[ORM\Column(type: 'string', length: 120)]
    private string $item;

    #[ORM\Column(type: 'string', length: 20)]
    private string $unidad;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 3)]
    private string $cantidadDisponible;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getShelter(): Shelter
    {
        return $this->shelter;
    }

    public function setShelter(Shelter $shelter): static
    {
        $this->shelter = $shelter;
        return $this;
    }

    public function getItem(): string
    {
        return $this->item;
    }

    public function setItem(string $item): static
    {
        $this->item = $item;
        return $this;
    }

    public function getUnidad(): string
    {
        return $this->unidad;
    }

    public function setUnidad(string $unidad): static
    {
        $this->unidad = $unidad;
        return $this;
    }

    public function getCantidadDisponible(): string
    {
        return $this->cantidadDisponible;
    }

    public function setCantidadDisponible(string $cantidadDisponible): static
    {
        $this->cantidadDisponible = $cantidadDisponible;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    /**
     * Incrementa el stock disponible (cuando llega un despacho logístico).
     */
    public function incrementar(string $cantidad): void
    {
        $actual = (float) $this->cantidadDisponible;
        $this->cantidadDisponible = (string) ($actual + (float) $cantidad);
        $this->updatedAt = new \DateTimeImmutable();
    }

    /**
     * Decrementa el stock disponible (cuando se entrega a un beneficiario).
     * @throws \RuntimeException si no hay suficiente stock.
     */
    public function decrementar(string $cantidad): void
    {
        $actual = (float) $this->cantidadDisponible;
        $aRestar = (float) $cantidad;

        if ($actual < $aRestar) {
            throw new \RuntimeException(sprintf(
                'Stock insuficiente: disponible %s %s, solicitado %s %s',
                $actual,
                $this->unidad,
                $aRestar,
                $this->unidad,
            ));
        }

        $this->cantidadDisponible = (string) ($actual - $aRestar);
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
