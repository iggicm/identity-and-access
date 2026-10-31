<?php

namespace Tests\Feature;

use App\Domain\Model\Access\Role;
use App\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Passport\Client;
use Laravel\Passport\Passport;
use Laravel\Passport\Token;
use Tests\TestCase;
use Webpatser\Uuid\Uuid;

class AuthenticationWorkflowTest extends TestCase
{
    public function testLoginValidationFailsOnMissingFields()
    {
        $response = $this->postJson('/api/users/login', []);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => 0,
            'faillure' => 1,
        ]);
        $this->assertNotNull($response->json('raison'));
    }

    public function testLoginFailsForUnknownEmail()
    {
        $response = $this->postJson('/api/users/login', [
            'grant_type' => 'password',
            'client_id' => 1,
            'client_secret' => 'secret',
            'email' => 'unknown@example.com',
            'password' => 'secret123',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => 0,
            'faillure' => 1,
            'raison' => 'E-Mail ou mot de passe incorrect',
        ]);
    }

    public function testLoginFailsForDeactivatedUser()
    {
        $user = $this->createRegularUser([
            'email' => 'blocked@example.com',
            'enablement' => false,
        ]);

        $response = $this->postJson('/api/users/login', [
            'grant_type' => 'password',
            'client_id' => 1,
            'client_secret' => 'secret',
            'email' => 'blocked@example.com',
            'password' => 'secret123',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => 0,
            'faillure' => 1,
        ]);
        $this->assertStringContainsString('bloque', $response->json('raison'));
    }

    public function testLoginFailsWhenUserMustResetPassword()
    {
        $user = $this->createRegularUser([
            'email' => 'mustreset@example.com',
            'mustresetpassword' => true,
        ]);

        $response = $this->postJson('/api/users/login', [
            'grant_type' => 'password',
            'client_id' => 1,
            'client_secret' => 'secret',
            'email' => 'mustreset@example.com',
            'password' => 'secret123',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => 0,
            'faillure' => 1,
            'raison' => 'Vous devez choisir votre mot de passe',
        ]);
    }

    public function testLoginFailsOnIncorrectPassword()
    {
        $user = $this->createRegularUser([
            'email' => 'validuser@example.com',
            'password' => 'correct-pass',
        ]);

        $response = $this->postJson('/api/users/login', [
            'grant_type' => 'password',
            'client_id' => 1,
            'client_secret' => 'secret',
            'email' => 'validuser@example.com',
            'password' => 'wrong-pass',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => 0,
            'faillure' => 1,
            'raison' => 'Bad credentials',
        ]);
    }

    public function testConfirmAccessTokenWithValidScope()
    {
        $user = $this->createRegularUser();
        Passport::actingAs($user, ['USER']);

        $response = $this->postJson('/api/confirm-access-token', [
            'scope' => 'USER',
        ]);

        $response->assertStatus(200);
        $this->assertEquals($user->email, $response->json('email'));
    }

    public function testConfirmAccessTokenWithMissingScopeReturnsZero()
    {
        $user = $this->createRegularUser();
        Passport::actingAs($user, ['USER']);

        $response = $this->postJson('/api/confirm-access-token', [
            'scope' => 'SUPER_ADMINISTRATOR',
        ]);

        $response->assertStatus(200);
        $this->assertEquals('0', $response->getContent());
    }

    public function testConfirmAdminAccessTokenForAdminUser()
    {
        $admin = $this->createAdminUser();
        Passport::actingAs($admin, ['ADMINISTRATOR']);

        $response = $this->getJson('/api/confirm-admin-access-token');

        $response->assertStatus(200);
        $this->assertEquals($admin->email, $response->json('email'));

        $postResponse = $this->postJson('/api/confirm-admin-access-token');
        $postResponse->assertStatus(200);
        $this->assertEquals($admin->email, $postResponse->json('email'));
    }

    public function testConfirmAdminAccessTokenForRegularUserReturnsZero()
    {
        $user = $this->createRegularUser();
        Passport::actingAs($user, ['USER']);

        $response = $this->getJson('/api/confirm-admin-access-token');

        $response->assertStatus(200);
        $this->assertEquals('0', $response->getContent());
    }

    public function testLogoutRevokesTokensAndSetsDisconnected()
    {
        $user = $this->createRegularUser();
        Passport::actingAs($user, ['USER']);

        $response = $this->postJson("/api/users/{$user->userid}/logout");

        $response->assertStatus(200);
        $response->assertJson([
            'success' => 1,
            'faillure' => 0,
        ]);

        $user->refresh();
        $this->assertFalse((bool) $user->isconnected);
    }

    public function testLogoutWithUnknownUserReturnsError()
    {
        $user = $this->createRegularUser();
        Passport::actingAs($user, ['USER']);

        $response = $this->postJson('/api/users/nonexistent-user-id/logout');

        $response->assertStatus(200);
        $response->assertJson([
            'success' => 0,
            'faillure' => 1,
        ]);
    }

    public function testGetUsersRequiresAuthentication()
    {
        $response = $this->getJson('/api/users');
        $response->assertStatus(401);
    }

    public function testGetUsersReturnsOtherUsersExcludingCaller()
    {
        $currentUser = $this->createRegularUser(['userid' => 'current-u', 'email' => 'current@example.com']);
        $otherUser1 = $this->createRegularUser(['userid' => 'other-u1', 'email' => 'other1@example.com']);
        $otherUser2 = $this->createRegularUser(['userid' => 'other-u2', 'email' => 'other2@example.com']);

        Passport::actingAs($currentUser, ['USER']);

        $response = $this->getJson('/api/users');

        $response->assertStatus(200);
        $response->assertJson([
            'success' => 1,
            'faillure' => 0,
        ]);

        $returnedUsers = $response->json('response');
        $returnedUserIds = array_column($returnedUsers, 'userid');

        $this->assertNotContains('current-u', $returnedUserIds);
        $this->assertContains('other-u1', $returnedUserIds);
        $this->assertContains('other-u2', $returnedUserIds);
    }
}
