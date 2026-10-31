<?php

namespace Tests\Feature;

use App\Domain\Model\Access\AdministratorProposition;
use App\Domain\Model\Access\AdministratorSignature;
use App\Domain\Model\Access\Role;
use App\User;
use Laravel\Passport\Passport;
use Tests\TestCase;
use Webpatser\Uuid\Uuid;

class AdministratorPropositionTest extends TestCase
{
    public function testUnauthenticatedCannotAccessAdministratorPropositions()
    {
        $response = $this->getJson('/api/administratorpropositions');
        $response->assertStatus(401);

        $postResponse = $this->postJson('/api/administratorpropositions', ['proposeduserid' => 'u1']);
        $postResponse->assertStatus(401);
    }

    public function testNonAdminCannotAccessAdministratorPropositions()
    {
        $user = $this->createRegularUser();
        Passport::actingAs($user, ['USER']);

        $response = $this->getJson('/api/administratorpropositions');
        $response->assertStatus(403);
    }

    public function testCreatePropositionFailsForUnknownCandidate()
    {
        $admin = $this->createAdminUser();
        Passport::actingAs($admin, ['ADMINISTRATOR']);

        $response = $this->postJson('/api/administratorpropositions', [
            'proposeduserid' => 'nonexistent-user-id',
            'proposed_by' => $admin->userid,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => 0,
            'faillure' => 1,
            'raison' => 'Candidat inconnu',
        ]);
    }

    public function testCreatePropositionFailsWhenCandidateIsAlreadyAdmin()
    {
        $admin1 = $this->createAdminUser(['userid' => 'admin-p-1', 'email' => 'adminp1@example.com']);
        $admin2 = $this->createAdminUser(['userid' => 'admin-p-2', 'email' => 'adminp2@example.com']);

        Passport::actingAs($admin1, ['ADMINISTRATOR']);

        $response = $this->postJson('/api/administratorpropositions', [
            'proposeduserid' => $admin2->userid,
            'proposed_by' => $admin1->userid,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => 0,
            'faillure' => 1,
            'raison' => 'Est deja administrateur',
        ]);
    }

    public function testCreatePropositionFailsWhenAlreadyProposed()
    {
        $admin = $this->createAdminUser();
        $candidate = $this->createRegularUser();

        Passport::actingAs($admin, ['ADMINISTRATOR']);

        $proposition = AdministratorProposition::create([
            'administratorpropositionid' => (string) Uuid::generate(),
            'proposeduserid' => $candidate->userid,
            'proposed_by' => $admin->userid,
            'administratorssignatures' => '[]',
            'state' => AdministratorProposition::CREATED,
        ]);

        $response = $this->postJson('/api/administratorpropositions', [
            'proposeduserid' => $candidate->userid,
            'proposed_by' => $admin->userid,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => 0,
            'faillure' => 1,
            'raison' => 'Deja propose  administrateur',
        ]);
    }

    public function testCreatePropositionSuccessAndAutoPromoteWhenSoleAdmin()
    {
        // When there is only 1 administrator, proposing a user reaches unanimity immediately
        $admin = $this->createAdminUser(['userid' => 'sole-admin', 'email' => 'soleadmin@example.com']);
        $candidate = $this->createRegularUser(['userid' => 'promoted-candidate', 'email' => 'candidate@example.com']);

        // Ensure sole admin is the only one in Administrator role
        $this->adminRole->usersplayingrole = json_encode(['sole-admin']);
        $this->adminRole->save();

        Passport::actingAs($admin, ['ADMINISTRATOR']);

        $response = $this->postJson('/api/administratorpropositions', [
            'proposeduserid' => 'promoted-candidate',
            'proposed_by' => 'sole-admin',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => 1,
            'faillure' => 0,
            'response' => 'Proposition enregistree avec succes',
        ]);

        // Should be promoted
        $proposition = AdministratorProposition::where('proposeduserid', 'promoted-candidate')->first();
        $this->assertNotNull($proposition);
        $this->assertEquals(AdministratorProposition::ACCEPTED, $proposition->state);

        $this->adminRole->refresh();
        $this->assertTrue($this->adminRole->isUserInRole('promoted-candidate'));
    }

    public function testCreatePropositionWithMultipleAdminsRequiresOtherSignatures()
    {
        $admin1 = $this->createAdminUser(['userid' => 'multi-admin-1', 'email' => 'madmin1@example.com']);
        $admin2 = $this->createAdminUser(['userid' => 'multi-admin-2', 'email' => 'madmin2@example.com']);

        $this->adminRole->usersplayingrole = json_encode(['multi-admin-1', 'multi-admin-2']);
        $this->adminRole->save();

        $candidate = $this->createRegularUser(['userid' => 'pending-candidate', 'email' => 'pendingcand@example.com']);

        Passport::actingAs($admin1, ['ADMINISTRATOR']);

        $response = $this->postJson('/api/administratorpropositions', [
            'proposeduserid' => 'pending-candidate',
            'proposed_by' => 'multi-admin-1',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => 1,
            'faillure' => 0,
        ]);

        $proposition = AdministratorProposition::where('proposeduserid', 'pending-candidate')->first();
        $this->assertEquals(AdministratorProposition::CREATED, $proposition->state);

        // Candidate not yet in role
        $this->adminRole->refresh();
        $this->assertFalse($this->adminRole->isUserInRole('pending-candidate'));
    }

    public function testGetAdministratorPropositionsForSigner()
    {
        $admin1 = $this->createAdminUser(['userid' => 'prop-creator', 'email' => 'propcreator@example.com']);
        $admin2 = $this->createAdminUser(['userid' => 'prop-signer', 'email' => 'propsigner@example.com']);

        $this->adminRole->usersplayingrole = json_encode(['prop-creator', 'prop-signer']);
        $this->adminRole->save();

        $candidate = $this->createRegularUser(['userid' => 'cand-to-sign', 'email' => 'candtosign@example.com']);

        $sig1 = new AdministratorSignature('prop-creator', date('Y-m-d H:i:s'), 'Creator Admin');
        $propId = (string) Uuid::generate();
        AdministratorProposition::create([
            'administratorpropositionid' => $propId,
            'proposeduserid' => 'cand-to-sign',
            'proposed_by' => 'prop-creator',
            'administratorssignatures' => json_encode([$sig1]),
            'state' => AdministratorProposition::CREATED,
        ]);

        // For admin2, it should appear as awaiting signature
        Passport::actingAs($admin2, ['ADMINISTRATOR']);

        $response = $this->getJson('/api/administratorpropositions');

        $response->assertStatus(200);
        $response->assertJson([
            'success' => 1,
            'faillure' => 0,
        ]);
        $props = $response->json('response');
        $this->assertCount(1, $props);
        $this->assertEquals($propId, $props[0]['administratorpropositionid']);
    }
}
