<?php

namespace App\Services;

use App\Models\Invitation;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InvitationService
{
    public function create(
        Workspace $workspace,
        ?User $actor,
        string $email,
        ?string $roleId = null,
        ?Carbon $expiresAt = null
    ): array {
        $normalizedEmail = Str::lower(trim($email));
        $this->ensureWorkspaceDoesNotAlreadyContain($workspace, $normalizedEmail);

        $invitation = Invitation::query()
            ->where('workspace_id', $workspace->id)
            ->where('email', $normalizedEmail)
            ->whereNull('accepted_at')
            ->latest('created_at')
            ->first() ?? new Invitation();

        $plainToken = Str::random(64);

        $invitation->forceFill([
            'workspace_id' => $workspace->id,
            'role_id' => $roleId,
            'email' => $normalizedEmail,
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => $expiresAt ?? now()->addDays(7),
            'accepted_at' => null,
            'invited_by' => $actor?->id,
        ])->save();

        return [
            $invitation->fresh([
                'workspace',
                'role',
                'invitedBy',
            ]),
            $plainToken,
        ];
    }

    public function preview(string $token): Invitation
    {
        return $this->resolveValidInvitation($token)->load([
            'workspace',
            'role',
            'invitedBy',
        ]);
    }

    public function acceptForUser(User $user, string $token): array
    {
        $invitation = $this->resolveValidInvitation($token);

        if (Str::lower($user->email) !== Str::lower($invitation->email)) {
            throw ValidationException::withMessages([
                'token' => [
                    'This invitation belongs to a different email address.',
                ],
            ]);
        }

        $membership = $this->activateMembership($user, $invitation);

        $invitation->forceFill([
            'accepted_at' => now(),
        ])->save();

        return [
            'invitation' => $invitation->fresh([
                'workspace',
                'role',
            ]),
            'membership' => $membership,
        ];
    }

    public function acceptForRegistration(array $data): array
    {
        $invitation = $this->resolveValidInvitation((string) $data['invitation_token']);
        $normalizedEmail = Str::lower((string) $data['email']);

        if ($normalizedEmail !== Str::lower($invitation->email)) {
            throw ValidationException::withMessages([
                'email' => [
                    'This email does not match the invitation.',
                ],
            ]);
        }

        $user = User::query()->create([
            'name' => trim("{$data['first_name']} {$data['last_name']}"),
            'email' => $normalizedEmail,
            'password' => $data['password'],
            'locale' => $data['locale'] ?: config('app.locale'),
            'timezone' => $data['timezone'] ?: 'UTC',
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        $membership = $this->activateMembership($user, $invitation);

        $invitation->forceFill([
            'accepted_at' => now(),
        ])->save();

        return [
            'user' => $user,
            'invitation' => $invitation->fresh([
                'workspace',
                'role',
            ]),
            'membership' => $membership,
        ];
    }

    public function buildAcceptUrl(string $token): string
    {
        return sprintf(
            '%s/register?invitationToken=%s',
            rtrim((string) config('app.frontend_url', config('app.url')), '/'),
            urlencode($token),
        );
    }

    private function ensureWorkspaceDoesNotAlreadyContain(
        Workspace $workspace,
        string $email
    ): void {
        $existingUser = User::query()
            ->where('email', $email)
            ->first();

        if (!$existingUser) {
            return;
        }

        $existingMembership = WorkspaceMembership::query()
            ->where('workspace_id', $workspace->id)
            ->where('user_id', $existingUser->id)
            ->whereIn('status', [
                'pending',
                'active',
                'suspended',
            ])
            ->first();

        if ($existingMembership) {
            throw ValidationException::withMessages([
                'email' => [
                    'This user already belongs to the workspace.',
                ],
            ]);
        }
    }

    private function resolveValidInvitation(string $token): Invitation
    {
        $invitation = Invitation::query()
            ->where('token_hash', hash('sha256', trim($token)))
            ->first();

        if (!$invitation) {
            throw ValidationException::withMessages([
                'token' => [
                    'Invitation token is invalid.',
                ],
            ]);
        }

        if ($invitation->accepted_at) {
            throw ValidationException::withMessages([
                'token' => [
                    'Invitation has already been accepted.',
                ],
            ]);
        }

        if ($invitation->expires_at->isPast()) {
            throw ValidationException::withMessages([
                'token' => [
                    'Invitation has expired.',
                ],
            ]);
        }

        return $invitation;
    }

    private function activateMembership(
        User $user,
        Invitation $invitation
    ): WorkspaceMembership {
        $membership = WorkspaceMembership::query()
            ->firstOrNew([
                'workspace_id' => $invitation->workspace_id,
                'user_id' => $user->id,
            ]);

        $membership->forceFill([
            'role_id' => $invitation->role_id,
            'status' => 'active',
            'joined_at' => $membership->joined_at ?? now(),
        ])->save();

        return $membership->fresh([
            'workspace',
            'role.permissions',
            'user',
        ]);
    }
}
