<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Str;

class KohaAuthService
{
    public function __construct(protected KohaService $kohaService)
    {
    }

    /**
     * Check credentials against Koha.
     *
     * Returns the matching local user when Koha accepts the credentials, or null when it
     * does not. Throws if Koha cannot be reached.
     */
    public function attempt(string $identifier, string $password): ?User
    {
        $validated = $this->kohaService->validatePatronPassword($identifier, $password);

        if (! $validated) {
            return null;
        }

        $patronId = (string) $validated['patron_id'];
        $cardnumber = $validated['cardnumber'] ?? $identifier;

        $patron = $this->kohaService->getPatronByCardnumber($cardnumber) ?? [];

        $name = trim(($patron['firstname'] ?? '') . ' ' . ($patron['surname'] ?? ''));
        if ($name === '') {
            $name = $cardnumber;
        }

        $user = User::where('koha_patron_id', $patronId)->first();

        if ($user) {
            if ($user->name !== $name) {
                $user->update(['name' => $name]);
            }

            return $user;
        }

        $user = User::create([
            'name' => $name,
            'email' => $this->pickEmail($patron['email'] ?? null, $patronId),
            'password' => Str::random(40),
            'koha_patron_id' => $patronId,
        ]);

        $user->forceFill(['email_verified_at' => now()])->save();

        return $user;
    }

    /**
     * Use the Koha email when it is free, otherwise a placeholder,
     * because the users table needs a unique email for every row.
     */
    protected function pickEmail(?string $email, string $patronId): string
    {
        $email = strtolower(trim((string) $email));

        if ($email !== '' && ! User::where('email', $email)->exists()) {
            return $email;
        }

        return "koha-{$patronId}@library.invalid";
    }
}