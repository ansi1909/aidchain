<?php

namespace App\Service;

use App\Dto\EventData;
use App\Entity\Coordinator;
use App\Entity\InventoryEvent;
use App\Enum\EventState;
use App\Enum\EventType;
use App\Exception\InvalidSignatureException;
use App\Exception\ReceptionNotAllowedException;
use App\Repository\InventoryEventRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Núcleo de inmutabilidad del ledger humanitario (Fase 2).
 *
 * Cada InventoryEvent se encadena al anterior mediante un hash SHA-256:
 *
 *     hash_actual = SHA256( hash_anterior + payload_canonico + timestamp )
 *
 * De esta forma, alterar cualquier campo de negocio de un bloque ya
 * persistido cambia su hash_actual y rompe el enlace con todos los bloques
 * posteriores, lo que verifyChain() detecta de inmediato ("alarma perimetral").
 *
 * La cadena es GLOBAL (una sola secuencia para todo el sistema), coherente
 * con InventoryEventRepository::findUltimoEvento(), que devuelve el último
 * bloque sin filtrar por refugio.
 */
class CryptoLedgerService
{
    /** Formato de timestamp usado tanto al crear como al verificar (precisión de segundos, igual que en BD). */
    private const TIMESTAMP_FORMAT = 'Y-m-d H:i:s';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly InventoryEventRepository $eventRepository,
        private readonly SignatureVerifierService $signatureVerifier,
        private readonly \App\Service\ShelterStockService $stockService,
    ) {
    }

    /**
     * Verifica la firma de origen, encadena el evento por hash y lo persiste.
     *
     * @throws InvalidSignatureException si la firma no valida contra la clave pública de la organización.
     */
    public function appendEvent(EventData $data): InventoryEvent
    {
        $event = new InventoryEvent();
        $event->setTipo($data->tipo)
            ->setItem($data->item)
            ->setCantidad($data->cantidad)
            ->setUnidad($data->unidad)
            ->setShelter($data->shelter)
            ->setOrganization($data->organization)
            ->setCoordinatorOrigen($data->coordinatorOrigen)
            ->setBeneficiary($data->beneficiary)
            ->setCanalOrigen($data->canalOrigen)
            ->setEstado($data->resolveEstado())
            ->setLoteId($data->loteId)
            ->setFirmaOrigen($data->firmaOrigen)
            ->setDatosConfiguracion($data->datosConfiguracion);

        // 1. Verificar la firma de origen contra el historial de claves públicas del coordinador
        //    que la generó. La firma cubre el payload canónico (que ya incluye
        //    el coordinator_id, de modo que la identidad del firmante queda
        //    criptográficamente ligada al contenido y a la cadena).
        $payload = $this->canonicalPayload($event);
        $coordinator = $data->coordinatorOrigen;
        if ($coordinator === null) {
            throw new InvalidSignatureException('El evento no tiene un coordinador de origen para verificar la firma.');
        }

        // Verificar contra todas las llaves del coordinador (historial completo)
        if (!$this->verifyAgainstCoordinatorKeys($payload, $data->firmaOrigen, $coordinator)) {
            throw new InvalidSignatureException();
        }

        // 2. Obtener el hash del último bloque (null si es el génesis).
        $hashAnterior = $this->eventRepository->findUltimoEvento()?->getHashActual();

        // 3. Calcular el hash encadenado.
        $timestamp = $event->getCreatedAt()->format(self::TIMESTAMP_FORMAT);
        $hashActual = $this->computeHash($hashAnterior, $payload, $timestamp);

        $event->setHashAnterior($hashAnterior)
            ->setHashActual($hashActual);

        // 4. Persistir.
        $this->entityManager->persist($event);
        $this->entityManager->flush();

        return $event;
    }

    /**
     * Firma cruzada de doble vía (Fase 4): confirma la recepción de un despacho.
     *
     * El coordinador de destino (ENCARGADO_REFUGIO) firma EXACTAMENTE el mismo
     * payload canónico que firmó el origen, atestiguando criptográficamente que
     * recibió el contenido despachado tal cual. Al validar, se registra su firma
     * (`firmaDestino`) y su identidad (`coordinatorDestino`), y el evento pasa de
     * EN_TRANSITO a CONSOLIDADO.
     *
     * Importante: el payload canónico NO incluye estado ni firmas, por lo que
     * consolidar el evento no altera su hash y la cadena permanece íntegra.
     *
     * @throws ReceptionNotAllowedException si el evento no es un OUT_DISPATCH EN_TRANSITO.
     * @throws InvalidSignatureException    si la firma de destino no valida.
     */
    public function confirmReception(InventoryEvent $event, Coordinator $receiver, string $firmaDestino): InventoryEvent
    {
        if ($event->getTipo() !== EventType::OUT_DISPATCH) {
            throw new ReceptionNotAllowedException('Solo un despacho (OUT_DISPATCH) admite confirmación de recepción.');
        }

        if ($event->getEstado() !== EventState::EN_TRANSITO) {
            throw new ReceptionNotAllowedException(sprintf(
                'El despacho no está EN_TRANSITO (estado actual: %s); no puede confirmarse la recepción.',
                $event->getEstado()->value,
            ));
        }

        // El no repudio de doble vía exige DOS firmas de identidades distintas:
        // el receptor no puede ser el mismo coordinador que originó el despacho
        // (evita la "auto-consolidación" que vaciaría de sentido la firma cruzada).
        $origen = $event->getCoordinatorOrigen();
        if ($origen !== null && $origen->getId() !== null && $origen->getId() === $receiver->getId()) {
            throw new ReceptionNotAllowedException('El coordinador que confirma la recepción no puede ser el mismo que originó el despacho.');
        }

        if (trim($firmaDestino) === '') {
            throw new InvalidSignatureException('Falta la firma de destino.');
        }

        // La firma de destino cubre el MISMO payload canónico del despacho.
        $payload = $this->canonicalPayload($event);
        if (!$this->verifyAgainstCoordinatorKeys($payload, $firmaDestino, $receiver)) {
            throw new InvalidSignatureException('La firma de destino no es válida para ninguna de las claves públicas del coordinador receptor.');
        }

        $event->setFirmaDestino($firmaDestino)
            ->setCoordinatorDestino($receiver)
            ->setEstado(EventState::CONSOLIDADO);

        $this->entityManager->flush();

        // Incrementar el stock del refugio cuando se confirma un despacho logístico
        // Solo si no tiene beneficiario (es logística a granel)
        if ($event->getBeneficiary() === null) {
            $this->stockService->incrementarPorDespacho($event);
        }

        return $event;
    }

    /**
     * Recorre toda la cadena en orden cronológico y detecta rupturas de dos tipos:
     *  - "enlace_roto": el hash_anterior de un bloque no coincide con el hash_actual del previo.
     *  - "hash_alterado": el hash_actual almacenado no coincide con el recalculado (contenido manipulado).
     *
     * @return array{valid: bool, total: int, breaks: list<array{id: int|null, tipo: string, detalle: string}>}
     */
    public function verifyChain(): array
    {
        /** @var InventoryEvent[] $events */
        $events = $this->eventRepository->createQueryBuilder('e')
            ->orderBy('e.createdAt', 'ASC')
            ->addOrderBy('e.id', 'ASC')
            ->getQuery()
            ->getResult();

        $breaks = [];
        $hashPrevioEsperado = null;

        foreach ($events as $event) {
            // (a) Comprobar el enlace con el bloque anterior.
            if ($event->getHashAnterior() !== $hashPrevioEsperado) {
                $breaks[] = [
                    'id' => $event->getId(),
                    'tipo' => 'enlace_roto',
                    'detalle' => sprintf(
                        'hash_anterior=%s pero se esperaba %s',
                        $event->getHashAnterior() ?? 'null',
                        $hashPrevioEsperado ?? 'null',
                    ),
                ];
            }

            // (b) Recalcular el hash del bloque y compararlo con el almacenado.
            $payload = $this->canonicalPayload($event);
            $timestamp = $event->getCreatedAt()->format(self::TIMESTAMP_FORMAT);
            $hashRecalculado = $this->computeHash($event->getHashAnterior(), $payload, $timestamp);

            if (!hash_equals($event->getHashActual(), $hashRecalculado)) {
                $breaks[] = [
                    'id' => $event->getId(),
                    'tipo' => 'hash_alterado',
                    'detalle' => 'El contenido del bloque no coincide con su hash almacenado.',
                ];
            }

            $hashPrevioEsperado = $event->getHashActual();
        }

        return [
            'valid' => $breaks === [],
            'total' => \count($events),
            'breaks' => $breaks,
        ];
    }

    /**
     * hash_actual = SHA256( hash_anterior + payload_canonico + timestamp )
     */
    private function computeHash(?string $hashAnterior, string $payload, string $timestamp): string
    {
        return hash('sha256', ($hashAnterior ?? '') . $payload . $timestamp);
    }

    /**
     * Serialización canónica y determinista de los campos de negocio del evento.
     * Es el mensaje que se firma (SignatureVerifierService) y también la base
     * del hash de encadenamiento. Las claves van ordenadas para que la salida
     * sea estable independientemente del orden de asignación.
     */
    private function canonicalPayload(InventoryEvent $event): string
    {
        $data = [
            'tipo' => $event->getTipo()->value,
            'item' => $event->getItem(),
            'cantidad' => $event->getCantidad() !== null ? $this->normalizeCantidad($event->getCantidad()) : null,
            'unidad' => $event->getUnidad(),
            'beneficiary_token' => $event->getBeneficiary()?->getBeneficiaryToken(),
            'shelter_id' => $event->getShelter()?->getId(),
            'organization_id' => $event->getOrganization()?->getId(),
            'coordinator_id' => $event->getCoordinatorOrigen()?->getId(),
            'canal_origen' => $event->getCanalOrigen()->value,
            'lote_id' => $event->getLoteId(),
        ];

        // Para eventos de configuración, incluir datosConfiguracion en el payload
        if (str_starts_with($event->getTipo()->value, 'config_')) {
            $data['datos_configuracion'] = $event->getDatosConfiguracion();
        }

        ksort($data);

        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    /**
     * Canonicaliza la cantidad a su forma numérica mínima (sin ceros finales
     * tras el punto decimal), de manera IDÉNTICA a la del frontend.
     *
     * Es imprescindible porque `cantidad` se mapea como decimal(12,3): al
     * escribir se hashea/firma el valor que llegó del request (p. ej. "50"),
     * pero al releer desde la BD Doctrine lo devuelve normalizado ("50.000").
     * Sin esta normalización, verifyChain() recalcularía un hash distinto y
     * reportaría un falso "hash_alterado" en cada bloque.
     */
    private function normalizeCantidad(string $cantidad): string
    {
        $c = trim($cantidad);
        if ($c === '') {
            return '0';
        }
        if (str_contains($c, '.')) {
            $c = rtrim($c, '0');
            $c = rtrim($c, '.');
        }

        return $c === '' ? '0' : $c;
    }

    /**
     * Verifica una firma contra el historial completo de llaves del coordinador.
     * Esto permite que firmas hechas con llaves antiguas sigan siendo válidas
     * después de que el coordinador recupera su identidad en un nuevo dispositivo.
     *
     * @param string $payload      Payload canónico que fue firmado
     * @param string $signatureB64 Firma en base64
     * @param Coordinator $coordinator Coordinador cuyo historial de llaves se usará
     * @return bool true si la firma es válida contra alguna llave del historial
     */
    private function verifyAgainstCoordinatorKeys(string $payload, string $signatureB64, Coordinator $coordinator): bool
    {
        try {
            // Primero intentar con la llave activa actual (si existe)
            $activeKey = $coordinator->getActiveKey();
            if ($activeKey !== null) {
                if ($this->signatureVerifier->verify($payload, $signatureB64, $activeKey->getPublicKey())) {
                    return true;
                }
            }

            // Si no funcionó con la llave activa, verificar contra todo el historial
            // Esto es necesario para firmas históricas hechas con llaves revocadas
            $keys = $coordinator->getKeys();
            if ($keys !== null) {
                foreach ($keys as $key) {
                    if ($this->signatureVerifier->verify($payload, $signatureB64, $key->getPublicKey())) {
                        return true;
                    }
                }
            }

            // Fallback: verificar contra el campo publicKey directo (compatibilidad con datos migrados)
            if ($coordinator->getPublicKey() !== null) {
                if ($this->signatureVerifier->verify($payload, $signatureB64, $coordinator->getPublicKey())) {
                    return true;
                }
            }

            return false;
        } catch (\Exception $e) {
            // Si hay algún error al verificar contra el historial, intentar con el fallback
            try {
                if ($coordinator->getPublicKey() !== null) {
                    return $this->signatureVerifier->verify($payload, $signatureB64, $coordinator->getPublicKey());
                }
            } catch (\Exception) {
                // Si falla también el fallback, retornar false
            }
            return false;
        }
    }
}
