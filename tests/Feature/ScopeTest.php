<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class ScopeTest extends TestCase
{
    /**
     * A basic feature test example.
     */
    public function test_example(): void
    {
        $this->actingAs($this->user);

        $user1 = User::factory()->create(['name' => 'name1', 'organization_id' => $this->organization->id]);
        $user2 = User::factory()->create(['name' => 'name2', 'organization_id' => $this->organization2->id]);

        $users = User::query()
            ->exceptMe()
            ->ownOrganization()
            ->get()
            ->toArray();

        $this->assertEquals(1, count($users));
    }
}
