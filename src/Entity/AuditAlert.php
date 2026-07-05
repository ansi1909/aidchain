<?php

namespace App\Entity;

use App\Enum\AlertSeverity;
use App\Repository\AuditAlertRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Alerta generada por las reglas de negocio (doble cobro, firma cruzada
 * incompleta) o por la auditoría predictiva de Gemini sobre el ledger.
 */
#[ORM\Entity(repositoryClass: AuditAlertRepository::class)]
#[ORM\Table(name: 'audit_alert')]
class AuditAlert
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    /**
     * Tipo de alerta en texto libre corto, p. ej.:
     * "doble_cobro", "recepcion_no_confirmada", "consumo_anomalo_ia".
     */
    #[ORM\Column(type: 'string', length: 60)]
    private string $tipo;

    #[ORM\Column(type: 'text')]
    private string $mensaje;

    #[ORM\Column(type: 'string', length: 20, enumType: AlertSeverity::class)]
    private AlertSeverity $severidad;

    #[ORM\ManyToOne(targetEntity: InventoryEvent::class, inversedBy: 'auditAlerts')]
    #[ORM\JoinColumn(name: 'inventory_event_id', nullable: true)]
    private ?InventoryEvent $inventoryEvent = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $zonaAfectada = null;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $resuelto = false;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTipo(): string
    {
        return $this->tipo;
    }

    public function setTipo(string $tipo): static
    {
        $this->tipo = $tipo;

        return $this;
    }

    public function getMensaje(): string
    {
        return $this->mensaje;
    }

    public function setMensaje(string $mensaje): static
    {
        $this->mensaje = $mensaje;

        return $this;
    }

    public function getSeveridad(): AlertSeverity
    {
        return $this->severidad;
    }

    public function setSeveridad(AlertSeverity $severidad): static
    {
        $this->severidad = $severidad;

        return $this;
    }

    public function getInventoryEvent(): ?InventoryEvent
    {
        return $this->inventoryEvent;
    }

    public function setInventoryEvent(?InventoryEvent $inventoryEvent): static
    {
        $this->inventoryEvent = $inventoryEvent;

        return $this;
    }

    public function getZonaAfectada(): ?string
    {
        return $this->zonaAfectada;
    }

    public function setZonaAfectada(?string $zonaAfectada): static
    {
        $this->zonaAfectada = $zonaAfectada;

        return $this;
    }

    public function isResuelto(): bool
    {
        return $this->resuelto;
    }

    public function setResuelto(bool $resuelto): static
    {
        $this->resuelto = $resuelto;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
