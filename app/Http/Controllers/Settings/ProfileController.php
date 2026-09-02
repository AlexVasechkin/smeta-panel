<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Models\User;
use App\Support\Passport;
use DateTimeInterface;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/profile', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
            'passport' => $this->passportPayload($request->user()),
            'passportTypes' => Passport::typeOptions(),
        ]);
    }

    /**
     * Update the user's profile settings.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();

        $user->fill($request->safe()->except('passport'));

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        $this->syncPassport($user, $request);

        return to_route('profile.edit');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    /**
     * Сохранить паспортные данные пользователя в EAV-свойства.
     */
    private function syncPassport(User $user, FormRequest $request): void
    {
        $passport = $request->validated('passport') ?? [];

        foreach (Passport::FIELDS as $key => $definition) {
            if (array_key_exists($key, $passport)) {
                $user->setEav($definition['code'], $passport[$key]);
            }
        }
    }

    /**
     * Паспортные данные пользователя для фронтенда.
     *
     * @return array<string, string|null>
     */
    private function passportPayload(User $user): array
    {
        $values = $user->eav();
        $passport = [];

        foreach (Passport::FIELDS as $key => $definition) {
            $value = $values[$definition['code']] ?? null;
            $passport[$key] = $value instanceof DateTimeInterface
                ? $value->format('Y-m-d')
                : $value;
        }

        return $passport;
    }
}
