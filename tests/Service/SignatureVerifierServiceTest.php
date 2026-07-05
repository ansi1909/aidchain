<?php

namespace App\Tests\Service;

use App\Service\SignatureVerifierService;
use PHPUnit\Framework\TestCase;

/**
 * Verifica el servicio de validación de firmas ECDSA P-256 con claves y
 * firmas reales generadas por OpenSSL, cubriendo tanto el formato DER como
 * el formato raw (r||s) que produce la Web Crypto API del navegador.
 */
class SignatureVerifierServiceTest extends TestCase
{
    private SignatureVerifierService $verifier;
    private \OpenSSLAsymmetricKey $privateKey;
    private string $publicKeyPem;

    protected function setUp(): void
    {
        $this->verifier = new SignatureVerifierService();

        $this->privateKey = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_EC,
            'curve_name' => 'prime256v1', // P-256 / secp256r1
        ]);

        $details = openssl_pkey_get_details($this->privateKey);
        $this->publicKeyPem = $details['key'];
    }

    public function testAceptaFirmaEnFormatoDer(): void
    {
        $payload = 'mensaje-firmado-de-prueba';
        openssl_sign($payload, $derSignature, $this->privateKey, OPENSSL_ALGO_SHA256);

        $this->assertTrue(
            $this->verifier->verify($payload, base64_encode($derSignature), $this->publicKeyPem),
            'Una firma DER válida debe aceptarse.',
        );
    }

    public function testAceptaFirmaEnFormatoRawWebCrypto(): void
    {
        $payload = 'mensaje-firmado-de-prueba';
        openssl_sign($payload, $derSignature, $this->privateKey, OPENSSL_ALGO_SHA256);

        // Simular la salida de la Web Crypto API: firma raw r||s de 64 bytes.
        $rawSignature = $this->derToRaw($derSignature);
        $this->assertSame(64, \strlen($rawSignature));

        $this->assertTrue(
            $this->verifier->verify($payload, base64_encode($rawSignature), $this->publicKeyPem),
            'Una firma raw (formato Web Crypto) válida debe aceptarse.',
        );
    }

    public function testRechazaFirmaConPayloadAlterado(): void
    {
        $payload = 'mensaje-original';
        openssl_sign($payload, $derSignature, $this->privateKey, OPENSSL_ALGO_SHA256);

        $this->assertFalse(
            $this->verifier->verify('mensaje-manipulado', base64_encode($derSignature), $this->publicKeyPem),
            'Una firma no debe validar contra un payload distinto al firmado.',
        );
    }

    public function testRechazaFirmaBasura(): void
    {
        $this->assertFalse(
            $this->verifier->verify('payload', base64_encode('no-es-una-firma'), $this->publicKeyPem),
        );
    }

    /**
     * Convierte una firma ECDSA DER (SEQUENCE { INTEGER r, INTEGER s }) al
     * formato raw r||s de 32+32 bytes, replicando lo que emite la Web Crypto API.
     */
    private function derToRaw(string $der): string
    {
        $offset = 0;
        $this->expectByte($der, $offset, 0x30); // SEQUENCE
        $this->readLength($der, $offset);

        $r = $this->readInteger($der, $offset);
        $s = $this->readInteger($der, $offset);

        return $this->pad32($r) . $this->pad32($s);
    }

    private function expectByte(string $data, int &$offset, int $expected): void
    {
        $this->assertSame($expected, \ord($data[$offset]));
        ++$offset;
    }

    private function readLength(string $data, int &$offset): int
    {
        $length = \ord($data[$offset]);
        ++$offset;

        return $length;
    }

    private function readInteger(string $data, int &$offset): string
    {
        $this->expectByte($data, $offset, 0x02); // INTEGER
        $length = $this->readLength($data, $offset);
        $value = substr($data, $offset, $length);
        $offset += $length;

        return ltrim($value, "\x00");
    }

    private function pad32(string $value): string
    {
        return str_pad($value, 32, "\x00", STR_PAD_LEFT);
    }
}
