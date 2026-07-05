<?php

namespace App\Entity;

use App\Enum\OrganizationType;
use App\Repository\OrganizationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Organización o entidad autorizada a firmar eventos en el ledger:
 * gobierno, ONG, cuerpo de voluntariado, etc.
 * Cada una tiene su propia identidad criptográfica (clave pública ECDSA).
 */
#[ORM\Entity(repositoryClass: OrganizationRepository::class)]
#[ORM\Table(name: 'organization')]
class Organization
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 180)]
    private string $nombre;

    #[ORM\Column(type: 'string', length: 30, enumType: OrganizationType::class)]
    private OrganizationType $tipo;

    /**
     * Clave pública ECDSA P-256 (formato PEM) generada en el navegador de la
     * organización. Se usa para verificar las firmas de sus eventos.
     */
    #[ORM\Column(type: 'text')]
    private string $publicKey;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    /** @var Collection<int, InventoryEvent> */
    #[ORM\OneToMany(mappedBy: 'organization', targetEntity: InventoryEvent::class)]
    private Collection $inventoryEvents;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->inventoryEvents = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNombre(): string
    {
        return $this->nombre;
    }

    public function setNombre(string $nombre): static
    {
        $this->nombre = $nombre;

        return $this;
    }

    public function getTipo(): OrganizationType
    {
        return $this->tipo;
    }

    public function setTipo(OrganizationType $tipo): static
    {
        $this->tipo = $tipo;

        return $this;
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

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** @return Collection<int, InventoryEvent> */
    public function getInventoryEvents(): Collection
    {
        return $this->inventoryEvents;
    }
}
