<?php

namespace Tests\Feature;

use App\Domain\Model\Identity\PasswordResetInvitation;
use App\Jobs\ProcessRestCall;
use App\Jobs\ProcessSendEmail;
use App\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Laravel\Passport\Passport;
use Tests\TestCase;
use Webpatser\Uuid\Uuid;

class PasswordWorkflowTest extends TestCase
{
    public function testRequestPasswordResetFailsForUnknownEmail()
    {
        $response = $this->postJson('/api/users/unknown@example.com/password-reset-request');

        $response->assertStatus(200);
        $response->assertJson([
            'success' => 0,
            'faillure' => 1,
        ]);
        $this->assertStringContainsString('Aucun utilisateur', $response->json('raison'));
    }

    public function testRequestPasswordResetFailsForBlockedUser()
    {
        $user = $this->createRegularUser([
            'email' => 'blocked_reset@example.com',
            'enablement' => false,
        ]);

        $response = $this->postJson("/api/users/{$user->email}/password-reset-request");

        $response->assertStatus(200);
        $response->assertJson([
            'success' => 0,
            'faillure' => 1,
            'raison' => 'Compte Bloque',
        ]);
    }

    public function testRequestPasswordResetCreatesInvitationAndDispatchesEmail()
    {
        Queue::fake();

        $user = $this->createRegularUser([
            'email' => 'valid_reset@example.com',
            'enablement' => true,
        ]);

        $response = $this->postJson("/api/users/{$user->email}/password-reset-request");

        $response->assertStatus(200);
        $response->assertJson([
            'success' => 1,
            'faillure' => 0,
        ]);

        $this->assertDatabaseHas('password_reset_invitations', [
            'email' => 'valid_reset@example.com',
            'used' => 0,
        ]);

        Queue::assertPushed(ProcessSendEmail::class);
    }

    public function testRequestPasswordResetReusesExistingValidInvitation()
    {
        Queue::fake();

        $user = $this->createRegularUser([
            'email' => 'existing_inv@example.com',
            'enablement' => true,
        ]);

        $invId = (string) Uuid::generate();
        $invitation = new PasswordResetInvitation(
            $invId,
            $user->firstname,
            $user->lastname,
            $user->email,
            "http://localhost:4200/reset-password/{$user->email}/{$invId}"
        );
        $invitation->save();

        $response = $this->postJson("/api/users/{$user->email}/password-reset-request");

        $response->assertStatus(200);
        $response->assertJson([
            'success' => 1,
            'faillure' => 0,
        ]);

        // Should still have exactly 1 unused invitation
        $count = PasswordResetInvitation::where('email', $user->email)->where('used', false)->count();
        $this->assertEquals(1, $count);
        Queue::assertPushed(ProcessSendEmail::class);
    }

    public function testResetPasswordFailsOnConfirmationMismatch()
    {
        $response = $this->postJson('/api/users/test@example.com/password-reset/inv-1', [
            'newpassword' => 'newpassword123',
            'newpassword_confirmation' => 'mismatchpass',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => 0,
            'faillure' => 1,
            'raison' => 'Le Mot de passe et sa confirmation sont distincts',
        ]);
    }

    public function testResetPasswordFailsOnInvalidInvitationId()
    {
        $user = $this->createRegularUser(['email' => 'reset_inv_fail@example.com']);

        $response = $this->postJson("/api/users/{$user->email}/password-reset/invalid-invitation-id", [
            'newpassword' => 'newpassword123',
            'newpassword_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => 0,
            'faillure' => 1,
            'raison' => 'Invitation Non existant ou Lien deja utilise',
        ]);
    }

    public function testResetPasswordFailsOnAlreadyUsedInvitation()
    {
        $user = $this->createRegularUser(['email' => 'used_inv@example.com']);

        $invId = (string) Uuid::generate();
        $invitation = new PasswordResetInvitation(
            $invId,
            $user->firstname,
            $user->lastname,
            $user->email,
            'http://localhost:4200/reset'
        );
        $invitation->used = true;
        $invitation->save();

        $response = $this->postJson("/api/users/{$user->email}/password-reset/{$invId}", [
            'newpassword' => 'newpassword123',
            'newpassword_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => 0,
            'faillure' => 1,
            'raison' => 'Invitation Non existant ou Lien deja utilise',
        ]);
    }

    public function testResetPasswordSuccess()
    {
        Queue::fake();

        $user = $this->createRegularUser([
            'email' => 'success_reset@example.com',
            'password' => 'oldpassword123',
            'mustresetpassword' => true,
        ]);

        $invId = (string) Uuid::generate();
        $invitation = new PasswordResetInvitation(
            $invId,
            $user->firstname,
            $user->lastname,
            $user->email,
            "http://localhost:4200/reset-password/{$user->email}/{$invId}"
        );
        $invitation->save();

        $response = $this->postJson("/api/users/{$user->email}/password-reset/{$invId}", [
            'newpassword' => 'brandnewpassword123',
            'newpassword_confirmation' => 'brandnewpassword123',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => 1,
            'faillure' => 0,
            'response' => 'Password Reset successfully',
        ]);

        // Invitation marked as used
        $invitation->refresh();
        $this->assertTrue((bool) $invitation->used);

        // Password updated and mustresetpassword cleared
        $user->refresh();
        $this->assertFalse((bool) $user->mustresetpassword);
        $this->assertTrue(Hash::check('brandnewpassword123', $user->password));

        // External update dispatched to debate service
        Queue::assertPushed(ProcessRestCall::class);
    }

    public function testChangePasswordRequiresAuthentication()
    {
        $response = $this->postJson('/api/users/some-id/change-password', [
            'currentpassword' => 'old',
            'newpassword' => 'newpass123',
            'newpassword_confirmation' => 'newpass123',
        ]);

        $response->assertStatus(401);
    }

    public function testChangePasswordFailsOnIncorrectCurrentPassword()
    {
        $user = $this->createRegularUser([
            'password' => 'correctpass123',
        ]);
        Passport::actingAs($user, ['USER']);

        $response = $this->postJson("/api/users/{$user->userid}/change-password", [
            'currentpassword' => 'wrongpass123',
            'newpassword' => 'newpass123',
            'newpassword_confirmation' => 'newpass123',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => 0,
            'faillure' => 1,
            'raison' => 'Mot de passe courant incorrect!',
        ]);
    }

    public function testChangePasswordFailsOnConfirmationMismatch()
    {
        $user = $this->createRegularUser([
            'password' => 'correctpass123',
        ]);
        Passport::actingAs($user, ['USER']);

        $response = $this->postJson("/api/users/{$user->userid}/change-password", [
            'currentpassword' => 'correctpass123',
            'newpassword' => 'newpass123',
            'newpassword_confirmation' => 'mismatchpass',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => 0,
            'faillure' => 1,
            'raison' => 'Echec de confirmation du mot de passe',
        ]);
    }

    public function testChangePasswordSuccess()
    {
        $user = $this->createRegularUser([
            'password' => 'correctpass123',
            'mustresetpassword' => true,
        ]);
        Passport::actingAs($user, ['USER']);

        $response = $this->postJson("/api/users/{$user->userid}/change-password", [
            'currentpassword' => 'correctpass123',
            'newpassword' => 'newstrongpassword123',
            'newpassword_confirmation' => 'newstrongpassword123',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => 1,
            'faillure' => 0,
            'response' => 'Mot de passe modifie avec succes',
        ]);

        $user->refresh();
        $this->assertTrue(Hash::check('newstrongpassword123', $user->password));
        $this->assertFalse((bool) $user->mustresetpassword);
    }

    public function testUpdateUserChangePasswordAction()
    {
        $user = $this->createRegularUser(['password' => 'oldpassword123']);
        Passport::actingAs($user, ['USER']);

        $response = $this->postJson("/api/users/{$user->userid}", [
            'action' => User::CHANGE_PASSWORD,
            'currentpassword' => 'oldpassword123',
            'newpassword' => 'updatedpass123',
            'newpassword_confirmation' => 'updatedpass123',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => 1,
            'faillure' => 0,
        ]);

        $user->refresh();
        $this->assertTrue(Hash::check('updatedpass123', $user->password));
    }

    public function testUpdateUserDeactivateAndReactivateActions()
    {
        $user = $this->createRegularUser(['enablement' => true]);
        Passport::actingAs($user, ['USER']);

        // Deactivate
        $deactivateResponse = $this->postJson("/api/users/{$user->userid}", [
            'action' => User::DEACTIVATE_USER,
        ]);

        $deactivateResponse->assertStatus(200);
        $deactivateResponse->assertJson([
            'success' => 1,
            'faillure' => 0,
        ]);

        $user->refresh();
        $this->assertFalse((bool) $user->enablement);

        // Deactivating already blocked user fails
        $deactivateAgain = $this->postJson("/api/users/{$user->userid}", [
            'action' => User::DEACTIVATE_USER,
        ]);
        $deactivateAgain->assertJson([
            'success' => 0,
            'faillure' => 1,
            'raison' => 'Utilisateur deja bloque',
        ]);

        // Reactivate
        $reactivateResponse = $this->postJson("/api/users/{$user->userid}", [
            'action' => User::REACTIVATE_USER,
        ]);

        $reactivateResponse->assertStatus(200);
        $reactivateResponse->assertJson([
            'success' => 1,
            'faillure' => 0,
        ]);

        $user->refresh();
        $this->assertTrue((bool) $user->enablement);

        // Reactivating already active user fails
        $reactivateAgain = $this->postJson("/api/users/{$user->userid}", [
            'action' => User::REACTIVATE_USER,
        ]);
        $reactivateAgain->assertJson([
            'success' => 0,
            'faillure' => 1,
            'raison' => 'Utilisateur deja actif',
        ]);
    }

    public function testUpdateUserUnsupportedAction()
    {
        $user = $this->createRegularUser();
        Passport::actingAs($user, ['USER']);

        $response = $this->postJson("/api/users/{$user->userid}", [
            'action' => 'SOME_INVALID_ACTION',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => 0,
            'faillure' => 1,
            'raison' => 'Action non supportee',
        ]);
    }
}
