<?php

namespace App\Dto;

use App\Entity\MoneyRequest;
use Symfony\Component\Validator\Constraints as Assert;

/** A parent's answer to a request of a child or a teenager. */
final readonly class DecisionRequest
{
    public function __construct(
        #[Assert\Length(max: MoneyRequest::ANSWER_MAX_LENGTH, normalizer: 'trim')]
        public ?string $answer = null,
        /** For an accepted advance or reimbursement: household account it is taken from */
        public ?int $accountId = null,
        /** For an accepted reimbursement: category of the household expense */
        public ?int $categoryId = null,
    ) {
    }

    public function cleanAnswer(): ?string
    {
        $answer = Text::clean($this->answer ?? '');

        return '' === $answer ? null : $answer;
    }
}
