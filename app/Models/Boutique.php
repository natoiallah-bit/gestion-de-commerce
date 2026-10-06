<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Boutique extends Model
{
    protected $table = 'boutique';

    protected $fillable = ['nom', 'adresse', 'telephone', 'pied_ticket'];

    // Une seule ligne de paramètres pour toute l'application
    public static function courante(): self
    {
        return static::query()->first() ?? static::create(['nom' => 'Ma Boutique']);
    }
}
