<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\ServerInvitation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        $plainToken = $input['invitation'] ?? null;
        $invitation = is_string($plainToken)
            ? ServerInvitation::findByPlainToken($plainToken)
            : null;

        $validator = Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
            'invitation' => ['nullable', 'string', 'size:64'],
        ]);

        $validator->after(function ($validator) use ($input, $plainToken, $invitation): void {
            if (! is_string($plainToken)) {
                return;
            }

            if ($invitation === null || $invitation->status !== ServerInvitation::STATUS_PENDING) {
                $validator->errors()->add('invitation', 'この招待リンクは無効か、すでに使用されています。');

                return;
            }

            if (mb_strtolower((string) ($input['email'] ?? '')) !== mb_strtolower($invitation->email)) {
                $validator->errors()->add('email', '招待されたメールアドレスで登録してください。');
            }
        });

        $validator->validate();

        return DB::transaction(function () use ($input): User {
            $user = User::create([
                'name' => $input['name'],
                'email' => $input['email'],
                'password' => $input['password'],
            ]);

            ServerInvitation::query()
                ->whereNull('user_id')
                ->where('status', ServerInvitation::STATUS_PENDING)
                ->whereRaw('LOWER(email) = ?', [Str::lower($user->email)])
                ->update(['user_id' => $user->id]);

            return $user;
        });
    }
}
