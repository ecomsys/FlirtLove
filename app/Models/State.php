<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class State extends Model
{
    // Указываем, что модель работает с таблицей пакета nnjeim/world
    protected $table = 'states';

    protected $fillable = ['name', 'country_id', 'state_code'];
    
    // Связь: Регион принадлежит Стране
    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    // Связь: В регионе много Городов
    public function cities()
    {
        return $this->hasMany(City::class);
    }
}