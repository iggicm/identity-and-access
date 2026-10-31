<?php

namespace Tests;

use App\Domain\Model\Access\Role;
use App\Domain\Model\Access\Scope;
use App\Domain\Model\Identity\Group;
use App\Interestcenter;
use App\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Webpatser\Uuid\Uuid;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication, DatabaseMigrations;

    /**
     * @var Role
     */
    protected $adminRole;

    /**
     * @var Role
     */
    protected $superAdminRole;

    /**
     * @var Group
     */
    protected $defaultGroup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedInitialDomainData();
    }

    protected function seedInitialDomainData(): void
    {
        Scope::firstOrCreate(['s_key' => 'ADMINISTRATOR'], ['description' => 'Administrator scope']);
        Scope::firstOrCreate(['s_key' => 'SUPER_ADMINISTRATOR'], ['description' => 'Super Administrator scope']);
        Scope::firstOrCreate(['s_key' => 'USER'], ['description' => 'Regular user scope']);

        $this->defaultGroup = Group::firstOrCreate(
            ['name' => 'DEFAULT GROUP'],
            [
                'groupid' => (string) Uuid::generate(),
                'description' => 'Default system group for all accepted members',
                'members' => '[]',
                'created_by' => 'system'
            ]
        );

        $this->adminRole = Role::firstOrCreate(
            ['name' => 'Administrator'],
            [
                'roleid' => (string) Uuid::generate(),
                'description' => 'Administrator role',
                'photo' => null,
                'groupsplayingrole' => '[]',
                'usersplayingrole' => '[]',
                'scopes' => json_encode(['ADMINISTRATOR']),
                'created_by' => 'system'
            ]
        );

        $this->superAdminRole = Role::firstOrCreate(
            ['name' => 'Super Administrator'],
            [
                'roleid' => (string) Uuid::generate(),
                'description' => 'Super Administrator role',
                'photo' => null,
                'groupsplayingrole' => '[]',
                'usersplayingrole' => '[]',
                'scopes' => json_encode(['SUPER_ADMINISTRATOR']),
                'created_by' => 'system'
            ]
        );

        Interestcenter::firstOrCreate(
            ['interestcenterid' => 'tech-center-id'],
            [
                'name' => 'Technology',
                'description' => 'Technology and software engineering'
            ]
        );
    }

    protected function createAdminUser(array $attributes = []): User
    {
        $userid = $attributes['userid'] ?? (string) Uuid::generate();
        $email = $attributes['email'] ?? 'admin_' . uniqid() . '@example.com';
        $password = $attributes['password'] ?? 'secret123';

        $user = new User(
            $userid,
            $attributes['firstname'] ?? 'Admin',
            $attributes['lastname'] ?? 'User',
            $attributes['gender'] ?? 'M',
            $attributes['photo'] ?? 'admin.jpg',
            $attributes['country'] ?? json_encode(['code' => 'CM', 'name' => 'Cameroon']),
            $attributes['cityofresidency'] ?? 'Yaounde',
            $attributes['interestcenter'] ?? 'tech-center-id',
            $email,
            $attributes['phone'] ?? '+237691179154',
            $attributes['enablement'] ?? true,
            'system',
            $password,
            'Admin account',
            $attributes['mustresetpassword'] ?? false,
            $attributes['latitude'] ?? '3.8480',
            $attributes['longitude'] ?? '11.5021',
            0,
            ''
        );
        $user->name = 'Admin User';
        $user->save();

        $currentUsers = json_decode($this->adminRole->usersplayingrole, true) ?: [];
        if (!in_array($userid, $currentUsers)) {
            $currentUsers[] = $userid;
            $this->adminRole->usersplayingrole = json_encode($currentUsers);
            $this->adminRole->save();
        }

        return $user;
    }

    protected function createRegularUser(array $attributes = []): User
    {
        $userid = $attributes['userid'] ?? (string) Uuid::generate();
        $email = $attributes['email'] ?? 'user_' . uniqid() . '@example.com';
        $password = $attributes['password'] ?? 'secret123';

        $user = new User(
            $userid,
            $attributes['firstname'] ?? 'John',
            $attributes['lastname'] ?? 'Doe',
            $attributes['gender'] ?? 'M',
            $attributes['photo'] ?? 'john.jpg',
            $attributes['country'] ?? json_encode(['code' => 'CM', 'name' => 'Cameroon']),
            $attributes['cityofresidency'] ?? 'Douala',
            $attributes['interestcenter'] ?? 'tech-center-id',
            $email,
            $attributes['phone'] ?? '+237691179154',
            $attributes['enablement'] ?? true,
            'system',
            $password,
            'Regular member',
            $attributes['mustresetpassword'] ?? false,
            $attributes['latitude'] ?? '4.0511',
            $attributes['longitude'] ?? '9.7679',
            0,
            ''
        );
        $user->name = 'John Doe';
        $user->save();

        return $user;
    }
}
