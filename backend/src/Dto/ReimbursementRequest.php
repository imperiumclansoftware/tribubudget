<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/** Sent by a teenager: an expense of theirs the parents should pay back. */
final readonly class ReimbursementRequest
{
    public function __construct(
        #[Assert\NotNull(message: 'Choisis la dépense à te faire rembourser.')]
        public ?int $operationId = null,
    ) {
    }
}
