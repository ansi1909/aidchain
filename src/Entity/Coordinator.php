<?php

namespace App\Entity;

use App\Enum\CoordinatorRole;
use App\Repository\CoordinatorRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Persona verificada en terreno (despachador, encargado de refugio, auditor)
 * que actúa en nombre de una Organization. Cada coordinador tiene su PROPIA
 * identidad criptográfica (par de llaves ECDSA P-256 generado en su navegador);
 * aquí solo guardamos su clave pública para verificar las firmas de los eventos
 * que origina. La llave privada nunca sale de su dispositivo (IndexedDB).
 *
 * Es la pieza central del esquema multi-actor de no repudio: al firmar cada
 * evento con la llave del coordinador (y no solo la de la organización),
 * podemos atribuir criptográficamente quién originó cada movimiento.
 */
#[ORM\Entity(repositoryClass: CoordinatorRepository::class)]
#[ORM\Table(name: 'coordinator')]
class Coordinator
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 180)]
    private string $nombre;

    /** Documento de identidad (DNI, cédula, etc.) para identificación legal. */
    #[ORM\Column(type: 'string', length: 50, unique: true)]
    private string $documento;

    /**
     * Capacidades del coordinador (multi-rol). Una misma persona puede ser
     * despachador y encargado de refugio a la vez, algo habitual en terreno.
     * Se almacena como lista de valores de CoordinatorRole (JSON).
     *
     * @var string[]
     */
    #[ORM\Column(type: 'json')]
    private array $roles = [];

    #[ORM\ManyToOne(targetEntity: Organization::class)]
    #[ORM\JoinColumn(name: 'organization_id', nullable: false)]
    private Organization $organization;

    /** Refugio/centro al que está asignado (opcional). */
    #[ORM\ManyToOne(targetEntity: Shelter::class)]
    #[ORM\JoinColumn(name: 'shelter_id', nullable: true)]
    private ?Shelter $shelter = null;

    /** Clave pública ECDSA P-256 (formato PEM/SPKI) generada en el navegador. */
    #[ORM\Column(type: 'text')]
    private string $publicKey;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    /** @var Collection<int, InventoryEvent> */
    #[ORM\OneToMany(mappedBy: 'coordinatorOrigen', targetEntity: InventoryEvent::class)]
    private Collection $inventoryEvents;

    /** @var Collection<int, CoordinatorKey> */
    #[ORM\OneToMany(mappedBy: 'coordinator', targetEntity: CoordinatorKey::class)]
    private Collection $keys;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->inventoryEvents = new ArrayCollection();
        $this->keys = new ArrayCollection();
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

    public function getDocumento(): string
    {
        return $this->documento;
    }

    public function setDocumento(string $documento): static
    {
        $this->documento = $documento;

        return $this;
    }

    /**
     * Roles del coordinador como enums.
     *
     * @return CoordinatorRole[]
     */
    public function getRoles(): array
    {
        return array_map(
            static fn (string $r): CoordinatorRole => CoordinatorRole::from($r),
            $this->roles,
        );
    }

    /**
     * Roles como valores string (tal cual se persisten y serializan).
     *
     * @return string[]
     */
    public function getRoleValues(): array
    {
        return $this->roles;
    }

    /**
     * @param CoordinatorRole[] $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = array_values(array_unique(array_map(
            static fn (CoordinatorRole $r): string => $r->value,
            $roles,
        )));

        return $this;
    }

    public function hasRole(CoordinatorRole $role): bool
    {
        return \in_array($role->value, $this->roles, true);
    }

    public function getOrganization(): Organization
    {
        return $this->organization;
    }

    public function setOrganization(Organization $organization): static
    {
        $this->organization = $organization;

        return $this;
    }

    public function getShelter(): ?Shelter
    {
        return $this->shelter;
    }

    public function setShelter(?Shelter $shelter): static
    {
        $this->shelter = $shelter;

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

    /** @return Collection<int, CoordinatorKey> */
    public function getKeys(): Collection
    {
        return $this->keys;
    }

    public function addKey(CoordinatorKey $key): static
    {
        if (!$this->keys->contains($key)) {
            $this->keys->add($key);
            $key->setCoordinator($this);
        }

        return $this;
    }

    public function removeKey(CoordinatorKey $key): static
    {
        if ($this->keys->removeElement($key)) {
            if ($key->getCoordinator() === $this) {
                $key->setCoordinator(null);
            }
        }

        return $this;
    }

    /**
     * Obtiene la llave activa actual del coordinador.
     * Si no hay llaves activas, retorna null.
     */
    public function getActiveKey(): ?CoordinatorKey
    {
        foreach ($this->keys as $key) {
            if ($key->isActivo()) {
                return $key;
            }
        }

        return null;
    }

    /**
     * Obtiene todas las llaves públicas del coordinador (activas e inactivas).
     * Útil para verificación de firmas históricas.
     *
     * @return string[]
     */
    public function getAllPublicKeys(): array
    {
        return $this->keys->map(fn (CoordinatorKey $key) => $key->getPublicKey())->toArray();
    }
}
