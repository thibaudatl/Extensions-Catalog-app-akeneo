<?php

namespace App\Security;

class Encryptor
{
    private const CIPHER = 'aes-256-gcm';

    private string $key;

    public function __construct(string $appSecret)
    {
        $this->key = hash('sha256', $appSecret, true);
    }

    public function encrypt(string $plaintext): string
    {
        $ivLength = openssl_cipher_iv_length(self::CIPHER);
        $iv = random_bytes($ivLength);
        $tag = '';

        $ciphertext = openssl_encrypt($plaintext, self::CIPHER, $this->key, OPENSSL_RAW_DATA, $iv, $tag);

        if ($ciphertext === false) {
            throw new \RuntimeException('Encryption failed.');
        }

        return base64_encode($iv . $tag . $ciphertext);
    }

    public function decrypt(string $encoded): string
    {
        $data = base64_decode($encoded, true);

        if ($data === false) {
            throw new \RuntimeException('Decryption failed: invalid base64.');
        }

        $ivLength = openssl_cipher_iv_length(self::CIPHER);
        $tagLength = 16;

        if (strlen($data) < $ivLength + $tagLength) {
            throw new \RuntimeException('Decryption failed: data too short.');
        }

        $iv = substr($data, 0, $ivLength);
        $tag = substr($data, $ivLength, $tagLength);
        $ciphertext = substr($data, $ivLength + $tagLength);

        $plaintext = openssl_decrypt($ciphertext, self::CIPHER, $this->key, OPENSSL_RAW_DATA, $iv, $tag);

        if ($plaintext === false) {
            throw new \RuntimeException('Decryption failed: invalid key or corrupted data.');
        }

        return $plaintext;
    }
}
