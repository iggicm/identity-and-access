<?php

namespace Tests\Unit;

use App\User;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserModelTest extends TestCase
{
    public function testUserConstants()
    {
        $this->assertEquals('CHANGE_PASSWORD', User::CHANGE_PASSWORD);
        $this->assertEquals('DEACTIVATE_USER', User::DEACTIVATE_USER);
        $this->assertEquals('REACTIVATE_USER', User::REACTIVATE_USER);
        $this->assertEquals('RESET_PASSWORD', User::RESET_PASSWORD);
        $this->assertEquals('123456', User::DEFAULT_PASSWORD);
    }

    public function testUserCreationAndFillable()
    {
        $user = new User(
            'usr-123',
            'Jane',
            'Doe',
            'F',
            'jane.jpg',
            json_encode(['code' => 'CM', 'name' => 'Cameroon']),
            'Douala',
            'Technology',
            'jane@example.com',
            '+237691179154',
            true,
            'admin-1',
            'plainpass',
            'comment',
            false,
            '4.05',
            '9.70',
            false,
            'token-xyz'
        );
        $user->save();

        $this->assertDatabaseHas('users', [
            'userid' => 'usr-123',
            'email' => 'jane@example.com',
            'firstname' => 'Jane',
            'enablement' => 1,
            'mustresetpassword' => 0,
        ]);

        $this->assertTrue(Hash::check('plainpass', $user->password));
    }

    public function testGetCountryCodeAndName()
    {
        $user = new User(
            'usr-country',
            'Country',
            'Tester',
            'M',
            null,
            json_encode(['code' => 'FR', 'name' => 'France']),
            'Paris',
            'tech',
            'country@example.com',
            '+33123456789',
            true
        );

        $this->assertEquals('FR', $user->getcountryCode());
        $this->assertEquals('France', $user->getcountryName());
    }

    public function testHiddenAttributes()
    {
        $user = new User(
            'usr-hidden',
            'Hidden',
            'Attr',
            'M',
            null,
            null,
            null,
            null,
            'hidden@example.com',
            null,
            true,
            null,
            'secret123'
        );
        $userArray = $user->toArray();

        $this->assertArrayNotHasKey('password', $userArray);
        $this->assertArrayNotHasKey('remember_token', $userArray);
    }
}
