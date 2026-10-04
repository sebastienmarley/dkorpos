<?php

namespace App\Models;

use App\Enums\InsurancePlan;
use BackedEnum;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Str;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $firstname
 * @property string $lastname
 * @property string $username
 * @property-read Role|null $role
 * @property int|null $position_id
 * @property int|null $store_id
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
 * @property bool $is_full_time
 * @property bool $has_group_insurance
 * @property InsurancePlan|null $insurance_plan
 * @property bool $is_salaried
 * @property string|null $hourly_rate
 * @property string|null $weekly_salary
 * @property bool $has_commission
 * @property string|null $commission_rate
 * @property bool $has_bonus
 * @property int|null $weekly_sales_target
 * @property int|null $bonus_amount
 * @property int|null $bonus_step
 * @property string|null $hours_per_day
 * @property string|null $vacation_days_accrued
 * @property string|null $vacation_hours_available
 * @property Carbon|null $vacation_balance_computed_at
 * @property string|null $address_civic
 * @property string|null $address_apartment
 * @property string|null $address_street
 * @property string|null $address_city
 * @property string|null $address_province
 * @property string|null $address_country
 * @property string|null $address_postal_code
 * @property Carbon|null $last_modified
 * @property int|null $last_modified_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['firstname', 'lastname', 'position_id', 'store_id', 'email', 'personal_email', 'password', 'is_active', 'first_day', 'last_day', 'phone', 'cellphone', 'address_civic', 'address_apartment', 'address_street', 'address_city', 'address_province', 'address_country', 'address_postal_code', 'is_full_time', 'has_group_insurance', 'insurance_plan', 'is_salaried', 'hourly_rate', 'weekly_salary', 'has_commission', 'commission_rate', 'has_bonus', 'weekly_sales_target', 'bonus_amount', 'bonus_step', 'hours_per_day'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token', 'first_day', 'last_day', 'last_modified', 'last_modified_by', 'vacation_hours_available'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    use HasRoles {
        HasRoles::hasPermissionTo as traitHasPermissionTo;
        HasRoles::getAllPermissions as traitGetAllPermissions;
    }

    protected $attributes = [
        'is_full_time' => false,
        'has_group_insurance' => false,
        'is_salaried' => false,
        'has_commission' => false,
        'has_bonus' => false,
    ];

    /** @var SupportCollection<int, string>|null */
    private ?SupportCollection $deniedPermissionNames = null;

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
            'is_full_time' => 'boolean',
            'has_group_insurance' => 'boolean',
            'insurance_plan' => InsurancePlan::class,
            'is_salaried' => 'boolean',
            'hourly_rate' => 'decimal:2',
            'weekly_salary' => 'decimal:2',
            'has_commission' => 'boolean',
            'commission_rate' => 'decimal:2',
            'has_bonus' => 'boolean',
            'weekly_sales_target' => 'integer',
            'bonus_amount' => 'integer',
            'bonus_step' => 'integer',
            'hours_per_day' => 'decimal:2',
            'vacation_days_accrued' => 'decimal:2',
            'vacation_hours_available' => 'decimal:2',
            'vacation_balance_computed_at' => 'datetime',
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
     * Permissions retirées à cet usager : elles priment sur celles de son rôle et sur ses permissions directes.
     *
     * @return BelongsToMany<Permission, $this>
     */
    public function deniedPermissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'user_denied_permissions');
    }

    /**
     * Noms des permissions retirées (lus une seule fois par instance).
     *
     * @return SupportCollection<int, string>
     */
    public function deniedPermissionNames(): SupportCollection
    {
        return $this->deniedPermissionNames ??= $this->deniedPermissions()->pluck('name');
    }

    /**
     * Synchronise les permissions retirées.
     *
     * @param  array<int, string>  $names
     */
    public function syncDeniedPermissions(array $names): void
    {
        $this->deniedPermissions()->sync(Permission::whereIn('name', $names)->pluck('id')->all());
        $this->deniedPermissionNames = null;
    }

    /**
     * Une permission retirée à l'usager prime sur son rôle et sur ses permissions directes.
     *
     * @param  string|int|Permission|BackedEnum  $permission
     */
    public function hasPermissionTo($permission, ?string $guardName = null): bool
    {
        $name = is_string($permission) ? $permission : ($permission instanceof Permission ? $permission->name : null);

        if ($name !== null && $this->deniedPermissionNames()->contains($name)) {
            return false;
        }

        return $this->traitHasPermissionTo($permission, $guardName);
    }

    /**
     * Permissions effectives : celles du rôle et les directes, moins les permissions retirées.
     *
     * @return Collection<int, Model>
     */
    public function getAllPermissions(): Collection
    {
        $denied = $this->deniedPermissionNames();

        return new Collection(array_values(array_filter(
            $this->traitGetAllPermissions()->all(),
            fn (Model $permission): bool => ! $denied->contains($permission->getAttribute('name')),
        )));
    }

    /**
     * Rôle unique de l'utilisateur (les permissions supplémentaires passent par les permissions directes).
     */
    public function getRoleAttribute(): ?Role
    {
        $role = $this->roles->first();

        return $role instanceof Role ? $role : null;
    }

    /**
     * Niveau hiérarchique du rôle (0 sans rôle).
     */
    public function roleLevel(): int
    {
        $role = $this->role;

        return $role === null ? 0 : $role->level;
    }

    /**
     * Rôles que cet utilisateur peut attribuer en créant ou en modifiant un usager :
     * ceux dont le niveau est inférieur ou égal au sien, s'il a le droit de créer ou modifier des usagers.
     *
     * @return Collection<int, Role>
     */
    public function assignableRoles(): Collection
    {
        if (! $this->canAny(['users.create', 'users.edit'])) {
            return new Collection;
        }

        return Role::query()->where('level', '<=', $this->roleLevel())->orderByDesc('level')->orderBy('label')->get();
    }

    /**
     * Rôles dont les fiches sont visibles : ceux qu'il peut gérer, sinon tous.
     *
     * @return Collection<int, Role>
     */
    public function visibleRoles(): Collection
    {
        $assignable = $this->assignableRoles();

        return $assignable->isNotEmpty() ? $assignable : Role::query()->orderByDesc('level')->orderBy('label')->get();
    }

    /**
     * Indique si le rôle donné fait partie des rôles visibles (un usager sans rôle l'est toujours).
     */
    public function canSeeRole(?Role $role): bool
    {
        return $role === null || $this->visibleRoles()->contains('id', $role->id);
    }

    /**
     * Limite la requête aux usagers que le visiteur a le droit de voir.
     *
     * @param  Builder<User>  $query
     */
    #[Scope]
    protected function visibleTo(Builder $query, self $viewer): void
    {
        $roleIds = $viewer->visibleRoles()->modelKeys();

        $query->where(fn (Builder $q) => $q->whereHas('roles', fn (Builder $r) => $r->whereIn('roles.id', $roleIds))
            ->orWhereDoesntHave('roles'));
    }

    /** @return BelongsTo<Store, $this> */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /** @return BelongsTo<Position, $this> */
    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
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
