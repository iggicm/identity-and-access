<?php

namespace Tests\Feature;

use App\Domain\Model\Access\AdministratorSignature;
use App\Domain\Model\Access\Role;
use App\Domain\Model\Identity\RevokedUser;
use App\Domain\Model\Identity\UserRevokationProposition;
use App\Jobs\ProcessRestCall;
use App\User;
use Illuminate\Support\Facades\Queue;
use Laravel\Passport\Passport;
use Tests\TestCase;
use Webpatser\Uuid\Uuid;

class UserRevocationTest extends TestCase
{
    public function testUnauthenticatedCannotAccessRevocation()
    {
        $response = $this->postJson('/api/revokedusers/some-id');
        $response->assertStatus(401);

        $memberResponse = $this->getJson('/api/members');
        $memberResponse->assertStatus(401);
    }

    public function testNonAdminCannotAccessRevocation()
    {
        $user = $this->createRegularUser();
        Passport::actingAs($user, ['USER']);

        $response = $this->postJson('/api/revokedusers/some-id');
        $response->assertStatus(403);
    }

    public function testRevokeUnknownMemberReturnsError()
    {
        $admin = $this->createAdminUser();
        Passport::actingAs($admin, ['ADMINISTRATOR']);

        $response = $this->postJson('/api/revokedusers/unknown-user-id');

        $response->assertStatus(200);
        $response->assertJson([
            'success' => 0,
            'faillure' => 1,
            'raison' => 'Membre inconnu.',
        ]);
    }

    public function testRevokeAlreadyRevokedPropositionReturnsError()
    {
        $admin = $this->createAdminUser();
        $target = $this->createRegularUser(['userid' => 'target-already-rev']);
        Passport::actingAs($admin, ['ADMINISTRATOR']);

        UserRevokationProposition::create([
            'userrevokationpropositionid' => (string) Uuid::generate(),
            'proposeduserid' => 'target-already-rev',
            'proposed_by' => $admin->userid,
            'administratorssignatures' => '[]',
            'state' => UserRevokationProposition::CREATED,
        ]);

        $response = $this->postJson("/api/revokedusers/{$target->userid}");

        $response->assertStatus(200);
        $response->assertJson([
            'success' => 0,
            'faillure' => 1,
            'raison' => 'Membre deja revoque.',
        ]);
    }

    public function testRevokeUserHandlesExternalServiceFailureGracefully()
    {
        // When external debate service is unreachable, revokeUser catches the exception,
        // rolls back the transaction, and returns faillure: 1
        $admin = $this->createAdminUser();
        $target = $this->createRegularUser(['userid' => 'target-to-revoke']);
        Passport::actingAs($admin, ['ADMINISTRATOR']);

        $response = $this->postJson("/api/revokedusers/{$target->userid}");

        $response->assertStatus(200);
        $response->assertJson([
            'success' => 0,
            'faillure' => 1,
        ]);

        // Target user is still present due to transaction rollback
        $this->assertDatabaseHas('users', ['userid' => 'target-to-revoke']);
    }

    public function testSignRevocationPropositionFailsForUnknownProposition()
    {
        $admin = $this->createAdminUser();
        Passport::actingAs($admin, ['ADMINISTRATOR']);

        $response = $this->postJson('/api/userrevokationpropositions/nonexistent-prop-id');

        $response->assertStatus(200);
        $response->assertJson([
            'success' => 0,
            'faillure' => 1,
            'raison' => 'Proposition de revocation inconnu.',
        ]);
    }

    public function testSignRevocationPropositionFailsIfAlreadySigned()
    {
        $admin = $this->createAdminUser(['userid' => 'admin-signer-1']);
        Passport::actingAs($admin, ['ADMINISTRATOR']);

        $target = $this->createRegularUser(['userid' => 'target-sign-1']);

        $sig = new AdministratorSignature('admin-signer-1', date('Y-m-d H:i:s'), 'Admin One');
        $propId = (string) Uuid::generate();
        UserRevokationProposition::create([
            'userrevokationpropositionid' => $propId,
            'proposeduserid' => $target->userid,
            'proposed_by' => 'admin-signer-1',
            'administratorssignatures' => json_encode([$sig]),
            'state' => UserRevokationProposition::CREATED,
        ]);

        $response = $this->postJson("/api/userrevokationpropositions/{$propId}");

        $response->assertStatus(200);
        $response->assertJson([
            'success' => 0,
            'faillure' => 1,
            'raison' => 'Vous avez deja signe.',
        ]);
    }

    public function testSignRevocationPropositionCompletesWhenAllAdminsSign()
    {
        Queue::fake();

        $admin1 = $this->createAdminUser(['userid' => 'rev-admin-1', 'email' => 'revadmin1@example.com']);
        $admin2 = $this->createAdminUser(['userid' => 'rev-admin-2', 'email' => 'revadmin2@example.com']);

        // Set exactly 2 admins
        $this->adminRole->usersplayingrole = json_encode(['rev-admin-1', 'rev-admin-2']);
        $this->adminRole->save();

        $target = $this->createRegularUser(['userid' => 'target-to-complete-rev', 'email' => 'revtarget@example.com']);

        $sig1 = new AdministratorSignature('rev-admin-1', date('Y-m-d H:i:s'), 'Admin One');
        $propId = (string) Uuid::generate();
        UserRevokationProposition::create([
            'userrevokationpropositionid' => $propId,
            'proposeduserid' => $target->userid,
            'proposed_by' => 'rev-admin-1',
            'administratorssignatures' => json_encode([$sig1]),
            'state' => UserRevokationProposition::CREATED,
        ]);

        // Admin 2 signs
        Passport::actingAs($admin2, ['ADMINISTRATOR']);

        $response = $this->postJson("/api/userrevokationpropositions/{$propId}");

        $response->assertStatus(200);
        $response->assertJson([
            'success' => 1,
            'faillure' => 0,
            'response' => 'Signature enregistre avec succes',
        ]);

        // User moved to revokedusers and deleted from users
        $this->assertDatabaseMissing('users', ['userid' => 'target-to-complete-rev']);
        $this->assertDatabaseHas('revokedusers', ['revokeduserid' => 'target-to-complete-rev']);

        Queue::assertPushed(ProcessRestCall::class);
    }

    public function testGetMembersForRevocation()
    {
        $admin = $this->createAdminUser();
        $eligibleMember = $this->createRegularUser(['userid' => 'eligible-mem', 'email' => 'eligible@example.com']);
        Passport::actingAs($admin, ['ADMINISTRATOR']);

        $response = $this->getJson('/api/members');

        $response->assertStatus(200);
        $response->assertJson([
            'success' => 1,
            'faillure' => 0,
        ]);

        $members = $response->json('response');
        $memberIds = array_column($members, 'userid');

        $this->assertContains('eligible-mem', $memberIds);
        $this->assertNotContains($admin->userid, $memberIds);
    }

    public function testGetRevocationPropositionsCreatedForUnsignedAdmin()
    {
        $admin1 = $this->createAdminUser(['userid' => 'creator-rev-admin', 'email' => 'creatorrev@example.com']);
        $admin2 = $this->createAdminUser(['userid' => 'other-rev-admin', 'email' => 'otherrev@example.com']);

        $target = $this->createRegularUser(['userid' => 'prop-target', 'email' => 'proptarget@example.com']);

        $sig1 = new AdministratorSignature('creator-rev-admin', date('Y-m-d H:i:s'), 'Admin Creator');
        $propId = (string) Uuid::generate();
        UserRevokationProposition::create([
            'userrevokationpropositionid' => $propId,
            'proposeduserid' => 'prop-target',
            'proposed_by' => 'creator-rev-admin',
            'administratorssignatures' => json_encode([$sig1]),
            'state' => 'CREATED',
        ]);

        // Admin2 has not signed, so it should be returned
        Passport::actingAs($admin2, ['ADMINISTRATOR']);

        $response = $this->getJson('/api/userrevokationpropositions-created');

        $response->assertStatus(200);
        $response->assertJson([
            'success' => 1,
            'faillure' => 0,
        ]);

        $props = $response->json('response');
        $this->assertCount(1, $props);
        $this->assertEquals($propId, $props[0]['userrevokationpropositionid']);

        // Admin1 already signed, so should return 0 propositions
        Passport::actingAs($admin1, ['ADMINISTRATOR']);
        $admin1Response = $this->getJson('/api/userrevokationpropositions-created');
        $this->assertCount(0, $admin1Response->json('response'));
    }

    public function testGetAdministrators()
    {
        $admin = $this->createAdminUser(['userid' => 'test-admin-list', 'email' => 'adminlist@example.com']);
        Passport::actingAs($admin, ['ADMINISTRATOR']);

        $response = $this->getJson('/api/users-administrator');

        $response->assertStatus(200);
        $response->assertJson([
            'success' => 1,
            'faillure' => 0,
        ]);

        $admins = $response->json('response');
        $adminIds = array_column($admins, 'userid');
        $this->assertContains('test-admin-list', $adminIds);
    }
}
