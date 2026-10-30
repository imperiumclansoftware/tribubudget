<?php

namespace App\Dto;

use App\Entity\MoneyRequest;
use App\Enum\RequestKind;
use Symfony\Component\Validator\Constraints as Assert;

/** Sent by a child or a teenager; the messages talk to them. Reimbursements: ReimbursementRequest. */
final readonly class NewMoneyRequest
{
    public function __construct(
        #[Assert\Choice(choices: [RequestKind::Advance, RequestKind::Purchase], message: 'Demande une avance ou un achat.')]
        public RequestKind $kind = RequestKind::Advance,
        #[Assert\Range(min: 1, max: Amount::MAX, notInRangeMessage: 'Indique un montant positif.')]
        public int $amount = 0,
        #[Assert\NotBlank(message: 'Explique ta demande.', normalizer: 'trim')]
        #[Assert\Length(max: MoneyRequest::REASON_MAX_LENGTH, normalizer: 'trim')]
        public string $reason = '',
    ) {
    }
}
