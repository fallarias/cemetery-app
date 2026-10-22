<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cedula extends Model
{
    protected $fillable = [
        'serial',
        'date_issued',
        'place_of_issue',
        'surname',
        'first_name',
        'middle_name',
        'tin',
        'address',
        'sex',
        'civil',
        'citizenship',
        'icr_no',
        'place_of_birth',
        'height',
        'weight',
        'basic_tax_due',
        'additional_tax_due',
        'item1_due',
        'item2_due',
        'item3_due',
        'total',
        'interest',
        'total_paid',
        'in_words',
        'treasurer',
        'date_of_birth',
        'user_id'
    ];

    protected $casts = [
        'date_issued'        => 'date:Y-m-d',
        'basic_tax_due'      => 'decimal:2',
        'additional_tax_due' => 'decimal:2',
        'item1_due'          => 'decimal:2',
        'item2_due'          => 'decimal:2',
        'item3_due'          => 'decimal:2',
        'total'              => 'decimal:2',
        'interest'           => 'decimal:2',
        'total_paid'         => 'decimal:2',
        'date_of_birth'      => 'date:Y-m-d',
    ];

    // Replaces the old full_name column: $cedula->full_name
    public function getFullNameAttribute(): string
    {
        return trim("{$this->surname}, {$this->first_name} {$this->middle_name}");
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id'); // Adjust 'user_id' if your column name differs
    }

    protected $appends = ['full_name'];
}
