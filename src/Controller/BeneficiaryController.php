<?php

namespace App\Controller;

use App\Entity\Beneficiary;
use App\Repository\BeneficiaryRepository;
use App\Repository\ShelterRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Censo de beneficiarios (Fase 7). Cada beneficiario recibe un token único
 * determinista (beneficiaryToken) que se materializa en un QR físico y sirve
 * de clave para el control de "doble cobro" (Fase 3).
 */
#[Route('/api/beneficiaries')]
class BeneficiaryController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly BeneficiaryRepository $beneficiaryRepository,
        private readonly ShelterRepository $shelterRepository,
    ) {
    }

    #[Route('', name: 'api_beneficiaries_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $criteria = [];
        $shelterId = $request->query->get('shelterId');
        if ($shelterId !== null) {
            $criteria['shelter'] = $shelterId;
        }

        $data = array_map(
            $this->serialize(...),
            $this->beneficiaryRepository->findBy($criteria, ['createdAt' => 'DESC']),
        );

        return $this->json($data);
    }

    #[Route('', name: 'api_beneficiaries_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        try {
            $body = $request->toArray();
        } catch (\JsonException) {
            return $this->json(['error' => 'JSON inválido.'], Response::HTTP_BAD_REQUEST);
        }

        $nombre = isset($body['nombre']) ? trim((string) $body['nombre']) : null;
        $documento = isset($body['documento']) ? trim((string) $body['documento']) : null;
        $shelterId = $body['shelterId'] ?? null;
        $token = isset($body['beneficiaryToken']) ? trim((string) $body['beneficiaryToken']) : '';
        $datos = $body['datosDemograficos'] ?? null;
        $sexo = $body['sexo'] ?? null;
        $telefono = $body['telefono'] ?? null;

        $shelter = $shelterId !== null ? $this->shelterRepository->find($shelterId) : null;
        if ($shelter === null) {
            return $this->json(['error' => 'shelterId no encontrado.'], Response::HTTP_BAD_REQUEST);
        }

        // Validar documento si se proporciona (solo V/E para beneficiarios)
        if ($documento !== null && $documento !== '') {
            $documentoError = $this->validarDocumento($documento);
            if ($documentoError !== null) {
                return $this->json(['error' => $documentoError], Response::HTTP_BAD_REQUEST);
            }

            // Validar unicidad de documento
            $existingBeneficiary = $this->beneficiaryRepository->findOneBy(['documento' => $documento]);
            if ($existingBeneficiary !== null) {
                return $this->json(['error' => 'Ya existe un beneficiario con este documento de identidad.'], Response::HTTP_CONFLICT);
            }
        }

        // Si no se envía token, generamos uno aleatorio para el QR temporal.
        if ($token === '') {
            $token = bin2hex(random_bytes(16));
        }

        if ($this->beneficiaryRepository->findOneByToken($token) !== null) {
            return $this->json(['error' => 'Ya existe un beneficiario con ese token.'], Response::HTTP_CONFLICT);
        }

        if ($datos !== null && !\is_array($datos)) {
            return $this->json(['error' => 'datosDemograficos debe ser un objeto.'], Response::HTTP_BAD_REQUEST);
        }

        // Integrar sexo y teléfono en datosDemograficos si se proporcionan
        if ($sexo !== null || $telefono !== null) {
            if ($datos === null) {
                $datos = [];
            }
            if ($sexo !== null) {
                $datos['sexo'] = $sexo;
            }
            if ($telefono !== null) {
                $datos['telefono'] = $telefono;
            }
        }

        $beneficiary = (new Beneficiary())
            ->setNombre($nombre)
            ->setDocumento($documento)
            ->setShelter($shelter)
            ->setBeneficiaryToken($token)
            ->setDatosDemograficos($datos);

        $this->entityManager->persist($beneficiary);
        $this->entityManager->flush();

        return $this->json($this->serialize($beneficiary), Response::HTTP_CREATED);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(Beneficiary $b): array
    {
        return [
            'id' => $b->getId(),
            'nombre' => $b->getNombre(),
            'documento' => $b->getDocumento(),
            'beneficiaryToken' => $b->getBeneficiaryToken(),
            'shelterId' => $b->getShelter()->getId(),
            'datosDemograficos' => $b->getDatosDemograficos(),
            'createdAt' => $b->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }

    /**
     * Valida el formato del documento de identidad (solo V/E para beneficiarios).
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
        // Solo V/E para beneficiarios (no pasaportes)
        if (preg_match('/^[VE]-?\d{6,8}$/', $documento)) {
            return null;
        }

        return 'Formato de documento inválido. Use V-XXXXXX o E-XXXXXX (6-8 dígitos, guion opcional).';
    }
}
