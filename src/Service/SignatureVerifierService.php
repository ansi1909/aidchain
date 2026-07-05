<?php

namespace App\Service;

/**
 * Verifica firmas digitales ECDSA sobre la curva P-256 (secp256r1) usando
 * SHA-256, contra la clave pública (formato PEM) registrada de la organización.
 *
 * La dApp del navegador firma con la Web Crypto API (algoritmo
 * { name: 'ECDSA', hash: 'SHA-256' }), que produce la firma en formato "raw"
 * IEEE P1363: la concatenación de los dos enteros r y s de 32 bytes cada uno
 * (64 bytes en total para P-256). OpenSSL, en cambio, espera la firma en
 * formato ASN.1 DER, por lo que aquí convertimos de P1363 a DER antes de
 * verificar. También aceptamos firmas ya en DER (por compatibilidad).
 */
class SignatureVerifierService
{
    /** Longitud en bytes de una firma raw P-256 (r||s). */
    private const P256_RAW_SIGNATURE_LENGTH = 64;

    /**
     * @param string $payload       Mensaje canónico que fue firmado.
     * @param string $signatureB64  Firma en base64 (formato raw P1363 o DER).
     * @param string $publicKeyPem  Clave pública ECDSA en formato PEM.
     */
    public function verify(string $payload, string $signatureB64, string $publicKeyPem): bool
    {
        $signature = base64_decode($signatureB64, true);
        if ($signature === false || $signature === '') {
            return false;
        }

        $publicKey = openssl_pkey_get_public($publicKeyPem);
        if ($publicKey === false) {
            return false;
        }

        // Si viene en formato raw (r||s), convertir a DER; si no, usar tal cual.
        $der = \strlen($signature) === self::P256_RAW_SIGNATURE_LENGTH
            ? $this->rawToDer($signature)
            : $signature;

        $result = openssl_verify($payload, $der, $publicKey, OPENSSL_ALGO_SHA256);

        return $result === 1;
    }

    /**
     * Convierte una firma ECDSA en formato raw IEEE P1363 (r||s, 64 bytes)
     * a la estructura ASN.1 DER que espera OpenSSL:
     *   SEQUENCE { INTEGER r, INTEGER s }
     */
    private function rawToDer(string $raw): string
    {
        $half = \intdiv(\strlen($raw), 2);
        $r = substr($raw, 0, $half);
        $s = substr($raw, $half);

        $rEncoded = $this->encodeInteger($r);
        $sEncoded = $this->encodeInteger($s);

        $sequence = $rEncoded . $sEncoded;

        return "\x30" . $this->encodeLength(\strlen($sequence)) . $sequence;
    }

    /**
     * Codifica un entero positivo big-endian como un TLV ASN.1 INTEGER,
     * eliminando ceros a la izquierda y anteponiendo un 0x00 cuando el bit
     * más significativo está activo (para que no se interprete como negativo).
     */
    private function encodeInteger(string $bytes): string
    {
        // Quitar ceros a la izquierda, pero conservar al menos un byte.
        $bytes = ltrim($bytes, "\x00");
        if ($bytes === '') {
            $bytes = "\x00";
        }

        // Si el bit alto está activo, anteponer 0x00 para marcarlo positivo.
        if (\ord($bytes[0]) & 0x80) {
            $bytes = "\x00" . $bytes;
        }

        return "\x02" . $this->encodeLength(\strlen($bytes)) . $bytes;
    }

    /**
     * Codifica la longitud según las reglas DER (forma corta o larga).
     */
    private function encodeLength(int $length): string
    {
        if ($length < 0x80) {
            return \chr($length);
        }

        $bytes = '';
        while ($length > 0) {
            $bytes = \chr($length & 0xFF) . $bytes;
            $length >>= 8;
        }

        return \chr(0x80 | \strlen($bytes)) . $bytes;
    }
}
