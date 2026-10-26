<?php

namespace App\Models;

use App\Models\Concerns\LogsChanges;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Deseased extends Model
{
    use HasFactory, SoftDeletes, LogsChanges;

    protected $fillable = [
        'surname',
        'givenname',
        'middlename',
        'suffix',
        'name_of_payor',
        'date_of_payment',
        'or_number',
        'date_of_death',
        'year_covered',
        'amount_due',
        'user_id',
        'rental_id',
        'location_id',
        'status_id',
    ];

    protected $table = 'deseased_info';

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id'); // Adjust 'user_id' if your column name differs
    }

    public function rental()
    {
        return $this->belongsTo(Rental::class, 'rental_id'); // Adjust 'user_id' if your column name differs
    }

    public function location()
    {
        return $this->belongsTo(Location::class, 'location_id'); // Adjust 'user_id' if your column name differs
    }

    public function status()
    {
        return $this->belongsTo(Status::class, 'status_id'); // Adjust 'user_id' if your column name differs
    }

    public function remarks(){
        return $this->hasMany(Remarks::class, 'desease_id');
    }

    public function latestRemark()
    {
        return $this->hasOne(Remarks::class, 'desease_id')->latestOfMany();
    }

    public function latestEdit()
    {
        return $this->hasOne(Remarks::class, 'desease_id')->ofMany(
            ['id' => 'max'],                                  // latest = highest id
            fn ($query) => $query->where('type', 'EDIT')      // only EDIT rows
        );
    }

    public function scopeCoveringYears($query, int $from, int $to)
    {
        return $query->whereRaw(
            'CAST(LEFT(year_covered, 4) AS UNSIGNED) <= ? AND CAST(RIGHT(year_covered, 4) AS UNSIGNED) >= ?',
            [$to, $from]
        );
    }

    public function scopeCoveringDeath($query, int $from, int $to)
    {
        if ($from > $to) {
            [$from, $to] = [$to, $from];   // guard against reversed input
        }

        return $query->where('date_of_death', '>=', "{$from}-01-01")
                    ->where('date_of_death', '<',  ($to + 1) . '-01-01');
    }

    public function scopeCoveringPayment($query, int $from, int $to)
    {
        if ($from > $to) {
            [$from, $to] = [$to, $from];   // guard against reversed input
        }

        return $query->where('date_of_payment', '>=', "{$from}-01-01")
                    ->where('date_of_payment', '<',  ($to + 1) . '-01-01');
    }
}
