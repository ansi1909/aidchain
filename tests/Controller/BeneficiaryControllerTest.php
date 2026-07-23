<?php

namespace App\Tests\Controller;

use App\Entity\Beneficiary;
use App\Entity\Shelter;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Test funcional y de integración para la gestión de grupos familiares y representantes (Fase Censo).
 */
class BeneficiaryControllerTest extends WebTestCase
{
    private EntityManagerInterface $em;
    private Shelter $shelter;
    private \Symfony\Bundle\FrameworkBundle\KernelBrowser $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = static::createClient();
        $this->em = $this->client->getContainer()->get(EntityManagerInterface::class);

        // Limpiar y recrear esquema para aislamiento
        $schemaTool = new SchemaTool($this->em);
        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);

        $this->shelter = (new Shelter())->setNombre('Refugio Censo')->setZona('Centro');
        $this->em->persist($this->shelter);
        $this->em->flush();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->em->close();
    }

    public function testCrearRepresentanteYMiembroConValidacionesDeGrupo(): void
    {
        // 1. Crear un Representante de Grupo Familiar
        $this->client->request('POST', '/api/beneficiaries', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'nombre' => 'Jefe de Familia',
            'documento' => 'V-20000001',
            'shelterId' => $this->shelter->getId(),
            'esRepresentante' => true,
        ]));

        $this->assertResponseIsSuccessful();
        $repData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertTrue($repData['esRepresentante']);
        $this->assertNull($repData['representante']);

        $repId = $repData['id'];

        // 2. Intentar crear un Miembro sin especificar representante ni documento de representante -> Error 400
        $this->client->request('POST', '/api/beneficiaries', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'nombre' => 'Hijo Sin Representante',
            'documento' => 'V-20000002',
            'shelterId' => $this->shelter->getId(),
            'esRepresentante' => false,
        ]));

        $this->assertResponseStatusCodeSame(400);
        $err = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertStringContainsString('Toda persona que no sea representante debe tener asociado obligatoriamente a su representante', $err['error']);

        // 3. Crear un Miembro usando representanteId
        $this->client->request('POST', '/api/beneficiaries', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'nombre' => 'Hijo Con ID Representante',
            'documento' => 'V-20000002',
            'shelterId' => $this->shelter->getId(),
            'esRepresentante' => false,
            'representanteId' => $repId,
        ]));

        $this->assertResponseIsSuccessful();
        $miembroData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertFalse($miembroData['esRepresentante']);
        $this->assertSame('V-20000001', $miembroData['representante']['documento']);

        // 4. Crear un Miembro usando documentoRepresentante (carga masiva o por cédula)
        $this->client->request('POST', '/api/beneficiaries', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'nombre' => 'Hijo Con Doc Representante',
            'documento' => 'V-20000003',
            'shelterId' => $this->shelter->getId(),
            'esRepresentante' => false,
            'documentoRepresentante' => 'V-20000001',
        ]));

        $this->assertResponseIsSuccessful();

        // 5. Verificar que el miembro NO puede ser representante de otro (jerarquía de 2 niveles máxima)
        $miembroId = json_decode($this->client->getResponse()->getContent(), true)['id'];
        $this->client->request('POST', '/api/beneficiaries', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'nombre' => 'Nieto No Permitido',
            'documento' => 'V-20000004',
            'shelterId' => $this->shelter->getId(),
            'esRepresentante' => false,
            'representanteId' => $miembroId,
        ]));

        $this->assertResponseStatusCodeSame(400);
        $errJerarquia = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertStringContainsString('El representante seleccionado no es una cabeza de familia válida', $errJerarquia['error']);

        // 6. Verificar que no se permite documento duplicado en la base de datos (unicidad global)
        $this->client->request('POST', '/api/beneficiaries', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'nombre' => 'Duplicado de Jefe',
            'documento' => 'V-20000001',
            'shelterId' => $this->shelter->getId(),
            'esRepresentante' => true,
        ]));

        $this->assertResponseStatusCodeSame(409);
        $errDup = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertStringContainsString('ya está censado', $errDup['error']);
    }

    public function testListarYFiltrarSoloRepresentantes(): void
    {
        // Crear representante
        $this->client->request('POST', '/api/beneficiaries', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'nombre' => 'Rep 1',
            'documento' => 'V-30000001',
            'shelterId' => $this->shelter->getId(),
            'esRepresentante' => true,
        ]));
        $repId = json_decode($this->client->getResponse()->getContent(), true)['id'];

        // Crear miembro
        $this->client->request('POST', '/api/beneficiaries', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'nombre' => 'Miembro 1',
            'documento' => 'V-30000002',
            'shelterId' => $this->shelter->getId(),
            'esRepresentante' => false,
            'representanteId' => $repId,
        ]));

        // Listar todos del refugio
        $this->client->request('GET', '/api/beneficiaries?shelterId=' . $this->shelter->getId());
        $this->assertResponseIsSuccessful();
        $todos = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertCount(2, $todos);

        // Listar solo representantes
        $this->client->request('GET', '/api/beneficiaries?shelterId=' . $this->shelter->getId() . '&soloRepresentantes=1');
        $this->assertResponseIsSuccessful();
        $reps = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertCount(1, $reps);
        $this->assertSame('V-30000001', $reps[0]['documento']);
    }
}

