<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
}
