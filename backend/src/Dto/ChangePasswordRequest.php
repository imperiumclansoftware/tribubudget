<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ChangePasswordRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Indiquez votre mot de passe actuel.')]
        public string $currentPassword = '',
        public string $newPassword = '',
    ) {
    }
}
