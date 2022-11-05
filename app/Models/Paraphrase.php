<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $editor_id
 * @property string $interview_id
 * @property string $paraphrase
 * @property int position_start
 * @property int position_end
 * @property-read Editor $editor
 * @property-read Interview $interview
 */
class Paraphrase extends Model
{
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
