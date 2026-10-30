<?php

namespace App\Dto;

use App\Entity\User;
use App\Enum\FamilyRole;
use Symfony\Component\Validator\Constraints as Assert;

/** A parent adds a member to the family: another parent, a teenager or a child. */
final readonly class MemberRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Indiquez le prénom.', normalizer: 'trim')]
        #[Assert\Length(max: User::DISPLAY_NAME_MAX_LENGTH, normalizer: 'trim')]
        public string $displayName = '',
        #[Assert\NotBlank(message: 'Choisissez un identifiant.')]
        #[Assert\Regex('/^[a-zA-Z0-9._-]{3,40}$/', message: 'L’identifiant compte de 3 à 40 lettres, chiffres, points, tirets.')]
        public string $username = '',
        public FamilyRole $role = FamilyRole::Child,
        public string $password = '',
    ) {
    }
}
