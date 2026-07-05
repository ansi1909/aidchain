<?php

namespace App\Controller;

use App\Entity\Coordinator;
use App\Entity\CoordinatorKey;
use App\Enum\CoordinatorRole;
use App\Repository\CoordinatorRepository;
use App\Repository\OrganizationRepository;
use App\Repository\ShelterRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Onboarding de identidad criptográfica: registra a un coordinador junto con
 * la clave pública ECDSA P-256 que generó en su navegador. A partir de ese
 * momento, sus eventos se verifican contra esta clave.
 */
#[Route('/api/coordinators')]
class CoordinatorController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CoordinatorRepository $coordinatorRepository,
        private readonly OrganizationRepository $organizationRepository,
        private readonly ShelterRepository $shelterRepository,
    ) {
    }

    #[Route('/register', name: 'api_coordinators_register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        try {
            $body = $request->toArray();
        } catch (\JsonException) {
            return $this->json(['error' => 'JSON inválido.'], Response::HTTP_BAD_REQUEST);
        }

        $nombre = trim((string) ($body['nombre'] ?? ''));
        $documento = trim((string) ($body['documento'] ?? ''));
        $publicKey = trim((string) ($body['publicKey'] ?? ''));
        $organizationId = $body['organizationId'] ?? null;
        $shelterId = $body['shelterId'] ?? null;

        if ($nombre === '' || $documento === '' || $publicKey === '') {
            return $this->json(['error' => 'nombre, documento y publicKey son obligatorios.'], Response::HTTP_BAD_REQUEST);
        }

        // Validación estricta de documento de identidad
        $documentoError = $this->validarDocumento($documento);
        if ($documentoError !== null) {
            return $this->json(['error' => $documentoError], Response::HTTP_BAD_REQUEST);
        }

        // Validar unicidad de documento
        $existingCoordinator = $this->coordinatorRepository->findOneBy(['documento' => $documento]);
        if ($existingCoordinator !== null) {
            return $this->json(['error' => 'Ya existe un coordinador con este documento de identidad.'], Response::HTTP_CONFLICT);
        }

        // Acepta `roles` (lista, multi-rol) o `rol` (un único valor, retrocompatible).
        $rolesInput = $body['roles'] ?? (isset($body['rol']) ? [$body['rol']] : []);
        if (!is_array($rolesInput) || $rolesInput === []) {
            return $this->json(['error' => 'Debes indicar al menos un rol en "roles".'], Response::HTTP_BAD_REQUEST);
        }

        $validValues = array_column(CoordinatorRole::cases(), 'value');
        $roles = [];
        foreach ($rolesInput as $rolValue) {
            $rol = CoordinatorRole::tryFrom((string) $rolValue);
            if ($rol === null) {
                return $this->json([
                    'error' => sprintf('rol inválido: "%s". Valores válidos: %s.', (string) $rolValue, implode(', ', $validValues)),
                ], Response::HTTP_BAD_REQUEST);
            }
            $roles[] = $rol;
        }

        $organization = $organizationId !== null ? $this->organizationRepository->find($organizationId) : null;
        if ($organization === null) {
            return $this->json(['error' => 'organizationId no encontrado.'], Response::HTTP_BAD_REQUEST);
        }

        $shelter = null;
        if ($shelterId !== null) {
            $shelter = $this->shelterRepository->find($shelterId);
            if ($shelter === null) {
                return $this->json(['error' => 'shelterId no encontrado.'], Response::HTTP_BAD_REQUEST);
            }
        }

        // Validar que la clave pública sea criptográficamente utilizable.
        if (openssl_pkey_get_public($publicKey) === false) {
            return $this->json(['error' => 'publicKey no es una clave pública PEM válida.'], Response::HTTP_BAD_REQUEST);
        }

        $coordinator = (new Coordinator())
            ->setNombre($nombre)
            ->setDocumento($documento)
            ->setRoles($roles)
            ->setOrganization($organization)
            ->setShelter($shelter)
            ->setPublicKey($publicKey);

        $this->entityManager->persist($coordinator);
        $this->entityManager->flush();

        return $this->json([
            'id' => $coordinator->getId(),
            'nombre' => $coordinator->getNombre(),
            'documento' => $coordinator->getDocumento(),
            'roles' => $coordinator->getRoleValues(),
            'organizationId' => $organization->getId(),
            'shelterId' => $shelter?->getId(),
        ], Response::HTTP_CREATED);
    }

    /**
     * Valida el formato del documento de identidad.
     *
     * @param string $documento Documento a validar
     * @return string|null Error message si es inválido, null si es válido
     */
    private function validarDocumento(string $documento): ?string
    {
        // Quitar espacios en blanco al principio y al final
        $documento = trim($documento);

        // Convertir a mayúsculas para validación case-insensitive
        $documento = strtoupper($documento);

        // Validar cédula venezolana: V-XXXXXX o E-XXXXXX (6-8 dígitos, guion opcional)
        if (preg_match('/^[VE]-?\d{6,8}$/', $documento)) {
            return null;
        }

        // Validar pasaporte alfanumérico (6-9 caracteres, solo letras y números)
        if (preg_match('/^[A-Z0-9]{6,9}$/', $documento)) {
            return null;
        }

        return 'Formato de documento inválido. Use V-XXXXXX o E-XXXXXX (6-8 dígitos, guion opcional), o pasaporte alfanumérico (6-9 caracteres).';
    }

    #[Route('/recover', name: 'api_coordinators_recover', methods: ['POST'])]
    public function recover(Request $request): JsonResponse
    {
        try {
            $body = $request->toArray();
        } catch (\JsonException) {
            return $this->json(['error' => 'JSON inválido.'], Response::HTTP_BAD_REQUEST);
        }

        $documento = trim((string) ($body['documento'] ?? ''));
        $publicKey = trim((string) ($body['publicKey'] ?? ''));

        if ($documento === '' || $publicKey === '') {
            return $this->json(['error' => 'documento y publicKey son obligatorios.'], Response::HTTP_BAD_REQUEST);
        }

        // Validar formato de documento
        $documentoError = $this->validarDocumento($documento);
        if ($documentoError !== null) {
            return $this->json(['error' => $documentoError], Response::HTTP_BAD_REQUEST);
        }

        // Buscar coordinador por documento
        $coordinator = $this->coordinatorRepository->findOneBy(['documento' => $documento]);
        if ($coordinator === null) {
            return $this->json(['error' => 'No existe un coordinador con este documento de identidad.'], Response::HTTP_NOT_FOUND);
        }

        // Desactivar todas las llaves existentes del coordinador
        foreach ($coordinator->getKeys() as $key) {
            if ($key->isActivo()) {
                $key->setActivo(false);
                $key->setFechaRevocacion(new \DateTimeImmutable());
            }
        }

        // Crear nueva llave como activa
        $newKey = new CoordinatorKey();
        $newKey->setPublicKey($publicKey);
        $newKey->setActivo(true);
        $newKey->setFechaActivacion(new \DateTimeImmutable());
        $newKey->setCoordinator($coordinator);

        $this->entityManager->persist($newKey);
        $this->entityManager->flush();

        return $this->json([
            'id' => $coordinator->getId(),
            'nombre' => $coordinator->getNombre(),
            'documento' => $coordinator->getDocumento(),
            'roles' => $coordinator->getRoleValues(),
            'organizationId' => $coordinator->getOrganization()->getId(),
            'shelterId' => $coordinator->getShelter()?->getId(),
            'publicKey' => $publicKey,
        ]);
    }
}
