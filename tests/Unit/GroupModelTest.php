<?php

namespace Tests\Unit;

use App\Domain\Model\Identity\Group;
use App\User;
use Tests\TestCase;

class GroupModelTest extends TestCase
{
    public function testAddMembersToGroupSuccess()
    {
        $user1 = $this->createRegularUser(['userid' => 'grp-u1', 'email' => 'grpu1@example.com']);
        $user2 = $this->createRegularUser(['userid' => 'grp-u2', 'email' => 'grpu2@example.com']);

        $group = new Group('grp-test-1', 'Test Group', 'Description', '[]');
        $group->save();

        $result = $group->addMembersToGroup(['grp-u1', 'grp-u2']);

        $this->assertTrue($result[0]);
        $this->assertEquals(['grp-u1', 'grp-u2'], $result[1]);
        $this->assertEquals([], $result[2]);

        $group->refresh();
        $this->assertEquals(['grp-u1', 'grp-u2'], json_decode($group->members, true));
    }

    public function testAddMembersDoesNotDuplicate()
    {
        $user1 = $this->createRegularUser(['userid' => 'grp-u3', 'email' => 'grpu3@example.com']);

        $group = new Group('grp-test-dup', 'Test Group Dup', 'Description', json_encode(['grp-u3']));
        $group->save();

        $result = $group->addMembersToGroup(['grp-u3']);

        $this->assertTrue($result[0]);
        $this->assertEquals([], $result[1]);
        $this->assertEquals(['grp-u3'], $result[2]);
    }

    public function testAddNonExistentMemberReturnsError()
    {
        $group = new Group('grp-test-err', 'Test Group Err', 'Description', '[]');
        $group->save();

        $result = $group->addMembersToGroup(['non-existent-user-id']);

        $this->assertFalse($result[0]);
        $this->assertStringContainsString('n\'existe pas dans le systeme', $result[3]);
    }

    public function testRemoveMembersFromGroup()
    {
        $user1 = $this->createRegularUser(['userid' => 'grp-u4', 'email' => 'grpu4@example.com']);
        $user2 = $this->createRegularUser(['userid' => 'grp-u5', 'email' => 'grpu5@example.com']);

        $group = new Group('grp-test-rm', 'Test Group Rm', 'Description', json_encode(['grp-u4', 'grp-u5']));
        $group->save();

        $result = $group->removeMembersToGroup(['grp-u4']);

        $this->assertTrue($result[0]);
        $this->assertEquals(['grp-u4'], $result[1]);
        $this->assertEquals([], $result[2]);

        $group->refresh();
        $this->assertEquals(['grp-u5'], json_decode($group->members, true));
    }

    public function testRemoveNonExistentMemberReturnsError()
    {
        $group = new Group('grp-test-rm-err', 'Test Group Rm Err', 'Description', '[]');
        $group->save();

        $result = $group->removeMembersToGroup(['ghost-user-id']);

        $this->assertFalse($result[0]);
        $this->assertStringContainsString('n\'existe pas dans le systeme', $result[3]);
    }
}
