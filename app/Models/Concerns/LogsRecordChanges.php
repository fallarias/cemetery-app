<?php
namespace App\Models\Concerns;

use App\Models\RecordLogs;
use Illuminate\Support\Arr;
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

            static::writeRecordLog($record, 'New Record', static::buildRecordLogMessage($record));
        });

        static::updated(function ($record) {
            if (app()->runningInConsole() || ! Auth::check()) {
                return;
            }

            // only the tracked fields that really changed (ignores updated_at, deleted_at, user_id, etc.)
            $changes = Arr::only($record->getChanges(), array_keys(static::$recordLogLabels));

            if (empty($changes)) {
                return;
            }

            static::writeRecordLog($record, 'Updated', static::buildRecordLogMessage($record, $changes));
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

    /**
     * One "Label: value" line per field that has a value.
     * Changed fields are shown as "Label: old → new".
     */
    protected static function buildRecordLogMessage($record, array $changes = []): string
    {
        $lines = [];

        foreach (static::$recordLogLabels as $field => $label) {
            if (array_key_exists($field, $changes)) {
                $lines[] = "{$label}: "
                    . static::displayRecordLogValue($field, $record->getOriginal($field))
                    . ' → '
                    . static::displayRecordLogValue($field, $changes[$field]);
                continue;
            }

            $current = $record->getAttribute($field);

            if ($current !== null && $current !== '') {
                $lines[] = "{$label}: " . static::displayRecordLogValue($field, $current);
            }
        }

        return implode('; ', $lines);
    }

    protected static function displayRecordLogValue(string $field, $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        if ($value instanceof \DateTimeInterface) {
            $value = $value->format('Y-m-d');
        }

        if (isset(static::$recordLogLookups[$field])) {
            [$table, $column] = static::$recordLogLookups[$field];
            $value = DB::table($table)->where('id', $value)->value($column) ?? $value;
        }

        return Str::limit((string) $value, 120);
    }

    protected static function writeRecordLog($record, string $type, string $message): void
    {
        try {
            RecordLogs::create([
                'message'    => $message,
                'type'       => $type,
                'user_id'    => Auth::id(),
                'desease_id' => $record->id,
            ]);
        } catch (\Throwable $e) {
            // never break saving a record because of logging
        }
    }
}
