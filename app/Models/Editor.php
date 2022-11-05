<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
}
