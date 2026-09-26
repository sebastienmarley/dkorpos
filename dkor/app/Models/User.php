<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $firstname
 * @property string $lastname
 * @property string $username
 * @property string $role
 * @property string $email
 * @property bool $is_active
 * @property date|null $first_day
 * @property date|null $last_day
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $personal_email
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['firstname', 'lastname', 'role', 'email', 'personal_email', 'password', 'is_active', 'first_day', 'last_day'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token', 'first_day', 'last_day'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'first_day' => 'date',
            'last_day' => 'date',
            'is_active' => 'boolean',
            'password' => 'hashed',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $user): void {
            if (blank($user->firstname) || blank($user->lastname)) {
                return;
            }

            $user->username = self::generateUniqueUsername($user->firstname, $user->lastname);
        });

        static::saving(function (self $user): void {
            if (blank($user->firstname) || blank($user->lastname)) {
                return;
            }

            if (blank($user->username)) {
                $user->username = self::generateUniqueUsername($user->firstname, $user->lastname);
            }
        });
    }

    /**
     * fonction pour savoir si l'usagé est actif.
     */
    public function getIsActiveAttribute(): bool
    {
        return (bool) ($this->attributes['is_active'] ?? false);
    }

    /**
     * fonction pour archiver un employé
     */
    public function setIsActiveAttribute(bool $value): void
    {
        $this->attributes['is_active'] = $value;
    }

    /**
     * Empêche un delete d'un usager par inadvertance, temporaire.
     */
    public function delete(): bool
    {
        if ($this->exists) {
            $this->forceFill(['is_active' => false])->save();
        }

        return true;
    }

    /**
     * Generate a unique username in the format firstname+lastname.
     */
    public static function generateUniqueUsername(string $firstName, string $lastName): string
    {
        $base = Str::of($firstName)->trim()->lower()->append(Str::of($lastName)->trim()->lower())->value();

        $candidate = $base;
        $suffix = 1;

        while (static::query()->whereRaw('LOWER(username) = ?', [mb_strtolower($candidate)])->exists()) {
            $candidate = $base.$suffix;
            $suffix++;
        }

        return $candidate;
    }

    /**
     * Get the user's full name from the profile fields.
     */
    public function fullName(): string
    {
        return trim(($this->firstname ?? '').' '.($this->lastname ?? ''));
    }

    /**
     * Resolve a username for a new employee and expose duplicate/returning-user status.
     *
     * @return array{username: string, existingUser: ?self, requiresVerification: bool, reactivated: bool}
     */
    public static function resolveUsernameForEmployee(string $firstName, string $lastName): array
    {
        $base = Str::of($firstName)->trim()->lower()->append(Str::of($lastName)->trim()->lower())->value();
        $candidate = $base;
        $suffix = 1;

        while (true) {
            $existingUser = static::query()->whereRaw('LOWER(username) = ?', [mb_strtolower($candidate)])->first();

            if (! $existingUser) {
                return [
                    'username' => $candidate,
                    'existingUser' => null,
                    'requiresVerification' => false,
                    'reactivated' => false,
                ];
            }

            if (! $existingUser->is_active) {
                $existingUser->forceFill(['is_active' => true])->save();

                return [
                    'username' => $candidate,
                    'existingUser' => $existingUser,
                    'requiresVerification' => false,
                    'reactivated' => true,
                ];
            }

            if ($candidate === $base) {
                return [
                    'username' => $base.$suffix,
                    'existingUser' => $existingUser,
                    'requiresVerification' => true,
                    'reactivated' => false,
                ];
            }

            $candidate = $base.$suffix;
            $suffix++;
        }
    }

    /**
     * Prevent manual username assignment.
     */
    public function setUsernameAttribute(?string $value): void
    {
        if (blank($value) && filled($this->firstname) && filled($this->lastname)) {
            $this->attributes['username'] = self::generateUniqueUsername($this->firstname, $this->lastname);

            return;
        }

        $this->attributes['username'] = blank($value)
            ? null
            : self::generateUniqueUsername($this->firstname ?? '', $this->lastname ?? '');
    }

    /**
     * Pour obtenir les initiales de l'utilisateur. (icone de profilde la sidenav)
     */
    public function initials(): string
    {
        $firstName = $this->firstname ? Str::substr($this->firstname, 0, 1) : '';
        $lastName = $this->lastname ? Str::substr($this->lastname, 0, 1) : '';

        return Str::upper($firstName.$lastName);
    }
}
