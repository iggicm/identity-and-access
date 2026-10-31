<?php

namespace Tests\Unit;

use App\Domain\Model\Access\Role;
use App\Domain\Model\Identity\Group;
use App\User;
use Tests\TestCase;

class RoleModelTest extends TestCase
{
    public function testIsUserInRoleDirectAssignment()
    {
        $user = $this->createRegularUser(['userid' => 'role-u1', 'email' => 'roleu1@example.com']);

        $role = new Role(
            'role-test-direct',
            'Direct Role',
            'Description',
            null,
            '[]',
            json_encode(['role-u1'])
        );
        $role->save();

        $this->assertTrue($role->isUserInRole('role-u1'));
        $this->assertFalse($role->isUserInRole('non-existent-user'));
    }

    public function testIsUserInRoleInheritedFromGroup()
    {
        $user = $this->createRegularUser(['userid' => 'role-u2', 'email' => 'roleu2@example.com']);

        $group = new Group('grp-role-1', 'Group In Role', 'Description', json_encode(['role-u2']));
        $group->save();

        $role = new Role(
            'role-test-inherited',
            'Inherited Role',
            'Description',
            null,
            json_encode(['grp-role-1']),
            '[]'
        );
        $role->save();

        $this->assertTrue($role->isUserInRole('role-u2'));
    }

    public function testIsUserInRoleReturnsFalseWhenNotAssigned()
    {
        $user = $this->createRegularUser(['userid' => 'role-u3', 'email' => 'roleu3@example.com']);

        $role = new Role('role-empty', 'Empty Role', 'Desc');
        $role->save();

        $this->assertFalse($role->isUserInRole('role-u3'));
    }

    public function testIsGroupInRole()
    {
        $group = new Group('grp-test-in-role', 'G1', 'Desc', '[]');
        $group->save();

        $role = new Role(
            'role-grp-check',
            'Role Grp',
            'Desc',
            null,
            json_encode(['grp-test-in-role']),
            '[]'
        );
        $role->save();

        $this->assertTrue($role->isGroupInRole('grp-test-in-role'));
        $this->assertFalse($role->isGroupInRole('other-group-id'));
    }

    public function testUseridsPlayingRoleCombinesDirectAndGroupUsers()
    {
        $user1 = $this->createRegularUser(['userid' => 'u-direct', 'email' => 'udirect@example.com']);
        $user2 = $this->createRegularUser(['userid' => 'u-group', 'email' => 'ugroup@example.com']);

        $group = new Group('grp-combine', 'G Combine', 'Desc', json_encode(['u-group']));
        $group->save();

        $role = new Role(
            'role-combine',
            'Role Combine',
            'Desc',
            null,
            json_encode(['grp-combine']),
            json_encode(['u-direct'])
        );
        $role->save();

        $userIds = $role->useridsPlayingRole();
        $this->assertContains('u-direct', $userIds);
        $this->assertContains('u-group', $userIds);
    }
}
