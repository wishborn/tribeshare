<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * NOTE: deliberately NOT using WithoutModelEvents. Domain invariants are
     * enforced through model events — the ledger derives its month key on
     * create and refuses updates and deletes outright — so muting events
     * would seed data the application itself could never have produced, and
     * would skip the very guards worth exercising.
     */
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $this->call(TribeShareDemoSeeder::class);
    }
}
