<?php

namespace Tests\Feature;

use App\Domain\Model\Identity\Application;
use App\Domain\Model\Identity\Group;
use App\Domain\Model\Identity\PasswordResetInvitation;
use App\Jobs\ProcessRestCall;
use App\Jobs\ProcessSendEmail;
use App\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Passport\Passport;
use Tests\TestCase;

class ApplicationWorkflowTest extends TestCase
{
    public function testGuestCanSubmitMembershipApplication()
    {
        Storage::fake('local');
        Queue::fake();

        $photo = UploadedFile::fake()->create('applicant.jpg', 10, 'image/jpeg');

        $payload = [
            'firstname' => 'Alice',
            'lastname' => 'Smith',
            'gender' => 'F',
            'photo' => $photo,
            'country' => 'CM',
            'cityofresidency' => 'Yaounde',
            'interestcenter' => 'tech-center-id',
            'email' => 'alice@example.com',
            'phone' => '+237691179154',
            'commenttotheregistration' => 'I would love to join the think tank.',
            'latitude' => 3.848,
            'longitude' => 11.502,
        ];

        $response = $this->postJson('/api/applications', $payload);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => 1,
            'faillure' => 0,
        ]);

        $this->assertDatabaseHas('applications', [
            'email' => 'alice@example.com',
            'firstname' => 'Alice',
            'state' => Application::DEFAULT_APPLICATION_STATE,
        ]);

        Queue::assertPushed(ProcessSendEmail::class);
    }

    public function testApplicationValidationFailsOnMissingFields()
    {
        $response = $this->postJson('/api/applications', []);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => 0,
            'faillure' => 1,
        ]);
        $this->assertNotNull($response->json('raison'));
    }

    public function testApplicationValidationFailsOnDuplicateEmailWithExistingUser()
    {
        Storage::fake('local');
        Queue::fake();

        $this->createRegularUser(['email' => 'existing@example.com']);

        $photo = UploadedFile::fake()->create('test.jpg', 10, 'image/jpeg');
        $payload = [
            'firstname' => 'Bob',
            'lastname' => 'Marley',
            'gender' => 'M',
            'photo' => $photo,
            'country' => 'CM',
            'cityofresidency' => 'Douala',
            'interestcenter' => 'tech-center-id',
            'email' => 'existing@example.com',
            'commenttotheregistration' => 'Want to re-join.',
        ];

        $response = $this->postJson('/api/applications', $payload);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => 0,
            'faillure' => 1,
        ]);
        $this->assertStringContainsString('deja pris', $response->json('raison'));
    }

    public function testApplicationValidationFailsOnNonexistentInterestCenter()
    {
        Storage::fake('local');

        $photo = UploadedFile::fake()->create('test.jpg', 10, 'image/jpeg');
        $payload = [
            'firstname' => 'Bob',
            'lastname' => 'Marley',
            'gender' => 'M',
            'photo' => $photo,
            'country' => 'CM',
            'cityofresidency' => 'Douala',
            'interestcenter' => 'nonexistent-center-id',
            'email' => 'bob_ic@example.com',
            'commenttotheregistration' => 'Want to join.',
        ];

        $response = $this->postJson('/api/applications', $payload);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => 0,
            'faillure' => 1,
        ]);
    }

    public function testUnauthenticatedUserCannotListApplications()
    {
        $response = $this->getJson('/api/applications');
        $response->assertStatus(401);
    }

    public function testNonAdminUserCannotListApplications()
    {
        $user = $this->createRegularUser();
        Passport::actingAs($user, ['USER']);

        $response = $this->getJson('/api/applications');
        $response->assertStatus(403);
    }

    public function testAdministratorCanListApplications()
    {
        $admin = $this->createAdminUser();
        Passport::actingAs($admin, ['ADMINISTRATOR']);

        $application = new Application(
            'app-1',
            'Charlie',
            'Brown',
            'M',
            null,
            'CM',
            'Yaounde',
            'tech-center-id',
            'charlie@example.com',
            '+237691179154',
            'Please accept me.',
            Application::DEFAULT_APPLICATION_STATE
        );
        $application->save();

        $response = $this->getJson('/api/applications');

        $response->assertStatus(200);
        $response->assertJson([
            'success' => 1,
            'faillure' => 0,
        ]);
        $this->assertCount(1, $response->json('response'));
    }

    public function testAdministratorCanAcceptApplication()
    {
        Queue::fake();

        $admin = $this->createAdminUser();
        Passport::actingAs($admin, ['ADMINISTRATOR']);

        $appId = 'app-accept-1';
        $application = new Application(
            $appId,
            'David',
            'Miller',
            'M',
            'photos/david.jpg',
            'CM',
            'Bafoussam',
            'tech-center-id',
            'david@example.com',
            '+237691179154',
            'Ready to debate.',
            Application::DEFAULT_APPLICATION_STATE
        );
        $application->save();

        $response = $this->postJson("/api/applications/{$appId}", [
            'action' => Application::APPLICATION_ACCEPTED,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => 1,
            'faillure' => 0,
            'response' => 'Demande acceptee avec succes',
        ]);

        // Original application should be deleted
        $this->assertDatabaseMissing('applications', ['applicationid' => $appId]);

        // New user should be created with temporary password and mustresetpassword = true
        $this->assertDatabaseHas('users', [
            'userid' => $appId,
            'email' => 'david@example.com',
            'mustresetpassword' => 1,
        ]);

        // User should be added to DEFAULT GROUP
        $this->defaultGroup->refresh();
        $members = json_decode($this->defaultGroup->members, true);
        $this->assertContains($appId, $members);

        // Password reset invitation should be created
        $this->assertDatabaseHas('password_reset_invitations', [
            'email' => 'david@example.com',
            'used' => 0,
        ]);

        // Email and debate jobs dispatched
        Queue::assertPushed(ProcessSendEmail::class);
        Queue::assertPushed(ProcessRestCall::class);
    }

    public function testAdministratorCanDenyApplication()
    {
        Queue::fake();

        $admin = $this->createAdminUser();
        Passport::actingAs($admin, ['ADMINISTRATOR']);

        $appId = 'app-deny-1';
        $application = new Application(
            $appId,
            'Eve',
            'Adams',
            'F',
            'photos/eve.jpg',
            'CM',
            'Garoua',
            'tech-center-id',
            'eve@example.com',
            '+237691179154',
            'Apply.',
            Application::DEFAULT_APPLICATION_STATE
        );
        $application->save();

        $response = $this->postJson("/api/applications/{$appId}", [
            'action' => Application::APPLICATION_DENIED,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => 1,
            'faillure' => 0,
            'response' => 'Demande rejetee avec succes',
        ]);

        $application->refresh();
        $this->assertEquals(Application::APPLICATION_DENIED, $application->state);
        Queue::assertPushed(ProcessSendEmail::class);
    }

    public function testDecisionOnNonexistentApplicationReturnsError()
    {
        $admin = $this->createAdminUser();
        Passport::actingAs($admin, ['ADMINISTRATOR']);

        $response = $this->postJson('/api/applications/nonexistent-app-id', [
            'action' => Application::APPLICATION_ACCEPTED,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => 0,
            'faillure' => 1,
            'raison' => 'Demande inexistant',
        ]);
    }

    public function testDecisionWithoutActionReturnsValidationError()
    {
        $admin = $this->createAdminUser();
        Passport::actingAs($admin, ['ADMINISTRATOR']);

        $response = $this->postJson('/api/applications/some-app-id', []);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => 0,
            'faillure' => 1,
        ]);
    }

    public function testGetApplicationPhotoPublic()
    {
        Storage::fake('local');
        $storedPath = Storage::disk('local')->put('application', UploadedFile::fake()->create('test.jpg', 10, 'image/jpeg'));

        $appId = 'app-photo-1';
        $application = new Application(
            $appId,
            'Photo',
            'User',
            'M',
            $storedPath,
            'CM',
            'Douala',
            'tech-center-id',
            'photo@example.com',
            '+237691179154',
            'comment',
            Application::DEFAULT_APPLICATION_STATE
        );
        $application->save();

        $response = $this->get("/api/applications/{$appId}/photo");
        $response->assertStatus(200);
    }
}
