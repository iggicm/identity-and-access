<?php

namespace Tests\Feature;

use App\Interestcenter;
use App\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UtilityEndpointTest extends TestCase
{
    public function testGetInterestCenters()
    {
        Interestcenter::firstOrCreate(
            ['interestcenterid' => 'ic-science'],
            ['name' => 'Science', 'description' => 'Science research']
        );

        $response = $this->getJson('/api/interestcenters');

        $response->assertStatus(200);
        $response->assertJson([
            'success' => 1,
            'faillure' => 0,
        ]);
        $this->assertNotEmpty($response->json('response'));
    }

    public function testGetUsersCities()
    {
        $this->createRegularUser(['cityofresidency' => 'Buea', 'email' => 'city1@example.com']);
        $this->createRegularUser(['cityofresidency' => 'Garoua', 'email' => 'city2@example.com']);

        $response = $this->getJson('/api/users-cities');

        $response->assertStatus(200);
        $response->assertJson([
            'success' => 1,
            'faillure' => 0,
        ]);

        $cities = $response->json('response');
        $cityNames = array_column($cities, 'cityofresidency');

        $this->assertContains('Buea', $cityNames);
        $this->assertContains('Garoua', $cityNames);
    }

    public function testGetUserPhotoPublic()
    {
        Storage::fake('local');
        $storedPath = Storage::disk('local')->put('users', UploadedFile::fake()->create('avatar.jpg', 10, 'image/jpeg'));

        $user = $this->createRegularUser(['photo' => $storedPath]);

        $response = $this->get("/api/users/{$user->userid}/photo");
        $response->assertStatus(200);
    }

    public function testGetUserPhotoReturnsEmptyWhenNotFound()
    {
        $response = $this->get('/api/users/nonexistent-user-id/photo');
        $response->assertStatus(200);
    }

    public function testWsTestEndpoint()
    {
        $response = $this->postJson('/api/wstest', [
            'message' => 'Hello Realtime',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => 1,
            'faillure' => 0,
            'response' => 'broadcast successfully',
        ]);
    }
}
