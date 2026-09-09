<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Country extends Model
{
    protected $table = 'countries'; // Указываем таблицу пакета world

    protected $fillable = ['name', 'iso2', 'iso3', 'phone_code'];
    
    public function cities()
    {
        return $this->hasMany(City::class);
    }
}