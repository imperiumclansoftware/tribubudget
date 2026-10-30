<?php

namespace App\Money;

use App\Dto\AllowanceRequest;
use App\Entity\Account;
use App\Entity\Allowance;
use App\Entity\User;
use App\Repository\AllowanceRepository;
use Doctrine\ORM\EntityManagerInterface;

/** The parents set or stop the allowance of a child or a teenager (paid by AllowancePayer). */
final class Allowances
{
    public function __construct(
        private readonly AllowanceRepository $allowances,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /** @param ?Account $account household account the allowance is taken from */
    public function set(User $member, AllowanceRequest $payload, ?Account $account): void
    {
        $nextPaymentOn = new \DateTimeImmutable($payload->nextPaymentOn);
        $allowance = $this->allowances->forChild($member);
        if (null === $allowance) {
            $this->em->persist(new Allowance($member, $payload->amount, $payload->frequency, $nextPaymentOn, $account, $payload->active));
        } else {
            $allowance->edit($payload->amount, $payload->frequency, $nextPaymentOn, $account, $payload->active);
        }
        $this->em->flush();
    }

    public function remove(User $member): void
    {
        $allowance = $this->allowances->forChild($member);
        if (null !== $allowance) {
            $this->em->remove($allowance);
            $this->em->flush();
        }
    }
}
