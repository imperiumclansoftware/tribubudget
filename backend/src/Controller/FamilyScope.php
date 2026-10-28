<?php

namespace App\Controller;

use App\Entity\FamilyOwned;
use App\Entity\User;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** Another family's data does not exist, as far as the API is concerned. */
trait FamilyScope
{
    private function sameFamily(FamilyOwned $data, User $me): void
    {
        if ($data->getFamily() !== $me->getFamily()) {
            throw new NotFoundHttpException('Introuvable.');
        }
    }
}
