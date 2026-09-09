<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class City extends Model
{
    // Указываем таблицу пакета nnjeim/world
    protected $table = 'cities';

    protected $fillable = ['name', 'country_id', 'state_id', 'timezone'];
    
    // Связь: Город принадлежит Стране
    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    // НОВАЯ СВЯЗЬ: Город принадлежит Региону (State)
    public function state()
    {
        return $this->belongsTo(State::class, 'state_id');
    }
}