<?php

namespace App\Dto;

use App\Entity\Account;
use App\Enum\AccountType;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class AccountRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Donnez un nom au compte.', normalizer: 'trim')]
        #[Assert\Length(max: Account::NAME_MAX_LENGTH, normalizer: 'trim')]
        public string $name = '',
        public AccountType $type = AccountType::Checking,
        /** In cents, possibly negative (overdraft) */
        #[Assert\Range(min: -Amount::MAX, max: Amount::MAX)]
        public int $initialBalance = 0,
        /** Savings accounts: amount not allocated to the projects, in cents; null for none */
        #[Assert\Range(min: 0, max: Amount::MAX, notInRangeMessage: 'Le plancher doit être positif.')]
        public ?int $minimumBalance = null,
        /** The teenager holding the account; null for an account of the household */
        public ?int $ownerId = null,
    ) {
    }
}
