<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Paraphrase extends Model
{
    use HasFactory;
    public $timestamps = false;

    protected $fillable = [
        'editor_id',
        'interview_id',
        'paraphrase',
        'position_start',
        'position_end',
    ];

    public function interview()
    {
        return $this->belongsTo(Interview::class);
    }

    public function editor()
    {
        return $this->belongsTo(Editor::class);
    }
}
