<?php

namespace Tests;

use App\Models\User;
use Illuminate\Testing\TestResponse;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use LazilyRefreshDatabase;
    use CreatesApplication;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['name' => 'test_user']);
    }

    protected function createUser(array $data = []): User
    {
        return User::factory()->create($data);
    }

    protected function assertMessage(string $message, TestResponse $response): void
    {
        $this->assertEquals($message, $response->json('message'));
    }
}
