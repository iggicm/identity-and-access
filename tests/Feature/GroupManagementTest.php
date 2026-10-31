<?php

namespace Tests\Feature;

use App\Domain\Model\Identity\Group;
use Laravel\Passport\Passport;
use Tests\TestCase;
use Webpatser\Uuid\Uuid;

class GroupManagementTest extends TestCase
{
    public function testUnauthenticatedCannotAccessGroups()
    {
        $response = $this->getJson('/api/groups');
        $response->assertStatus(401);

        $createResponse = $this->postJson('/api/groups', ['name' => 'Test', 'description' => 'Test']);
        $createResponse->assertStatus(401);
    }

    public function testNonAdminCannotAccessGroups()
    {
        $user = $this->createRegularUser();
        Passport::actingAs($user, ['USER']);

        $response = $this->getJson('/api/groups');
        $response->assertStatus(403);
    }

    public function testAdministratorCanCreateGroup()
    {
        $admin = $this->createAdminUser();
        Passport::actingAs($admin, ['ADMINISTRATOR']);

        $response = $this->postJson('/api/groups', [
            'name' => 'Economic Forum',
            'description' => 'Discussion group on macroeconomics',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => 1,
            'faillure' => 0,
            'response' => 'Group creation successfull',
        ]);

        $this->assertDatabaseHas('groups', [
            'name' => 'Economic Forum',
            'created_by' => $admin->userid,
        ]);
    }

    public function testCreateGroupValidationFailsOnMissingFields()
    {
        $admin = $this->createAdminUser();
        Passport::actingAs($admin, ['ADMINISTRATOR']);

        $response = $this->postJson('/api/groups', []);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => 0,
            'faillure' => 1,
        ]);
        $this->assertNotNull($response->json('raison'));
    }

    public function testAdministratorCanRetrieveAllGroups()
    {
        $admin = $this->createAdminUser();
        Passport::actingAs($admin, ['ADMINISTRATOR']);

        $group = new Group(
            (string) Uuid::generate(),
            'Science Group',
            'Science discussion',
            '[]',
            $admin->userid
        );
        $group->save();

        $response = $this->getJson('/api/groups');

        $response->assertStatus(200);
        $response->assertJson([
            'success' => 1,
            'faillure' => 0,
        ]);
        // Default group + Science Group
        $this->assertGreaterThanOrEqual(2, count($response->json('response')));
    }

    public function testUpdateGroupAddMembers()
    {
        $admin = $this->createAdminUser();
        $member = $this->createRegularUser(['userid' => 'grp-mem-1', 'email' => 'grpmem1@example.com']);
        Passport::actingAs($admin, ['ADMINISTRATOR']);

        $groupId = (string) Uuid::generate();
        $group = new Group($groupId, 'Culture Group', 'Culture', '[]', $admin->userid);
        $group->save();

        $response = $this->postJson("/api/groups/{$groupId}", [
            'action' => env('ADD_MEMBERS_TO_GROUP'),
            'members' => json_encode(['grp-mem-1']),
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => 1,
            'faillure' => 0,
        ]);

        $group->refresh();
        $members = json_decode($group->members, true);
        $this->assertContains('grp-mem-1', $members);
    }

    public function testUpdateGroupRemoveMembers()
    {
        $admin = $this->createAdminUser();
        $member = $this->createRegularUser(['userid' => 'grp-mem-2', 'email' => 'grpmem2@example.com']);
        Passport::actingAs($admin, ['ADMINISTRATOR']);

        $groupId = (string) Uuid::generate();
        $group = new Group($groupId, 'Sports Group', 'Sports', json_encode(['grp-mem-2']), $admin->userid);
        $group->save();

        $response = $this->postJson("/api/groups/{$groupId}", [
            'action' => env('REMOVE_MEMBERS_TO_GROUP'),
            'members' => json_encode(['grp-mem-2']),
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => 1,
            'faillure' => 0,
        ]);

        $group->refresh();
        $members = json_decode($group->members, true);
        $this->assertNotContains('grp-mem-2', $members);
    }

    public function testUpdateGroupFailsOnNonexistentGroup()
    {
        $admin = $this->createAdminUser();
        Passport::actingAs($admin, ['ADMINISTRATOR']);

        $response = $this->postJson('/api/groups/non-existent-group-id', [
            'action' => env('ADD_MEMBERS_TO_GROUP'),
            'members' => '[]',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => 0,
            'faillure' => 1,
            'raison' => 'Group inexistant',
        ]);
    }

    public function testUpdateGroupFailsOnUnknownAction()
    {
        $admin = $this->createAdminUser();
        Passport::actingAs($admin, ['ADMINISTRATOR']);

        $groupId = (string) Uuid::generate();
        $group = new Group($groupId, 'Dummy Group', 'Desc', '[]', $admin->userid);
        $group->save();

        $response = $this->postJson("/api/groups/{$groupId}", [
            'action' => 'UNKNOWN_ACTION',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => 0,
            'faillure' => 1,
            'raison' => 'Aucune action specifiee',
        ]);
    }

    public function testGetUsersNotGroupMembers()
    {
        $admin = $this->createAdminUser();
        $inGroup = $this->createRegularUser(['userid' => 'in-grp-u', 'email' => 'ingrp@example.com']);
        $notInGroup = $this->createRegularUser(['userid' => 'not-in-grp-u', 'email' => 'notingrp@example.com']);

        Passport::actingAs($admin, ['ADMINISTRATOR']);

        $groupId = (string) Uuid::generate();
        $group = new Group($groupId, 'Filter Group', 'Desc', json_encode(['in-grp-u']), $admin->userid);
        $group->save();

        $response = $this->getJson("/api/groups/{$groupId}/not-members");

        $response->assertStatus(200);
        $response->assertJson([
            'success' => 1,
            'faillure' => 0,
        ]);

        $users = $response->json('response');
        $userIds = array_column($users, 'userid');

        $this->assertNotContains('in-grp-u', $userIds);
        $this->assertContains('not-in-grp-u', $userIds);
    }
}
