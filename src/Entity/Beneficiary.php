<?php

namespace App\Entity;

use App\Repository\BeneficiaryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Beneficiario o jefe de familia censado. La identidad determinista
 * (beneficiaryToken) es la clave para el control de "doble cobro":
 * un hash de la cédula o un código QR temporal entregado en terreno.
 */
#[ORM\Entity(repositoryClass: BeneficiaryRepository::class)]
#[ORM\Table(name: 'beneficiary')]
#[ORM\UniqueConstraint(name: 'uniq_beneficiary_token', columns: ['beneficiary_token'])]
class Beneficiary
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    /**
     * Hash determinista (p. ej. SHA-256 de la cédula) o código QR físico.
     * Es la clave primaria de negocio usada para agregar consumo por persona.
     */
    #[ORM\Column(type: 'string', length: 128)]
    private string $beneficiaryToken;

    #[ORM\Column(type: 'string', length: 180, nullable: true)]
    private ?string $nombre = null;

    /** Documento de identidad (V-XXXXXX o E-XXXXXX) para identificación y búsqueda. */
    #[ORM\Column(type: 'string', length: 50, unique: true, nullable: true)]
    private ?string $documento = null;

    #[ORM\ManyToOne(targetEntity: Shelter::class, inversedBy: 'beneficiaries')]
    #[ORM\JoinColumn(name: 'shelter_id', nullable: false)]
    private Shelter $shelter;

    /**
     * Datos demográficos flexibles: tamaño de familia, condiciones médicas,
     * menores a cargo, etc. Se guarda como JSON para no rigidizar el esquema.
     */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $datosDemograficos = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    /** @var Collection<int, InventoryEvent> */
    #[ORM\OneToMany(mappedBy: 'beneficiary', targetEntity: InventoryEvent::class)]
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

    public function getBeneficiaryToken(): string
    {
        return $this->beneficiaryToken;
    }

    public function setBeneficiaryToken(string $beneficiaryToken): static
    {
        $this->beneficiaryToken = $beneficiaryToken;

        return $this;
    }

    public function getNombre(): ?string
    {
        return $this->nombre;
    }

    public function setNombre(?string $nombre): static
    {
        $this->nombre = $nombre;

        return $this;
    }

    public function getDocumento(): ?string
    {
        return $this->documento;
    }

    public function setDocumento(?string $documento): static
    {
        $this->documento = $documento;

        return $this;
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

    public function getDatosDemograficos(): ?array
    {
        return $this->datosDemograficos;
    }

    public function setDatosDemograficos(?array $datosDemograficos): static
    {
        $this->datosDemograficos = $datosDemograficos;

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
