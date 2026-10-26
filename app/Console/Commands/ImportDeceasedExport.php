<?php

namespace App\Console\Commands;

use App\Models\{Deseased, Rental, Status, Location, Remarks, Logs, User};
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as XlDate;

class ImportDeceasedExport extends Command
{
    protected $signature = 'import:deceased-export {file} {--dry-run}';
    protected $description = 'Re-import deceased records from the system-exported Excel';

    private const DEFAULT_LOCATION = 'OLD CEMETERY';
    private const DEFAULT_RENTAL   = 'UNKNOWN';
    private const DEFAULT_STATUS   = 'UNKNOWN';

    private array $cache = [];

    public function handle()
    {
        $path = base_path($this->argument('file'));
        if (!is_file($path)) return $this->error("File not found: $path");

        $rows = IOFactory::load($path)->getActiveSheet()->toArray(null, true, false, false);
        array_shift($rows);                    // header
        $rows = array_reverse($rows, true);    // export is newest-first, restore oldest-first ids
        $this->seedUsers($rows);

        $fallbackUser = User::where('account_type', 'Admin')->value('id') ?? User::value('id');
        $dry = $this->option('dry-run');
        $ok = $fail = 0;
        $bar = $this->output->createProgressBar(count($rows));

        foreach ($rows as $i => $r) {
            $bar->advance();
            $line = $i + 2;
            if (!array_filter($r)) continue;

            try {
                [, $name, $dod, $or, $payor, $dop, $rental, $years, $amount,
                 $status, $type, $loc, $encBy, $encAt, $remarks, $modBy, $modAt] = array_pad($r, 17, null);

                // "SURNAME, GIVEN MIDDLE SUFFIX": middle/suffix can't be separated, so they stay in givenname
                $name = preg_replace('/\s+/', ' ', trim((string) $name));
                [$surname, $given] = str_contains($name, ',')
                    ? array_map('trim', explode(',', $name, 2))
                    : [$name, null];
                $surname = $surname ?: 'UNKNOWN';
                $given   = $given   ?: 'UNKNOWN';

                $orNo    = trim((string) $or) ?: '0';
                $years   = trim((string) $years) ?: '-';
                $payment = $this->date($dop);
                $amountF = (float) str_replace(',', '', (string) $amount);

                if ($dry) {
                    $this->date($dod);
                    $this->date($encAt, true);
                    $this->date($modAt, true);
                    if (!is_numeric(str_replace(',', '', (string) $amount)) && !blank($amount)) {
                        throw new \Exception("bad amount: $amount");
                    }
                    $ok++;
                    continue;
                }

                DB::transaction(function () use (
                    $surname, $given, $dod, $orNo, $payor, $payment, $rental, $years, $amountF,
                    $status, $type, $loc, $encBy, $encAt, $remarks, $modBy, $modAt, $fallbackUser
                ) {
                    $userId  = $this->userId($encBy) ?? $fallbackUser;
                    $created = $this->date($encAt, true) ?? now();

                    $rec = new Deseased([
                        'surname'         => $surname,
                        'givenname'       => $given,
                        'name_of_payor'   => trim((string) $payor),
                        'date_of_payment' => $payment ?? $created->toDateString(),
                        'or_number'       => $orNo,
                        'rental_id'       => $this->lookup(Rental::class, 'rental_type', $rental ?: self::DEFAULT_RENTAL),
                        'status_id'       => $this->lookup(Status::class, 'status', $status ?: self::DEFAULT_STATUS),
                        'location_id'     => $this->lookup(Location::class, 'location', $loc ?: self::DEFAULT_LOCATION),
                        'date_of_death'   => $this->date($dod) ?? '2015-10-01',
                        'year_covered'    => $years,
                        'amount_due'      => $amountF,
                        'user_id'         => $userId,
                    ]);
                    $rec->created_at = $created;
                    $rec->updated_at = $created;
                    $rec->save();

                    $type    = $type ? strtoupper(trim($type)) : 'NEW RECORD';
                    $remarks = $remarks ? trim($remarks) : null;

                    if (blank($modBy)) {
                        // never edited: the exported type/remarks are the original remark
                        $this->addRemark($rec->id, $userId, $type, $remarks, $created);
                    } else {
                        // edited: original remark first, then the exported (latest) remark by the modifier
                        $this->addRemark($rec->id, $userId, 'NEW RECORD', null, $created);

                        $modId   = $this->userId($modBy) ?? $userId;
                        $modDate = $this->date($modAt, true) ?? $created;
                        $this->addRemark($rec->id, $modId, $type, $remarks, $modDate);
                    }
                });

                $ok++;
            } catch (\Throwable $e) {
                $fail++;
                $this->newLine();
                $this->warn("Row $line skipped: " . $e->getMessage());
            }
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Imported: $ok | Failed: $fail" . ($dry ? ' (dry run, nothing saved)' : ''));
    }

    // Creates any encoder / modifier that isn't in the users table BEFORE importing records
    private function seedUsers(array $rows): void
    {
        $dry = $this->option('dry-run');

        $names = [];
        foreach ($rows as $r) {
            if (!array_filter($r)) continue;
            $names[] = preg_replace('/\s+/', ' ', trim((string) ($r[12] ?? '')));  // Encoded By
            $names[] = preg_replace('/\s+/', ' ', trim((string) ($r[15] ?? '')));  // Modified By
        }
        $names = array_unique(array_filter($names));

        $new = 0;
        foreach ($names as $name) {
            if (User::where('name', $name)->exists()) continue;

            $new++;
            if ($dry) continue;

            $base  = Str::slug($name, '.') ?: 'user';
            $email = "$base@gmail.com";
            for ($n = 2; User::where('email', $email)->exists(); $n++) {
                $email = "$base$n@gmail.com";
            }

            User::forceCreate([
                'name'           => $name,
                'email'          => $email,
                'account_type'   => 'Employee',
                'account_status' => 'active',
                'password'       => Hash::make('password'),
            ]);
        }

        $this->info(($dry ? 'Would create' : 'Created') . ": $new users");
    }

    private function addRemark(int $recId, int $userId, string $type, ?string $text, Carbon $at): void
    {
        $rem = Remarks::create([
            'desease_id' => $recId,
            'user_id'    => $userId,
            'remarks'    => $text,
            'type'       => $type,
        ]);
        $rem->forceFill(['created_at' => $at, 'updated_at' => $at])->save();

        $log = Logs::create([
            'desease_id' => $recId,
            'user_id'    => $userId,
            'remarks_id' => $rem->id,
        ]);
        $log->forceFill(['created_at' => $at, 'updated_at' => $at])->save();
    }

    private function date($v, bool $withTime = false): Carbon|string|null
    {
        if ($v === null || trim((string) $v) === '' || $v === '-') return null;

        $d = is_numeric($v)
            ? Carbon::instance(XlDate::excelToDateTimeObject($v))
            : Carbon::parse(trim($v));   // 2025-10-24, October 24, 2025 10:29 AM

        return $withTime ? $d : $d->toDateString();
    }

    private function lookup(string $model, string $col, $val): ?int
    {
        $val = strtoupper(trim((string) $val));
        if ($val === '' || $val === '-') return null;
        return $this->cache[$model][$val] ??= $model::firstOrCreate([$col => $val])->id;
    }

    private function userId($name): ?int
    {
        $name = preg_replace('/\s+/', ' ', trim((string) $name));
        if ($name === '') return null;
        return $this->cache['u'][$name] ??= User::where('name', $name)->value('id');
    }
}
// export => filename ex.Deseased_Records_All

// php artisan import:deceased-export storage/app/export.xlsx --dry-run
// php artisan import:deceased-export storage/app/export.xlsx  => real run
// Before importing into a database that has data:

// Back it up: mysqldump -u root -p your_database > backup.sql
// Run --dry-run first. It checks the dates and amounts but doesn't check for duplicates.
// Compare the record count before and after. The after count should be the before count plus the "Imported" number.
