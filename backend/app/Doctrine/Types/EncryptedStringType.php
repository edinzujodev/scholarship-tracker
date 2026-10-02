<?php

declare(strict_types=1);

namespace App\Doctrine\Types;

use App\Security\Pii\FieldCipher;
use Closure;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use LogicException;

/**
 * A string column stored encrypted (see FieldCipher). Entities keep working
 * with plaintext; Doctrine encrypts on write and decrypts on read, so no other
 * code changes. The column is TEXT because the ciphertext is longer than the
 * plaintext and its length must not leak.
 *
 * Doctrine types are created by Doctrine itself, so the cipher is resolved
 * lazily through a resolver registered by AppServiceProvider.
 */
final class EncryptedStringType extends Type
{
    public const NAME = 'encrypted_string';

    /** @var (Closure(): FieldCipher)|null */
    private static ?Closure $resolver = null;

    private static ?FieldCipher $cipher = null;

    /** @param Closure(): FieldCipher $resolver */
    public static function resolveCipherUsing(Closure $resolver): void
    {
        self::$resolver = $resolver;
        self::$cipher = null;
    }

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getClobTypeDeclarationSQL($column);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        return $value === null ? null : self::cipher()->encrypt((string) $value);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?string
    {
        return $value === null ? null : self::cipher()->decrypt((string) $value);
    }

    private static function cipher(): FieldCipher
    {
        if (self::$cipher === null) {
            $resolver = self::$resolver ?? throw new LogicException('No FieldCipher resolver registered for the encrypted_string type.');
            self::$cipher = $resolver();
        }

        return self::$cipher;
    }
}
