<?php

namespace App\Models;

use Database\Factories\customerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $firstname
 * @property string $lastname
 * @property string|null $phone
 * @property string|null $cellphone
 * @property string|null $email
 * @property string|null $adress
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['firstname', 'lastname', 'phone', 'cellphone', 'email', 'adress'])]
#[Hidden(['$adress'])]
class customer extends Model
{
    /** @use HasFactory<customerFactory> */
    use HasFactory;
}
