<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Remarks extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'desease_id',
        'user_id',
        'remarks',
        'type',
    ];

    protected $table = 'remarks';

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id'); // Adjust 'user_id' if your column name differs
    }

    public function deseased()
    {
        return $this->belongsTo(Deseased::class, 'desease_id'); // Adjust 'user_id' if your column name differs
    }

}
