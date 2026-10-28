<?php

namespace App\Dto;

use App\Entity\Family;
use App\Entity\User;
use Symfony\Component\Validator\Constraints as Assert;

/** A parent creates their family and becomes its first member. */
final readonly class RegisterRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Donnez un nom à votre famille.', normalizer: 'trim')]
        #[Assert\Length(max: Family::NAME_MAX_LENGTH, normalizer: 'trim')]
        public string $familyName = '',
        #[Assert\NotBlank(message: 'Indiquez votre prénom.', normalizer: 'trim')]
        #[Assert\Length(max: User::DISPLAY_NAME_MAX_LENGTH, normalizer: 'trim')]
        public string $displayName = '',
        #[Assert\NotBlank(message: 'Choisissez un identifiant.')]
        #[Assert\Regex('/^[a-zA-Z0-9._-]{3,40}$/', message: 'L’identifiant compte de 3 à 40 lettres, chiffres, points, tirets.')]
        public string $username = '',
        public string $password = '',
    ) {
    }
}
