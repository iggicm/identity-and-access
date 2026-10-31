<?php

namespace Tests\Feature;

use App\Domain\Model\Access\Role;
use App\Domain\Model\Identity\Group;
use App\Jobs\ProcessRoleUpdatedEventJob;
use App\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Passport\Passport;
use Tests\TestCase;
use Webpatser\Uuid\Uuid;

class RoleManagementTest extends TestCase
{
    public function testUnauthenticatedCannotAccessRoles()
    {
        $response = $this->getJson('/api/roles');
        $response->assertStatus(401);

        $createResponse = $this->postJson('/api/roles', ['name' => 'Test', 'description' => 'Test']);
        $createResponse->assertStatus(401);
    }

    public function testNonAdminCannotAccessRoles()
    {
        $user = $this->createRegularUser();
        Passport::actingAs($user, ['USER']);

        $response = $this->getJson('/api/roles');
        $response->assertStatus(403);
    }

    public function testAdministratorCanCreateRole()
    {
        Storage::fake('local');

        $admin = $this->createAdminUser();
        Passport::actingAs($admin, ['ADMINISTRATOR']);

        $photo = UploadedFile::fake()->create('role.jpg', 10, 'image/jpeg');

        $response = $this->postJson('/api/roles', [
            'name' => 'Editor Role',
            'description' => 'Can edit content',
            'photo' => $photo,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => 1,
            'faillure' => 0,
            'response' => 'Role created successfull',
        ]);

        $this->assertDatabaseHas('roles', [
            'name' => 'Editor Role',
            'created_by' => $admin->userid,
        ]);
    }

    public function testCreateRoleValidationFailsOnMissingFields()
    {
        $admin = $this->createAdminUser();
        Passport::actingAs($admin, ['ADMINISTRATOR']);

        $response = $this->postJson('/api/roles', []);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => 0,
            'faillure' => 1,
        ]);
    }

    public function testAdministratorCanRetrieveAllRoles()
    {
        $admin = $this->createAdminUser();
        Passport::actingAs($admin, ['ADMINISTRATOR']);

        $response = $this->getJson('/api/roles');

        $response->assertStatus(200);
        $response->assertJson([
            'success' => 1,
            'faillure' => 0,
        ]);
        // Seeded roles: Administrator and Super Administrator
        $this->assertGreaterThanOrEqual(2, count($response->json('response')));
    }

    public function testCheckIsUserInRoleViaApi()
    {
        $admin = $this->createAdminUser();
        $user = $this->createRegularUser(['userid' => 'test-role-u', 'email' => 'testroleu@example.com']);
        Passport::actingAs($admin, ['ADMINISTRATOR']);

        $role = new Role(
            (string) Uuid::generate(),
            'Reviewer',
            'Reviews items',
            null,
            '[]',
            json_encode(['test-role-u']),
            '[]',
            $admin->userid
        );
        $role->save();

        // Check assigned user
        $response = $this->getJson("/api/roles/{$role->roleid}/is-user-in-role/{$user->userid}");
        $response->assertStatus(200);
        $response->assertJson([
            'success' => 1,
            'faillure' => 0,
            'response' => true,
        ]);

        // Check unassigned user
        $responseOther = $this->getJson("/api/roles/{$role->roleid}/is-user-in-role/non-assigned-user");
        $responseOther->assertStatus(200);
        $responseOther->assertJson([
            'success' => 0,
            'faillure' => 1,
            'raison' => 'Utilisateur Inexistant',
        ]);
    }

    public function testUpdateRoleAddGroupsToRole()
    {
        Queue::fake();

        $admin = $this->createAdminUser();
        Passport::actingAs($admin, ['ADMINISTRATOR']);

        $group = new Group((string) Uuid::generate(), 'Group For Role', 'Desc', '[]', $admin->userid);
        $group->save();

        $role = new Role((string) Uuid::generate(), 'Role Group Test', 'Desc', null, '[]', '[]', '[]', $admin->userid);
        $role->save();

        $response = $this->postJson("/api/roles/{$role->roleid}", [
            'action' => env('ADD_GROUPS_TO_ROLE'),
            'groupstoplayrole' => json_encode([$group->groupid]),
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => 1,
            'faillure' => 0,
            'response' => 'Group ajoutes avec succes',
        ]);

        $role->refresh();
        $this->assertContains($group->groupid, json_decode($role->groupsplayingrole, true));
        Queue::assertPushed(ProcessRoleUpdatedEventJob::class);
    }

    public function testUpdateRoleAddUsersToRole()
    {
        Queue::fake();

        $admin = $this->createAdminUser();
        $user = $this->createRegularUser(['userid' => 'role-add-u', 'email' => 'roleaddu@example.com']);
        Passport::actingAs($admin, ['ADMINISTRATOR']);

        $role = new Role((string) Uuid::generate(), 'Role User Test', 'Desc', null, '[]', '[]', '[]', $admin->userid);
        $role->save();

        $response = $this->postJson("/api/roles/{$role->roleid}", [
            'action' => env('ADD_USERS_TO_ROLE'),
            'userstoplayrole' => json_encode(['role-add-u']),
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => 1,
            'faillure' => 0,
            'response' => 'Utilisateur ajoutes avec succes',
        ]);

        $role->refresh();
        $this->assertContains('role-add-u', json_decode($role->usersplayingrole, true));
        Queue::assertPushed(ProcessRoleUpdatedEventJob::class);
    }

    public function testUpdateRoleRemoveActionsAreEmptyStubsInCodebase()
    {
        // Documenting codebase discrepancy: REMOVE_USERS_TO_ROLE and REMOVE_GROUPS_TO_ROLE
        // have empty blocks in RoleController and fall through to 'Aucune action specifiee'
        $admin = $this->createAdminUser();
        Passport::actingAs($admin, ['ADMINISTRATOR']);

        $role = new Role((string) Uuid::generate(), 'Stub Test Role', 'Desc', null, '[]', '[]', '[]', $admin->userid);
        $role->save();

        $response = $this->postJson("/api/roles/{$role->roleid}", [
            'action' => env('REMOVE_USERS_TO_ROLE'),
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => 0,
            'faillure' => 1,
            'raison' => 'Aucune action specifiee',
        ]);
    }

    public function testGroupsNotPlayingRole()
    {
        $admin = $this->createAdminUser();
        Passport::actingAs($admin, ['ADMINISTRATOR']);

        $groupIn = new Group((string) Uuid::generate(), 'In Group', 'Desc', '[]', $admin->userid);
        $groupIn->save();
        $groupOut = new Group((string) Uuid::generate(), 'Out Group', 'Desc', '[]', $admin->userid);
        $groupOut->save();

        $role = new Role((string) Uuid::generate(), 'Filter Role', 'Desc', null, json_encode([$groupIn->groupid]), '[]', '[]', $admin->userid);
        $role->save();

        $response = $this->getJson("/api/roles/{$role->roleid}/groups-not-playing-role");

        $response->assertStatus(200);
        $response->assertJson([
            'success' => 1,
            'faillure' => 0,
        ]);

        $groups = $response->json('response');
        $groupIds = array_column($groups, 'groupid');

        $this->assertNotContains($groupIn->groupid, $groupIds);
        $this->assertContains($groupOut->groupid, $groupIds);
    }
}
