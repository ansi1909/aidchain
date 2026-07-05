<?php

namespace App\Tests\Service;

use App\Dto\EventData;
use App\Entity\Coordinator;
use App\Entity\InventoryEvent;
use App\Entity\Organization;
use App\Entity\Shelter;
use App\Enum\CoordinatorRole;
use App\Enum\EventChannel;
use App\Enum\EventState;
use App\Enum\EventType;
use App\Enum\OrganizationType;
use App\Exception\ReceptionNotAllowedException;
use App\Repository\InventoryEventRepository;
use App\Service\CryptoLedgerService;
use App\Service\SignatureVerifierService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Test de integración del corazón de la Fase 2: encadenamiento por hash e
 * inmutabilidad. Comprueba que una cadena construida con appendEvent() es
 * válida y que alterar un registro en la base de datos rompe la verificación
 * (la "alarma perimetral" de la propuesta).
 *
 * Usa la base de datos de test aislada "aidchain_test" (ver .env.test).
 */
class CryptoLedgerServiceTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private CryptoLedgerService $ledger;
    private Shelter $shelter;
    private Organization $organization;
    private Coordinator $coordinator;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $this->em = $container->get(EntityManagerInterface::class);

        // Crear el esquema desde los metadatos de las entidades.
        $schemaTool = new SchemaTool($this->em);
        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);

        // La verificación de firma se prueba aparte (SignatureVerifierServiceTest);
        // aquí la sustituimos por un doble que siempre acepta, para aislar la
        // lógica de encadenamiento.
        $verifier = $this->createStub(SignatureVerifierService::class);
        $verifier->method('verify')->willReturn(true);

        /** @var InventoryEventRepository $repository */
        $repository = $this->em->getRepository(InventoryEvent::class);

        $this->ledger = new CryptoLedgerService($this->em, $repository, $verifier);

        // Datos base compartidos.
        $this->shelter = (new Shelter())->setNombre('Refugio Zona A')->setZona('A');
        $this->organization = (new Organization())
            ->setNombre('ONG Prueba')
            ->setTipo(OrganizationType::ONG)
            ->setPublicKey('-----BEGIN PUBLIC KEY-----\nFAKE\n-----END PUBLIC KEY-----');

        $this->coordinator = (new Coordinator())
            ->setNombre('Coordinador Prueba')
            ->setRoles([CoordinatorRole::DESPACHADOR])
            ->setOrganization($this->organization)
            ->setPublicKey('-----BEGIN PUBLIC KEY-----\nFAKE-COORD\n-----END PUBLIC KEY-----');

        $this->em->persist($this->shelter);
        $this->em->persist($this->organization);
        $this->em->persist($this->coordinator);
        $this->em->flush();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->em->close();
    }

    public function testCadenaConstruidaEsValidaYEstaEncadenada(): void
    {
        $e1 = $this->append('Agua', '20');
        $e2 = $this->append('Arroz', '50');
        $e3 = $this->append('Medicinas', '5');

        // El bloque génesis no tiene hash anterior; los siguientes enlazan al previo.
        $this->assertNull($e1->getHashAnterior());
        $this->assertSame($e1->getHashActual(), $e2->getHashAnterior());
        $this->assertSame($e2->getHashActual(), $e3->getHashAnterior());

        $resultado = $this->ledger->verifyChain();

        $this->assertTrue($resultado['valid']);
        $this->assertSame(3, $resultado['total']);
        $this->assertSame([], $resultado['breaks']);
    }

    public function testCadenaSigueValidaTrasRecargarDesdeLaBaseDeDatos(): void
    {
        // Regresión: `cantidad` es decimal(12,3). Al escribir se hashea el valor
        // entrante ("50"); al releer, Doctrine devuelve "50.000". Sin normalizar
        // la cantidad, verifyChain() reportaría un falso "hash_alterado".
        $this->append('Agua', '20');
        $this->append('Arroz', '50');
        $this->append('Aceite', '2.5');

        // Forzar la re-hidratación desde la BD (cantidades en forma decimal).
        $this->em->clear();

        $resultado = $this->ledger->verifyChain();

        $this->assertTrue(
            $resultado['valid'],
            'La cadena debe seguir válida tras recargar (normalización de cantidad).',
        );
        $this->assertSame([], $resultado['breaks']);
    }

    public function testAlterarUnRegistroRompeLaCadena(): void
    {
        $this->append('Agua', '20');
        $eventoAtacado = $this->append('Arroz', '50');
        $this->append('Medicinas', '5');

        $idAtacado = $eventoAtacado->getId();

        // Manipulación directa en la base de datos, saltándose el servicio:
        // así el hash_actual almacenado queda desincronizado del contenido.
        $this->em->getConnection()->executeStatement(
            'UPDATE inventory_event SET item = :item WHERE id = :id',
            ['item' => 'Oro (desviado)', 'id' => $idAtacado],
        );

        // Limpiar el identity map para releer desde la BD manipulada.
        $this->em->clear();

        $resultado = $this->ledger->verifyChain();

        $this->assertFalse($resultado['valid'], 'La cadena manipulada no debe validar.');

        $hashAlterado = array_filter(
            $resultado['breaks'],
            static fn (array $break): bool => $break['tipo'] === 'hash_alterado' && $break['id'] === $idAtacado,
        );

        $this->assertNotEmpty(
            $hashAlterado,
            'Debe detectarse una ruptura de tipo hash_alterado en el bloque manipulado.',
        );
    }

    public function testConfirmarRecepcionConsolidaElDespachoYNoRompeLaCadena(): void
    {
        // Un despacho nace EN_TRANSITO (solo firma de origen).
        $despacho = $this->appendDespacho('Agua', '20', 'LOTE-001');
        $this->assertSame(EventState::EN_TRANSITO, $despacho->getEstado());
        $this->assertNull($despacho->getFirmaDestino());

        $encargado = (new Coordinator())
            ->setNombre('Encargado Refugio')
            ->setRoles([CoordinatorRole::ENCARGADO_REFUGIO])
            ->setOrganization($this->organization)
            ->setPublicKey('-----BEGIN PUBLIC KEY-----\nFAKE-ENCARGADO\n-----END PUBLIC KEY-----');
        $this->em->persist($encargado);
        $this->em->flush();

        // El verificador (stub) acepta la firma; validamos la lógica de estado.
        $consolidado = $this->ledger->confirmReception($despacho, $encargado, 'firma-destino');

        $this->assertSame(EventState::CONSOLIDADO, $consolidado->getEstado());
        $this->assertSame('firma-destino', $consolidado->getFirmaDestino());
        $this->assertSame($encargado, $consolidado->getCoordinatorDestino());

        // La consolidación NO altera el payload canónico, así que la cadena sigue íntegra.
        $resultado = $this->ledger->verifyChain();
        $this->assertTrue($resultado['valid'], 'Consolidar la recepción no debe romper la cadena.');
    }

    public function testNoSePuedeConfirmarRecepcionDeUnEventoQueNoEsDespacho(): void
    {
        $ingreso = $this->append('Arroz', '50'); // IN_STOCK, nace CONSOLIDADO

        $encargado = (new Coordinator())
            ->setNombre('Encargado Refugio')
            ->setRoles([CoordinatorRole::ENCARGADO_REFUGIO])
            ->setOrganization($this->organization)
            ->setPublicKey('-----BEGIN PUBLIC KEY-----\nFAKE-ENCARGADO\n-----END PUBLIC KEY-----');
        $this->em->persist($encargado);
        $this->em->flush();

        $this->expectException(ReceptionNotAllowedException::class);
        $this->ledger->confirmReception($ingreso, $encargado, 'firma-destino');
    }

    public function testNoSePuedeAutoConsolidarUnDespacho(): void
    {
        // Un coordinador con AMBOS roles no puede confirmar su propio despacho:
        // el no repudio exige dos firmas de identidades distintas.
        $multi = (new Coordinator())
            ->setNombre('Coordinador Doble Rol')
            ->setRoles([CoordinatorRole::DESPACHADOR, CoordinatorRole::ENCARGADO_REFUGIO])
            ->setOrganization($this->organization)
            ->setPublicKey('-----BEGIN PUBLIC KEY-----\nFAKE-MULTI\n-----END PUBLIC KEY-----');
        $this->em->persist($multi);
        $this->em->flush();

        $this->assertTrue($multi->hasRole(CoordinatorRole::DESPACHADOR));
        $this->assertTrue($multi->hasRole(CoordinatorRole::ENCARGADO_REFUGIO));

        $despacho = $this->appendDespacho('Agua', '20', 'LOTE-AUTO', $multi);

        $this->expectException(ReceptionNotAllowedException::class);
        $this->ledger->confirmReception($despacho, $multi, 'firma-destino');
    }

    private function append(string $item, string $cantidad): InventoryEvent
    {
        $data = new EventData(
            tipo: EventType::IN_STOCK,
            item: $item,
            cantidad: $cantidad,
            unidad: 'cajas',
            shelter: $this->shelter,
            organization: $this->organization,
            canalOrigen: EventChannel::APP_TERRENO,
            firmaOrigen: 'firma-de-prueba',
            coordinatorOrigen: $this->coordinator,
        );

        return $this->ledger->appendEvent($data);
    }

    private function appendDespacho(string $item, string $cantidad, string $loteId, ?Coordinator $origen = null): InventoryEvent
    {
        $data = new EventData(
            tipo: EventType::OUT_DISPATCH,
            item: $item,
            cantidad: $cantidad,
            unidad: 'cajas',
            shelter: $this->shelter,
            organization: $this->organization,
            canalOrigen: EventChannel::APP_TERRENO,
            firmaOrigen: 'firma-de-prueba',
            coordinatorOrigen: $origen ?? $this->coordinator,
            loteId: $loteId,
        );

        return $this->ledger->appendEvent($data);
    }
}
