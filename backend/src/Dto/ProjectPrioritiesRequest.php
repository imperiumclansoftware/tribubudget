<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/** New order of the projects of a savings account: the first one is funded first. */
final readonly class ProjectPrioritiesRequest
{
    /** @param array<int> $projectIds in order of priority */
    public function __construct(
        #[Assert\NotNull]
        public ?int $accountId = null,
        #[Assert\All([new Assert\Type('int')])]
        public array $projectIds = [],
    ) {
    }
}
