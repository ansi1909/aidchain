<?php

namespace App\Entity;

use App\Enum\ShelterNeedPriority;
use App\Enum\ShelterNeedStatus;
use App\Repository\ShelterNeedRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Necesidad de un refugio para un insumo específico.
 * Permite priorizar despachos según urgencia real y rastrear progreso de satisfacción.
 */
#[ORM\Entity(repositoryClass: ShelterNeedRepository::class)]
#[ORM\Table(name: 'shelter_need')]
#[ORM\Index(name: 'idx_shelter_need_shelter', columns: ['shelter_id'])]
#[ORM\Index(name: 'idx_shelter_need_estado', columns: ['estado'])]
#[ORM\Index(name: 'idx_shelter_need_prioridad', columns: ['prioridad'])]
class ShelterNeed
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Shelter::class)]
    #[ORM\JoinColumn(name: 'shelter_id', nullable: false)]
    private Shelter $shelter;

    #[ORM\Column(type: 'string', length: 255)]
    private string $item;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 3)]
    private string $cantidadRequerida;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 3)]
    private string $cantidadRecibida = '0';

    #[ORM\Column(type: 'string', enumType: ShelterNeedPriority::class)]
    private ShelterNeedPriority $prioridad;

    #[ORM\Column(type: 'string', enumType: ShelterNeedStatus::class)]
    private ShelterNeedStatus $estado;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notas = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $fechaReporte;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $fechaActualizacion;

    public function __construct()
    {
        $this->fechaReporte = new \DateTimeImmutable();
        $this->fechaActualizacion = new \DateTimeImmutable();
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

    public function getCantidadRequerida(): string
    {
        return $this->cantidadRequerida;
    }

    public function setCantidadRequerida(string $cantidadRequerida): static
    {
        $this->cantidadRequerida = $cantidadRequerida;
        $this->recalcularEstado();
        return $this;
    }

    public function getCantidadRecibida(): string
    {
        return $this->cantidadRecibida;
    }

    public function setCantidadRecibida(string $cantidadRecibida): static
    {
        $this->cantidadRecibida = $cantidadRecibida;
        $this->recalcularEstado();
        $this->fechaActualizacion = new \DateTimeImmutable();
        return $this;
    }

    /**
     * Incrementa la cantidad recibida (usado al despachar).
     */
    public function agregarCantidadRecibida(string $cantidad): static
    {
        $nueva = (float) $this->cantidadRecibida + (float) $cantidad;
        $this->cantidadRecibida = (string) $nueva;
        $this->recalcularEstado();
        $this->fechaActualizacion = new \DateTimeImmutable();
        return $this;
    }

    public function getPrioridad(): ShelterNeedPriority
    {
        return $this->prioridad;
    }

    public function setPrioridad(ShelterNeedPriority $prioridad): static
    {
        $this->prioridad = $prioridad;
        return $this;
    }

    public function getEstado(): ShelterNeedStatus
    {
        return $this->estado;
    }

    public function setEstado(ShelterNeedStatus $estado): static
    {
        $this->estado = $estado;
        return $this;
    }

    public function getNotas(): ?string
    {
        return $this->notas;
    }

    public function setNotas(?string $notas): static
    {
        $this->notas = $notas;
        return $this;
    }

    public function getFechaReporte(): \DateTimeImmutable
    {
        return $this->fechaReporte;
    }

    public function setFechaReporte(\DateTimeImmutable $fechaReporte): static
    {
        $this->fechaReporte = $fechaReporte;
        return $this;
    }

    public function getFechaActualizacion(): \DateTimeImmutable
    {
        return $this->fechaActualizacion;
    }

    public function setFechaActualizacion(\DateTimeImmutable $fechaActualizacion): static
    {
        $this->fechaActualizacion = $fechaActualizacion;
        return $this;
    }

    /**
     * Recalcula el estado automáticamente según el progreso de satisfacción.
     */
    private function recalcularEstado(): void
    {
        $requerida = (float) $this->cantidadRequerida;
        $recibida = (float) $this->cantidadRecibida;
        $this->estado = ShelterNeedStatus::fromProgress($requerida, $recibida);
    }

    /**
     * Calcula el porcentaje de satisfacción (0-100).
     */
    public function getPorcentajeSatisfaccion(): float
    {
        $requerida = (float) $this->cantidadRequerida;
        if ($requerida <= 0) {
            return 0.0;
        }
        $recibida = (float) $this->cantidadRecibida;
        return min(100.0, ($recibida / $requerida) * 100);
    }
}
