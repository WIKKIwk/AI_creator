<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthTest extends TestCase
{
    public function test_register(): void
    {
        $response = $this->postJson(
            route('register'),
            $payload = [
                'name' => 'Test User',
                'email' => 'email@mail.com',
                'password' => 'password',
                'password_confirmation' => 'password',
            ]
        );
        $response->assertStatus(201);
        $response->assertJsonStructure(['token']);

        $this->assertDatabaseHas('users', [
            'name' => $payload['name'],
            'email' => $payload['email'],
        ]);
    }

    public function test_login_wrong_credentials(): void
    {
        $response = $this->postJson(route('login'), [
            'email' => 'mail@mail.com',
            'password' => 'password',
        ]);
        $response->assertStatus(400);
        $response->assertJsonStructure(['message']);
        $this->assertEquals('Wrong credentials', $response->json('message'));
    }

    public function test_login_success(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make($password = '123123'),
        ]);

        $response = $this->postJson(route('login'), [
            'email' => $user->email,
            'password' => $password,
        ]);
        $response->assertStatus(200);
        $response->assertJsonStructure(['token']);

        $this->assertNotEmpty($response->json('token'));
        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'tokenable_type' => User::class,
            'name' => 'authToken'
        ]);
    }

    public function test_logout_401(): void
    {
        $response = $this->postJson(route('logout'));
        $response->assertStatus(401);
    }

    public function test_logout_success(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);
        $response = $this->postJson(route('logout'));
        $response->assertStatus(200);
        $response->assertJsonStructure(['message']);
        $this->assertEquals('Logged out', $response->json('message'));
    }

    public function test_me(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);
        $response = $this->postJson(route('me'));
        $response->assertStatus(200);

        $response->assertJsonStructure(['id', 'name', 'email']);
        $this->assertEquals($user->id, $response->json('id'));
    }
}
