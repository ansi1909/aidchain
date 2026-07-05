<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Historial de llaves públicas de un coordinador.
 * Permite múltiples dispositivos y recuperación de identidad sin invalidar firmas anteriores.
 */
#[ORM\Entity]
#[ORM\Table(name: 'coordinator_key')]
class CoordinatorKey
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'text')]
    private string $publicKey;

    #[ORM\Column(type: 'boolean')]
    private bool $activo = true;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $fechaActivacion;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $fechaRevocacion = null;

    #[ORM\ManyToOne(targetEntity: Coordinator::class, inversedBy: 'keys')]
    #[ORM\JoinColumn(name: 'coordinator_id', nullable: false)]
    private Coordinator $coordinator;

    public function __construct()
    {
        $this->fechaActivacion = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPublicKey(): string
    {
        return $this->publicKey;
    }

    public function setPublicKey(string $publicKey): static
    {
        $this->publicKey = $publicKey;

        return $this;
    }

    public function isActivo(): bool
    {
        return $this->activo;
    }

    public function setActivo(bool $activo): static
    {
        $this->activo = $activo;

        return $this;
    }

    public function getFechaActivacion(): \DateTimeImmutable
    {
        return $this->fechaActivacion;
    }

    public function setFechaActivacion(\DateTimeImmutable $fechaActivacion): static
    {
        $this->fechaActivacion = $fechaActivacion;

        return $this;
    }

    public function getFechaRevocacion(): ?\DateTimeImmutable
    {
        return $this->fechaRevocacion;
    }

    public function setFechaRevocacion(?\DateTimeImmutable $fechaRevocacion): static
    {
        $this->fechaRevocacion = $fechaRevocacion;

        return $this;
    }

    public function getCoordinator(): Coordinator
    {
        return $this->coordinator;
    }

    public function setCoordinator(Coordinator $coordinator): static
    {
        $this->coordinator = $coordinator;

        return $this;
    }
}
