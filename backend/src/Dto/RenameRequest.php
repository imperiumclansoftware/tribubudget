<?php

namespace App\Dto;

use App\Entity\User;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class RenameRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Indiquez le prénom.', normalizer: 'trim')]
        #[Assert\Length(max: User::DISPLAY_NAME_MAX_LENGTH, normalizer: 'trim')]
        public string $displayName = '',
    ) {
    }
}
