<?php

namespace App\Tests\Api;

use App\Tests\ApiTestCase;

final class RegistrationTest extends ApiTestCase
{
    public function testAParentCreatesTheirFamilyReadyToUse(): void
    {
        $token = $this->registerFamily();

        $me = $this->api('GET', '/api/me', $token);
        self::assertSame('parent', $me['role']);
        self::assertSame('Famille Martin', $me['family']['name']);

        $accounts = $this->api('GET', '/api/accounts', $token);
        self::assertSame(['Compte courant'], array_column($accounts, 'name'));
        $categories = array_column($this->api('GET', '/api/categories', $token), 'name');
        self::assertContains('Alimentation', $categories);
        self::assertContains('Argent de poche', $categories);
        self::assertContains('Salaires', $categories);
    }

    public function testUsernamesAreUniqueWhateverTheCase(): void
    {
        $this->registerFamily('maman');

        $response = $this->api('POST', '/api/register', null, ['familyName' => 'Autre', 'displayName' => 'X', 'username' => 'MAMAN', 'password' => 'motdepasse']);
        self::assertResponseStatusCodeSame(422);
        self::assertSame('Cet identifiant est déjà pris.', $response['detail']);
    }

    public function testAParentNeedsARealPassword(): void
    {
        $this->api('POST', '/api/register', null, ['familyName' => 'Martin', 'displayName' => 'Paul', 'username' => 'paul', 'password' => '1234']);
        self::assertResponseStatusCodeSame(422);
    }

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
}
