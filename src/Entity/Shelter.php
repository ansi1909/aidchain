<?php

namespace App\Entity;

use App\Repository\ShelterRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Refugio o centro de acopio. Punto físico donde se censan beneficiarios
 * y/o se despachan o reciben insumos.
 */
#[ORM\Entity(repositoryClass: ShelterRepository::class)]
#[ORM\Table(name: 'shelter')]
class Shelter
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 180)]
    private string $nombre;

    #[ORM\Column(type: 'string', length: 100)]
    private string $zona;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 7, nullable: true)]
    private ?string $latitud = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 7, nullable: true)]
    private ?string $longitud = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $capacidadCensada = null;

    /**
     * Indica si el refugio está activo. Un refugio inactivo se conserva por
     * integridad histórica (censo y ledger inmutable lo referencian) pero se
     * oculta de los selectores operativos.
     */
    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $activo = true;

    /**
     * Organización que creó o gestiona el refugio (opcional).
     * Un refugio puede ser compartido por varias organizaciones;
     * esta relación es de atribución, no de propiedad exclusiva.
     */
    #[ORM\ManyToOne(targetEntity: Organization::class)]
    #[ORM\JoinColumn(name: 'organization_id', nullable: true)]
    private ?Organization $organization = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    /** @var Collection<int, Beneficiary> */
    #[ORM\OneToMany(mappedBy: 'shelter', targetEntity: Beneficiary::class)]
    private Collection $beneficiaries;

    /** @var Collection<int, InventoryEvent> */
    #[ORM\OneToMany(mappedBy: 'shelter', targetEntity: InventoryEvent::class)]
    private Collection $inventoryEvents;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->beneficiaries = new ArrayCollection();
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

    public function getZona(): string
    {
        return $this->zona;
    }

    public function setZona(string $zona): static
    {
        $this->zona = $zona;

        return $this;
    }

    public function getLatitud(): ?string
    {
        return $this->latitud;
    }

    public function setLatitud(?string $latitud): static
    {
        $this->latitud = $latitud;

        return $this;
    }

    public function getLongitud(): ?string
    {
        return $this->longitud;
    }

    public function setLongitud(?string $longitud): static
    {
        $this->longitud = $longitud;

        return $this;
    }

    public function getCapacidadCensada(): ?int
    {
        return $this->capacidadCensada;
    }

    public function setCapacidadCensada(?int $capacidadCensada): static
    {
        $this->capacidadCensada = $capacidadCensada;

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

    public function getOrganization(): ?Organization
    {
        return $this->organization;
    }

    public function setOrganization(?Organization $organization): static
    {
        $this->organization = $organization;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** @return Collection<int, Beneficiary> */
    public function getBeneficiaries(): Collection
    {
        return $this->beneficiaries;
    }

    /** @return Collection<int, InventoryEvent> */
    public function getInventoryEvents(): Collection
    {
        return $this->inventoryEvents;
    }
}
