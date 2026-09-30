<?php

namespace App\Models;

use App\Enums\RoleType;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $firstname
 * @property string $lastname
 * @property string $username
 * @property RoleType $role
 * @property string $email
 * @property bool $is_active
 * @property Carbon|null $first_day
 * @property Carbon|null $last_day
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $personal_email
 * @property string|null $phone
 * @property string|null $cellphone
 * @property string|null $remember_token
 * @property Carbon|null $last_modified
 * @property int|null $last_modified_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['firstname', 'lastname', 'role', 'email', 'personal_email', 'password', 'is_active', 'first_day', 'last_day', 'phone', 'cellphone'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token', 'first_day', 'last_day', 'last_modified', 'last_modified_by'])]
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
            'last_modified' => 'datetime:Y-m-d H:i',
            'is_active' => 'boolean',
            'password' => 'hashed',
            'role' => RoleType::class,
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
     * Resolve a username for a new employee and expose duplicate status.
     * Un nom d'utilisateur déjà pris par un employé inactif est simplement ignoré (jamais de réactivation ici).
     *
     * @return array{username: string, existingUser: ?self, requiresVerification: bool}
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
                ];
            }

            if ($existingUser->is_active && $candidate === $base) {
                return [
                    'username' => $base.$suffix,
                    'existingUser' => $existingUser,
                    'requiresVerification' => true,
                ];
            }

            $candidate = $base.$suffix;
            $suffix++;
        }
    }

    /**
     * Employés inactifs portant le même prénom et nom (pour un éventuel retour d'employé),
     * limités à ceux que $viewer a le droit de voir.
     *
     * @return Collection<int, static>
     */
    public static function inactiveMatchesForName(string $firstName, string $lastName, ?self $viewer = null): Collection
    {
        return static::query()
            ->when($viewer, fn (Builder $query) => $query->visibleTo($viewer))
            ->where('is_active', false)
            ->whereRaw('LOWER(firstname) = ?', [mb_strtolower(trim($firstName))])
            ->whereRaw('LOWER(lastname) = ?', [mb_strtolower(trim($lastName))])
            ->orderBy('id')
            ->get();
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
     * Rôles que cet utilisateur peut attribuer en créant ou en modifiant un usager.
     * Vide pour les rôles qui ne gèrent pas les utilisateurs.
     *
     * @return array<int, RoleType>
     */
    public function assignableRoles(): array
    {
        return match ($this->role) {
            RoleType::Admin, RoleType::Owner, RoleType::Manager => $this->visibleRoles(),
            default => [],
        };
    }

    /**
     * Rôles dont les fiches sont visibles pour cet utilisateur (un Manager ne voit ni Admin ni Owner).
     *
     * @return array<int, RoleType>
     */
    public function visibleRoles(): array
    {
        if ($this->role !== RoleType::Manager) {
            return RoleType::cases();
        }

        return array_values(array_filter(
            RoleType::cases(),
            fn (RoleType $role): bool => ! in_array($role, [RoleType::Admin, RoleType::Owner], true),
        ));
    }

    /**
     * Limite la requête aux usagers que le visiteur a le droit de voir.
     *
     * @param  Builder<User>  $query
     */
    #[Scope]
    protected function visibleTo(Builder $query, self $viewer): void
    {
        $query->whereIn('role', array_column($viewer->visibleRoles(), 'value'));
    }

    /**
     * Enregistre qui a modifié la fiche et quand (sans sauvegarder).
     */
    public function markModifiedBy(?self $modifier): static
    {
        return $this->forceFill([
            'last_modified' => now(),
            'last_modified_by' => $modifier?->id,
        ]);
    }

    /** @return BelongsTo<User, $this> */
    public function lastModifiedBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'last_modified_by');
    }

    /** @return HasMany<Schedule, $this> */
    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class);
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
