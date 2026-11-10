<?php

namespace App\Security;

use App\Enum\FamilyRole;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Parents protect the whole budget, and teenagers their accounts, with a password of 12 characters
 * or more, neither weak nor found in a known data leak. Children sign in with a PIN of 4 to 12
 * digits (docs/adr/0002-child-pin.md).
 */
final readonly class PasswordPolicy
{
    public const int MAX_LENGTH = 4096;

    public function __construct(private ValidatorInterface $validator)
    {
    }

    public function check(FamilyRole $role, string $password): void
    {
        if (\strlen($password) > self::MAX_LENGTH) {
            throw new UnprocessableEntityHttpException('Ce mot de passe est trop long.');
        }
        if (FamilyRole::Child === $role) {
            if (!preg_match('/^\d{4,12}$/', $password)) {
                throw new UnprocessableEntityHttpException('Le code d’un enfant compte de 4 à 12 chiffres.');
            }

            return;
        }

        $who = FamilyRole::Teen === $role ? 'adolescent' : 'parent';
        $violations = $this->validator->validate($password, [
            new Assert\Length(
                min: $role->minPasswordLength(),
                minMessage: \sprintf('Le mot de passe d’un %s compte au moins %d caractères.', $who, $role->minPasswordLength()),
            ),
            new Assert\PasswordStrength(
                message: 'Ce mot de passe est trop facile à deviner : mélangez des mots, des chiffres et des signes.',
            ),
            // Have I Been Pwned; skipped when the server cannot reach it (home network without Internet)
            new Assert\NotCompromisedPassword(
                message: 'Ce mot de passe figure dans des fuites de données connues : choisissez-en un autre.',
                skipOnError: true,
            ),
        ]);
        foreach ($violations as $violation) {
            throw new UnprocessableEntityHttpException((string) $violation->getMessage());
        }
    }
}
