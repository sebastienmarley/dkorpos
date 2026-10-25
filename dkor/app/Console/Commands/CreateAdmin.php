<?php

namespace App\Console\Commands;

use App\Enums\StoreType;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\password;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

/**
 * Crée le premier administrateur d'une installation neuve, où personne ne peut encore se connecter (ADR 0014).
 * Le mot de passe est demandé sans écho : il n'apparaît ni à l'écran ni dans l'historique du terminal.
 */
class CreateAdmin extends Command
{
    protected $signature = 'app:create-admin';

    protected $description = 'Crée le premier administrateur (et le premier magasin si aucun n\'existe)';

    public function handle(): int
    {
        if (! Role::query()->where('name', 'admin')->exists()) {
            $this->error('Le rôle admin n\'existe pas : exécutez d\'abord php artisan db:seed --class=ReferenceDataSeeder.');

            return self::FAILURE;
        }

        if (User::role('admin')->exists()) {
            $this->error('Un administrateur existe déjà : créez les autres comptes dans l\'application.');

            return self::FAILURE;
        }

        $firstname = text('Prénom', required: true);
        $lastname = text('Nom', required: true);

        $stores = Store::query()->where('is_active', true)->orderBy('id')->pluck('name', 'id');

        $storeId = $stores->isEmpty()
            ? null
            : (int) select('Magasin d\'appartenance', $stores->all(), default: $stores->keys()->first());

        $storeName = $storeId === null ? text('Aucun magasin n\'existe. Nom du premier magasin', required: true) : null;

        $plainPassword = password('Mot de passe', required: true);

        $validator = Validator::make(
            ['password' => $plainPassword, 'password_confirmation' => password('Confirmez le mot de passe', required: true)],
            ['password' => ['required', 'confirmed', Password::defaults()]],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $user = DB::transaction(function () use ($firstname, $lastname, $storeId, $storeName, $plainPassword): User {
            $storeId ??= Store::query()->create([
                'name' => $storeName,
                'type' => StoreType::Physical,
                'opening_hours' => Store::defaultOpeningHours(),
                'is_active' => true,
            ])->id;

            $username = User::generateUniqueUsername($firstname, $lastname);

            $user = new User;
            $user->firstname = $firstname;
            $user->lastname = $lastname;
            $user->username = $username;
            $user->email = $username.'@dkor.ca';
            $user->store_id = $storeId;
            $user->is_active = true;
            $user->first_day = Carbon::today();
            $user->password = $plainPassword;
            $user->save();
            $user->assignRole('admin');

            return $user;
        });

        $this->info("Administrateur créé. Nom d'utilisateur : {$user->username}");
        $this->line('La 2FA lui sera demandée à sa première connexion.');

        return self::SUCCESS;
    }
}
