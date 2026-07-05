<?php

namespace App\Entity;

use App\Repository\ItemThresholdRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Umbrales máximos de consumo por insumo para control de doble cobro (Fase 3).
 * Define cuánto puede recibir un beneficiario de un item específico en una ventana de tiempo.
 */
#[ORM\Entity(repositoryClass: ItemThresholdRepository::class)]
#[ORM\Table(name: 'item_threshold')]
#[ORM\Index(name: 'idx_item_threshold_item', columns: ['item'])]
class ItemThreshold
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255, unique: true)]
    private string $item;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 3)]
    private string $cantidadMaxima;

    #[ORM\Column(type: 'string', length: 20)]
    private string $unidad;

    #[ORM\Column(type: 'integer')]
    private int $ventanaHoras = 24; // Ventana de tiempo en horas (default 24h)

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $descripcion = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getCantidadMaxima(): string
    {
        return $this->cantidadMaxima;
    }

    public function setCantidadMaxima(string $cantidadMaxima): static
    {
        $this->cantidadMaxima = $cantidadMaxima;
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

    public function getVentanaHoras(): int
    {
        return $this->ventanaHoras;
    }

    public function setVentanaHoras(int $ventanaHoras): static
    {
        $this->ventanaHoras = $ventanaHoras;
        return $this;
    }

    public function getDescripcion(): ?string
    {
        return $this->descripcion;
    }

    public function setDescripcion(?string $descripcion): static
    {
        $this->descripcion = $descripcion;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }
}
