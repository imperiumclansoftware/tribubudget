<?php

namespace App\Entity;

/** Data that only the members of one family may see. */
interface FamilyOwned
{
    public function getFamily(): Family;
}
