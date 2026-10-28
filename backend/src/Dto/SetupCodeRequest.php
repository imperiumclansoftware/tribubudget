<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class SetupCodeRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Saisissez le code d’installation.')]
        public string $code = '',
    ) {
    }
}
