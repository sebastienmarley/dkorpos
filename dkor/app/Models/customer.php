<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $firstname
 * @property string $lastname
 * @property string $phone
 * @property string $cellphone
 * @property string $email
 * @property string $adress
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['firstname', 'lastname', 'phone', 'cellphone', 'email', 'adress'])]
#[Hidden(['$adress'])]
class customer extends Model
{
    //
}
