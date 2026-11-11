<?php

namespace App\Services;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Http\Request;

class CreatorResolverService
{
    /**
     * Resolve the active creator for the private dashboard.
     * Uses the authenticated user when present, or falls back to the primary creator account
     * (seeding the default creator profile if the database was freshly migrated).
     */
    public function resolveActiveCreator(?Request $request = null): User
    {
        if ($request?->user() instanceof User) {
            return $request->user();
        }

        $creator = User::query()->where('username', 'alex-dev')->first()
            ?? User::query()->first();

        if (! $creator) {
            app(DatabaseSeeder::class)->run();
            $creator = User::query()->firstOrFail();
        }

        return $creator;
    }
}

