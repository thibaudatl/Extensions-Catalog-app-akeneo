<?php

namespace App\Doctrine\Type;

use App\Security\Encryptor;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

class EncryptedStringType extends Type
{
    public const NAME = 'encrypted_string';

    private static ?Encryptor $encryptor = null;

    public static function setEncryptor(Encryptor $encryptor): void
    {
        self::$encryptor = $encryptor;
    }

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL(['length' => 1024]);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (self::$encryptor === null) {
            throw new \RuntimeException('Encryptor not initialized for EncryptedStringType.');
        }

        return self::$encryptor->decrypt($value);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (self::$encryptor === null) {
            throw new \RuntimeException('Encryptor not initialized for EncryptedStringType.');
        }

        return self::$encryptor->encrypt($value);
    }
}
