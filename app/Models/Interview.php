<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property-read Collection<Paraphrase> $paraphrases
 * @property-read Collection<Editor> $editors
 */
class Interview extends Model
{
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'id'
    ];

    public function paraphrases()
    {
        return $this->hasMany(Paraphrase::class);
    }

    public function editors()
    {
        return $this->hasManyThrough(Editor::class, Paraphrase::class);
    }
}
