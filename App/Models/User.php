<?php

namespace App\Models;

use Framework\Support\Model;

class User extends Model
{
    protected string $table = 'users';

    protected array $fillable = ['name', 'email'];
}
