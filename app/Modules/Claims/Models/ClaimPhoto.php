<?php

declare(strict_types=1);

namespace App\Modules\Claims\Models;

use Illuminate\Database\Eloquent\Model;

class ClaimPhoto extends Model
{
    protected $fillable = ['claim_id', 'path', 'mime', 'size'];

    /** Le chemin de stockage ne sort jamais de l'API : seule la route autorisée sert le fichier. */
    protected $hidden = ['path'];
}
