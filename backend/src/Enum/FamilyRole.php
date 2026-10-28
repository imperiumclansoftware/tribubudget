<?php

namespace App\Enum;

enum FamilyRole: string
{
    case Parent = 'parent';
    case Child = 'child';

    /** Security role granted to the members holding this family role. */
    public function securityRole(): string
    {
        return match ($this) {
            self::Parent => 'ROLE_PARENT',
            self::Child => 'ROLE_CHILD',
        };
    }

    /** Parents sign in with a password, children with a short PIN. */
    public function minPasswordLength(): int
    {
        return match ($this) {
            self::Parent => 8,
            self::Child => 4,
        };
    }
}
