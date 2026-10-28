<?php

namespace App\Dto;

/** New password (or PIN) of a member, set by a parent; checked against PasswordPolicy. */
final readonly class PasswordRequest
{
    public function __construct(
        public string $password = '',
    ) {
    }
}
