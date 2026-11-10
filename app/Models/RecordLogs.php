<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RecordLogs extends Model
{
    protected $fillable = [
        'message',
        'type',
        'user_id',
        'desease_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function record()
    {
        return $this->belongsTo(Deseased::class, 'desease_id');
    }
}
