<?php

namespace App\Security;

use App\Enum\FamilyRole;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/** Parents protect the whole budget with a password; children sign in with a PIN of 4 or more digits. */
final class PasswordPolicy
{
    public const MAX_LENGTH = 4096;

    public static function check(FamilyRole $role, string $password): void
    {
        if (FamilyRole::Child === $role && !preg_match('/^\d{4,12}$/', $password)) {
            throw new UnprocessableEntityHttpException('Le code d’un enfant compte de 4 à 12 chiffres.');
        }
        if (FamilyRole::Parent === $role && mb_strlen($password) < $role->minPasswordLength()) {
            throw new UnprocessableEntityHttpException(\sprintf('Le mot de passe d’un parent compte au moins %d caractères.', $role->minPasswordLength()));
        }
        if (\strlen($password) > self::MAX_LENGTH) {
            throw new UnprocessableEntityHttpException('Ce mot de passe est trop long.');
        }
    }
}
