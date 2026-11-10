<?php
namespace App\Models\Concerns;

use App\Models\RecordLogs;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;


/**
 * @method static void created(\Closure|string $callback)
 * @method static void updated(\Closure|string $callback)
 */

trait LogsRecordChanges
{
    public static function bootLogsRecordChanges(): void
    {
        static::created(function ($record) {
            if (app()->runningInConsole() || ! Auth::check()) {
                return; // skips Artisan imports (user_id is required)
            }

            $name = trim("{$record->surname}, {$record->givenname}");

            static::writeRecordLog($record, 'New Record', "New record created: {$name} (OR# {$record->or_number})");
        });

        static::updated(function ($record) {
            if (app()->runningInConsole() || ! Auth::check()) {
                return;
            }

            $parts = [];

            foreach ($record->getChanges() as $field => $new) {
                if (! isset(static::$recordLogLabels[$field])) {
                    continue; // ignores updated_at, deleted_at, user_id, etc.
                }

                $old = $record->getOriginal($field);
                $parts[] = static::$recordLogLabels[$field] . ': '
                    . static::displayRecordLogValue($field, $old) . ' → ' . static::displayRecordLogValue($field, $new);
            }

            if ($parts) {
                static::writeRecordLog($record, 'Updated', implode('; ', $parts));
            }
        });
    }

    protected static array $recordLogLabels = [
        'surname'         => 'Surname',
        'givenname'       => 'Given Name',
        'middlename'      => 'Middle Name',
        'suffix'          => 'Suffix',
        'name_of_payor'   => 'Payor',
        'or_number'       => 'OR Number',
        'rental_id'       => 'Rental Type',
        'status_id'       => 'Status',
        'location_id'     => 'Location',
        'date_of_death'   => 'Date of Death',
        'date_of_payment' => 'Date of Payment',
        'year_covered'    => 'Year Covered',
        'amount_due'      => 'Amount Due',
    ];

    // foreign key => [table, column holding the readable name]
    protected static array $recordLogLookups = [
        'rental_id'   => ['rentals',   'rental_type'],
        'status_id'   => ['status',    'status'],
        'location_id' => ['locations', 'location'],
    ];

    protected static function displayRecordLogValue(string $field, $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        if (isset(static::$recordLogLookups[$field])) {
            [$table, $column] = static::$recordLogLookups[$field];
            $value = DB::table($table)->where('id', $value)->value($column) ?? $value;
        }

        return Str::limit((string) $value, 40);
    }

    protected static function writeRecordLog($record, string $type, string $message): void
    {
        try {
            RecordLogs::create([
                'message'    => Str::limit($message, 255),
                'type'       => $type,
                'user_id'    => Auth::id(),
                'desease_id' => $record->id,
            ]);
        } catch (\Throwable $e) {
            // never break saving a record because of logging
        }
    }
}
