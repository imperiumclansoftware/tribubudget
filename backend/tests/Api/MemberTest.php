<?php

namespace App\Tests\Api;

use App\Tests\ApiTestCase;

final class MemberTest extends ApiTestCase
{
    public function testAChildSignsInWithAPin(): void
    {
        $parent = $this->registerFamily();
        [, $child] = $this->addChild($parent, 'lea', '4321');

        self::assertSame('child', $this->api('GET', '/api/me', $child)['role']);
    }

    public function testAChildPinIsMadeOfDigits(): void
    {
        $parent = $this->registerFamily();

        $response = $this->api('POST', '/api/members', $parent, ['displayName' => 'Léa', 'username' => 'lea', 'role' => 'child', 'password' => 'abcd']);
        self::assertResponseStatusCodeSame(422);
        self::assertSame('Le code d’un enfant compte de 4 à 12 chiffres.', $response['detail']);
    }

    public function testUsernamesAreUniqueWhateverTheCase(): void
    {
        $parent = $this->registerFamily('maman');

        $response = $this->api('POST', '/api/members', $parent, ['displayName' => 'Autre', 'username' => 'MAMAN', 'role' => 'parent', 'password' => 'motdepasse']);
        self::assertResponseStatusCodeSame(422);
        self::assertSame('Cet identifiant est déjà pris.', $response['detail']);
    }
}
