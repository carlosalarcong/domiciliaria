<?php

declare(strict_types=1);

namespace App\Service;

use Defuse\Crypto\Crypto;
use Defuse\Crypto\Exception\EnvironmentIsBrokenException;
use Defuse\Crypto\Exception\WrongKeyOrModifiedCiphertextException;
use Defuse\Crypto\Key;

/**
 * Servicio de cifrado AES-256-GCM para datos sensibles.
 * La clave se lee desde APP_ENCRYPTION_KEY (nunca hardcodeada).
 */
class EncryptionService
{
    private Key $key;

    public function __construct(string $encryptionKey)
    {
        $this->key = Key::loadFromAsciiSafeString($encryptionKey);
    }

    /**
     * Cifra un string. Retorna el ciphertext en base64 con prefijo "enc:".
     * Si el valor ya está cifrado, lo devuelve tal cual.
     */
    public function encrypt(string $plaintext): string
    {
        if ($this->isEncrypted($plaintext)) {
            return $plaintext;
        }

        $ciphertext = Crypto::encrypt($plaintext, $this->key);
        return 'enc:' . $ciphertext;
    }

    /**
     * Descifra un string. Si no está cifrado, lo devuelve tal cual.
     */
    public function decrypt(string $ciphertext): string
    {
        if (!$this->isEncrypted($ciphertext)) {
            return $ciphertext;
        }

        $raw = substr($ciphertext, 4); // quitar prefijo "enc:"
        try {
            return Crypto::decrypt($raw, $this->key);
        } catch (WrongKeyOrModifiedCiphertextException) {
            throw new \RuntimeException('Error al descifrar: clave incorrecta o datos corrompidos.');
        } catch (EnvironmentIsBrokenException $e) {
            throw new \RuntimeException('Entorno de cifrado no disponible: ' . $e->getMessage());
        }
    }

    /**
     * Descifra con una clave alternativa (para rotación de claves).
     */
    public function decryptWithKey(string $ciphertext, string $oldKeyAscii): string
    {
        if (!$this->isEncrypted($ciphertext)) {
            return $ciphertext;
        }

        $raw = substr($ciphertext, 4);
        $oldKey = Key::loadFromAsciiSafeString($oldKeyAscii);
        return Crypto::decrypt($raw, $oldKey);
    }

    /**
     * Genera un HMAC-SHA256 determinístico para mantener constraints UNIQUE en BD.
     */
    public function hmac(string $value): string
    {
        // Usamos la misma clave como HMAC secret (derivada de sus primeros 32 bytes)
        $keyBytes = substr(hash('sha256', $this->key->saveToAsciiSafeString(), true), 0, 32);
        return hash_hmac('sha256', strtolower(trim($value)), $keyBytes);
    }

    public function isEncrypted(string $value): bool
    {
        return str_starts_with($value, 'enc:');
    }
}
