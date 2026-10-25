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
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\password;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

/**
 * Crée le premier administrateur d'une installation neuve, où personne ne peut encore se connecter (ADR 0014).
 *
 * Dans un terminal, les réponses manquantes sont demandées et le mot de passe est saisi sans écho. Sans terminal
 * interactif (exécuteur de commandes de Laravel Cloud, -n), les options sont obligatoires et un mot de passe est
 * généré puis affiché une seule fois : à faire réinitialiser après la première connexion, puisque la sortie reste
 * dans l'historique des commandes.
 */
class CreateAdmin extends Command
{
    protected $signature = 'app:create-admin
        {--firstname= : Prénom}
        {--lastname= : Nom}
        {--store= : Nom du magasin d\'appartenance (créé s\'il n\'existe encore aucun magasin)}
        {--generate-password : Générer le mot de passe au lieu de le demander}';

    protected $description = 'Crée le premier administrateur (et le premier magasin si aucun n\'existe)';

    public function handle(): int
    {
        if (! Role::query()->where('name', 'admin')->exists()) {
            $this->error('Le rôle admin n\'existe pas : exécutez d\'abord php artisan db:seed --class=ReferenceDataSeeder.');

            return self::FAILURE;
        }

        // Seulement pour une base sans aucun usager : dès qu'un compte existe (même désactivé ou sans le rôle admin),
        // les comptes se créent dans l'application, où les permissions et la portée par magasin s'appliquent.
        if (User::query()->exists()) {
            $this->error('Des usagers existent déjà : cette commande ne sert qu\'au premier compte. Créez les autres dans l\'application.');

            return self::FAILURE;
        }

        // Même règle que Laravel pour Prompts : sans vrai terminal (STDIN), aucune question ne peut être posée, même si
        // la commande n'a pas reçu -n (exécuteur de commandes de Laravel Cloud).
        $interactive = $this->input->isInteractive() && ((defined('STDIN') && stream_isatty(STDIN)) || $this->laravel->runningUnitTests());

        $firstname = $this->option('firstname') ?: ($interactive ? text('Prénom', required: true) : null);
        $lastname = $this->option('lastname') ?: ($interactive ? text('Nom', required: true) : null);

        if (blank($firstname) || blank($lastname)) {
            $this->error('Sans terminal interactif, --firstname et --lastname sont obligatoires.');

            return self::FAILURE;
        }

        $stores = Store::query()->where('is_active', true)->orderBy('id')->pluck('name', 'id');
        $storeOption = $this->option('store');

        if (filled($storeOption)) {
            $storeId = Store::query()->where('is_active', true)->firstWhere('name', $storeOption)?->id;

            if ($storeId === null && $stores->isNotEmpty()) {
                $this->error("Aucun magasin actif ne s'appelle « {$storeOption} ». Magasins : ".$stores->implode(', ').'.');

                return self::FAILURE;
            }

            $storeName = $storeId === null ? $storeOption : null;
        } elseif ($interactive) {
            $storeId = $stores->isEmpty()
                ? null
                : (int) select('Magasin d\'appartenance', $stores->all(), default: $stores->keys()->first());

            $storeName = $storeId === null ? text('Aucun magasin n\'existe. Nom du premier magasin', required: true) : null;
        } else {
            $this->error('Sans terminal interactif, --store est obligatoire.');

            return self::FAILURE;
        }

        $generatePassword = $this->option('generate-password') || ! $interactive;

        if ($generatePassword) {
            // Str::password() ne garantit pas chaque catégorie (environ 1 sur 20 manque de majuscules ou de minuscules).
            $attempts = 0;

            do {
                $plainPassword = Str::password(12);
                $validator = Validator::make(['password' => $plainPassword], ['password' => ['required', Password::defaults()]]);
            } while ($validator->fails() && ++$attempts < 20);
        } else {
            $plainPassword = password('Mot de passe', required: true);
            $validator = Validator::make(
                ['password' => $plainPassword, 'password_confirmation' => password('Confirmez le mot de passe', required: true)],
                ['password' => ['required', 'confirmed', Password::defaults()]],
            );
        }

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

        if ($generatePassword) {
            $this->warn("Mot de passe (affiché une seule fois) : {$plainPassword}");
            $this->line('Faites-le réinitialiser par un autre administrateur après la première connexion.');
        }

        $this->line('La 2FA lui sera demandée à sa première connexion.');

        return self::SUCCESS;
    }
}
