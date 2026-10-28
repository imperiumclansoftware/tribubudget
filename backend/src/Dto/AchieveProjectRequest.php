<?php

namespace App\Dto;

final readonly class AchieveProjectRequest
{
    public function __construct(
        /** Records the money taken from the savings account for the project */
        public bool $recordWithdrawal = true,
        public ?int $categoryId = null,
    ) {
    }
}
