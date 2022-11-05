<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property-read Collection<Paraphrase> $paraphrases
 * @property-read Collection<Interview> $interviews
 */
class Editor extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'name'
    ];

    public function paraphrases()
    {
        return $this->hasMany(Paraphrase::class);
    }

    public function interviews()
    {
        return $this->belongsToMany(Interview::class, 'paraphrases');
    }
}
