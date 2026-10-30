<?php

namespace App\Enum;

enum FamilyRole: string
{
    case Parent = 'parent';
    case Child = 'child';
    /** Holds accounts of their own, which the parents oversee */
    case Teen = 'teen';

    /** Security role granted to the members holding this family role. */
    public function securityRole(): string
    {
        return match ($this) {
            self::Parent => 'ROLE_PARENT',
            self::Child => 'ROLE_CHILD',
            self::Teen => 'ROLE_TEEN',
        };
    }

    /** Parents and teenagers sign in with a password, children with a short PIN. */
    public function minPasswordLength(): int
    {
        return match ($this) {
            self::Parent, self::Teen => 8,
            self::Child => 4,
        };
    }
}
