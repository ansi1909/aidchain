<?php

namespace App\Entity;

use App\Enum\EventChannel;
use App\Enum\EventState;
use App\Enum\EventType;
use App\Repository\InventoryEventRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * El "bloque" inmutable del ledger. Cada entrega, despacho o recepción
 * de insumos genera un InventoryEvent encadenado por hash al evento
 * anterior (ver CryptoLedgerService) y firmado digitalmente por quien
 * lo origina (y, cuando aplica, por quien lo recibe).
 */
#[ORM\Entity(repositoryClass: InventoryEventRepository::class)]
#[ORM\Table(name: 'inventory_event')]
#[ORM\Index(columns: ['beneficiary_id', 'created_at'], name: 'idx_beneficiary_fecha')]
#[ORM\Index(columns: ['lote_id'], name: 'idx_lote')]
class InventoryEvent
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 20, enumType: EventType::class)]
    private EventType $tipo;

    #[ORM\Column(type: 'string', length: 120)]
    private string $item;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 3)]
    private string $cantidad;

    #[ORM\Column(type: 'string', length: 20)]
    private string $unidad;

    // Nullable: no todo evento está atado a un beneficiario específico
    // (p. ej. un IN_STOCK de ingreso a bodega).
    #[ORM\ManyToOne(targetEntity: Beneficiary::class, inversedBy: 'inventoryEvents')]
    #[ORM\JoinColumn(name: 'beneficiary_id', nullable: true)]
    private ?Beneficiary $beneficiary = null;

    #[ORM\ManyToOne(targetEntity: Shelter::class, inversedBy: 'inventoryEvents')]
    #[ORM\JoinColumn(name: 'shelter_id', nullable: false)]
    private Shelter $shelter;

    #[ORM\ManyToOne(targetEntity: Organization::class, inversedBy: 'inventoryEvents')]
    #[ORM\JoinColumn(name: 'organization_id', nullable: false)]
    private Organization $organization;

    /**
     * Coordinador (persona verificada) que originó y firmó el evento. Su clave
     * pública es la que valida `firmaOrigen`. Nullable para compatibilidad con
     * eventos históricos o de canales delegados (WhatsApp/Excel) sin persona.
     */
    #[ORM\ManyToOne(targetEntity: Coordinator::class, inversedBy: 'inventoryEvents')]
    #[ORM\JoinColumn(name: 'coordinator_origen_id', nullable: true)]
    private ?Coordinator $coordinatorOrigen = null;

    /**
     * Coordinador (ENCARGADO_REFUGIO) que confirmó y firmó la recepción en
     * destino. Su clave pública valida `firmaDestino`. Null mientras el evento
     * está EN_TRANSITO; se completa al consolidar la firma cruzada (Fase 4).
     */
    #[ORM\ManyToOne(targetEntity: Coordinator::class)]
    #[ORM\JoinColumn(name: 'coordinator_destino_id', nullable: true)]
    private ?Coordinator $coordinatorDestino = null;

    /**
     * SHA-256 de (hashAnterior + payload serializado + timestamp).
     * Es lo que garantiza la inmutabilidad de la cadena.
     */
    #[ORM\Column(type: 'string', length: 64)]
    private string $hashActual;

    /** Hash del bloque anterior en la cadena (null solo para el primer bloque). */
    #[ORM\Column(type: 'string', length: 64, nullable: true)]
    private ?string $hashAnterior = null;

    /** Firma ECDSA (base64) generada en el navegador de quien despacha/registra. */
    #[ORM\Column(type: 'text')]
    private string $firmaOrigen;

    /** Firma ECDSA (base64) de quien confirma la recepción. Null mientras está EN_TRANSITO. */
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $firmaDestino = null;

    #[ORM\Column(type: 'string', length: 20, enumType: EventState::class)]
    private EventState $estado;

    #[ORM\Column(type: 'string', length: 20, enumType: EventChannel::class)]
    private EventChannel $canalOrigen;

    /**
     * Identificador de lote (usado en el QR físico) para vincular el
     * evento de salida (OUT_DISPATCH) con su evento de recepción
     * (IN_RECEPTION) en la firma cruzada de doble vía.
     */
    #[ORM\Column(type: 'string', length: 64, nullable: true)]
    private ?string $loteId = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    /** @var Collection<int, AuditAlert> */
    #[ORM\OneToMany(mappedBy: 'inventoryEvent', targetEntity: AuditAlert::class)]
    private Collection $auditAlerts;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->auditAlerts = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTipo(): EventType
    {
        return $this->tipo;
    }

    public function setTipo(EventType $tipo): static
    {
        $this->tipo = $tipo;

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

    public function getCantidad(): string
    {
        return $this->cantidad;
    }

    public function setCantidad(string $cantidad): static
    {
        $this->cantidad = $cantidad;

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

    public function getBeneficiary(): ?Beneficiary
    {
        return $this->beneficiary;
    }

    public function setBeneficiary(?Beneficiary $beneficiary): static
    {
        $this->beneficiary = $beneficiary;

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

    public function getOrganization(): Organization
    {
        return $this->organization;
    }

    public function setOrganization(Organization $organization): static
    {
        $this->organization = $organization;

        return $this;
    }

    public function getCoordinatorOrigen(): ?Coordinator
    {
        return $this->coordinatorOrigen;
    }

    public function setCoordinatorOrigen(?Coordinator $coordinatorOrigen): static
    {
        $this->coordinatorOrigen = $coordinatorOrigen;

        return $this;
    }

    public function getCoordinatorDestino(): ?Coordinator
    {
        return $this->coordinatorDestino;
    }

    public function setCoordinatorDestino(?Coordinator $coordinatorDestino): static
    {
        $this->coordinatorDestino = $coordinatorDestino;

        return $this;
    }

    public function getHashActual(): string
    {
        return $this->hashActual;
    }

    public function setHashActual(string $hashActual): static
    {
        $this->hashActual = $hashActual;

        return $this;
    }

    public function getHashAnterior(): ?string
    {
        return $this->hashAnterior;
    }

    public function setHashAnterior(?string $hashAnterior): static
    {
        $this->hashAnterior = $hashAnterior;

        return $this;
    }

    public function getFirmaOrigen(): string
    {
        return $this->firmaOrigen;
    }

    public function setFirmaOrigen(string $firmaOrigen): static
    {
        $this->firmaOrigen = $firmaOrigen;

        return $this;
    }

    public function getFirmaDestino(): ?string
    {
        return $this->firmaDestino;
    }

    public function setFirmaDestino(?string $firmaDestino): static
    {
        $this->firmaDestino = $firmaDestino;

        return $this;
    }

    public function getEstado(): EventState
    {
        return $this->estado;
    }

    public function setEstado(EventState $estado): static
    {
        $this->estado = $estado;

        return $this;
    }

    public function getCanalOrigen(): EventChannel
    {
        return $this->canalOrigen;
    }

    public function setCanalOrigen(EventChannel $canalOrigen): static
    {
        $this->canalOrigen = $canalOrigen;

        return $this;
    }

    public function getLoteId(): ?string
    {
        return $this->loteId;
    }

    public function setLoteId(?string $loteId): static
    {
        $this->loteId = $loteId;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** @return Collection<int, AuditAlert> */
    public function getAuditAlerts(): Collection
    {
        return $this->auditAlerts;
    }
}
