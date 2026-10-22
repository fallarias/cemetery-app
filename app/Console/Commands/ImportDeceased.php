<?php

namespace App\Console\Commands;

use App\Models\{Deseased, Rental, Status, Location, Remarks, Logs, User};
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as XlDate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ImportDeceased extends Command
{
    protected $signature = 'import:deceased {file} {--dry-run} {--export}';
    protected $description = 'Import deceased records from Excel';

    private const DEFAULT_LOCATION      = 'OLD CEMETERY';
    private const DEFAULT_DATE_OF_DEATH = '2015-10-01';   // 01/10/2015 (day/month/year)
    private const DEFAULT_NAME      = 'UNKNOWN';
    private const DEFAULT_OR_NUMBER = '0';
    private const DEFAULT_YEARS     = '-';
    private const DEFAULT_RENTAL          = 'UNKNOWN';
    private const DEFAULT_STATUS          = 'UNKNOWN';
    private const DEFAULT_DATE_OF_PAYMENT = '2015-10-01';

    private array $cache = [];

    public function handle()
    {
        $path = base_path($this->argument('file'));
        if (!is_file($path)) return $this->error("File not found: $path");

        $sheet = IOFactory::load($path)->getActiveSheet();
        $rows  = $sheet->toArray(null, true, false, false); // raw values
        array_shift($rows); // remove header row
        $rows = array_reverse($rows, true);
        $this->seedFirst($rows);

        $fallbackUser = User::where('account_type', 'Admin')->value('id') ?? User::value('id');
        $ok = $skip = $fail = $defaulted = 0;

        $bar = $this->output->createProgressBar(count($rows));

        foreach ($rows as $i => $r) {
            $bar->advance();
            $line = $i + 2;

            if (!array_filter($r)) { continue; }              // blank row

            try {
                [$dop, $deceased, $payor, $amount, $rental, $status,
                 $or, $years, $remarks, $encBy, $encAt, $modBy, $modAt] = array_pad($r, 13, null);

                [$surname, $given] = $this->splitName($deceased);

                $usedDefault = false;

                if (!$surname) {
                    $surname = self::DEFAULT_NAME;
                    $given   = self::DEFAULT_NAME;
                    $usedDefault = true;
                }
                if (blank($or)) {
                    $or = self::DEFAULT_OR_NUMBER;
                    $usedDefault = true;
                }
                if (blank($years)) {
                    $years = self::DEFAULT_YEARS;
                    $usedDefault = true;
                }

                if (blank($given)) {
                    $given = self::DEFAULT_NAME;
                    $usedDefault = true;
                }
                if (blank($rental) || trim($rental) === '-') {
                    $rental = self::DEFAULT_RENTAL;
                    $usedDefault = true;
                }
                if (blank($status) || trim($status) === '-') {
                    $status = self::DEFAULT_STATUS;
                    $usedDefault = true;
                }

                if ($usedDefault) {
                    $defaulted++;
                    $this->newLine();
                    $this->line("Row $line: used default value(s)");
                }

                if ($this->option('dry-run')) {
                    // run the same conversions as the real import, so bad values show up as warnings
                    $this->date($dop);
                    $this->date($encAt, true);
                    if (!is_numeric(str_replace(',', '', (string) $amount)) && !blank($amount)) {
                        throw new \Exception("bad amount: $amount");
                    }
                    $ok++;
                    continue;
                }

                DB::transaction(function () use (
                    $dop, $payor, $amount, $rental, $status, $or, $years,
                    $remarks, $encBy, $encAt, $surname, $given, $fallbackUser
                ) {
                    $userId  = $this->userId($encBy) ?? $fallbackUser;
                    $created = $this->date($encAt, true) ?? now();

                    $rec = new Deseased([
                        'surname'         => $surname,
                        'givenname'       => $given,
                        'name_of_payor'   => trim((string) $payor),
                        'date_of_payment' => $this->date($dop) ?? self::DEFAULT_DATE_OF_PAYMENT,
                        'or_number'       => trim((string) $or),
                        'rental_id'       => $this->lookup(Rental::class, 'rental_type', $rental),
                        'status_id'       => $this->lookup(Status::class, 'status', $status),
                        'location_id'     => $this->lookup(Location::class, 'location', self::DEFAULT_LOCATION),
                        'date_of_death'   => self::DEFAULT_DATE_OF_DEATH,
                        'year_covered'    => trim((string) $years),
                        'amount_due'      => (float) str_replace(',', '', (string) $amount),
                        'user_id'         => $userId,
                    ]);
                    $rec->created_at = $created;
                    $rec->updated_at = $created;
                    $rec->save();

                    $rem = Remarks::create([
                        'desease_id' => $rec->id,
                        'user_id'    => $userId,
                        'remarks'    => $remarks ? trim($remarks) : null,
                        'type'       => 'NEW RECORD',
                    ]);

                    Logs::create([
                        'desease_id' => $rec->id,
                        'user_id'    => $userId,
                        'remarks_id' => $rem->id,
                    ]);
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
        $this->info("Imported: $ok (with defaults: $defaulted) | Duplicates skipped: $skip | Failed: $fail" . ($this->option('dry-run') ? ' (dry run, nothing saved)' : ''));
    }

    // "GUNIO, JULIANITA REVELLAME" -> [GUNIO, JULIANITA REVELLAME]
    private function splitName($v): array
    {
        $v = trim(preg_replace('/\s+/', ' ', (string) $v));
        if (str_contains($v, ',')) {
            [$s, $g] = array_map('trim', explode(',', $v, 2));
            return [$s ?: null, $g ?: null];
        }
        return [$v ?: null, null];
    }

    private function date($v, bool $withTime = false): ?string
    {
        if ($v === null || $v === '' || $v === '-') return null;
        if (is_numeric($v)) {
            $d = Carbon::instance(XlDate::excelToDateTimeObject($v));
        } else {
            $d = Carbon::createFromFormat(
                str_contains($v, ':') ? 'd/m/Y H:i' : 'd/m/Y', trim($v)
            );
        }
        return $withTime ? $d->toDateTimeString() : $d->toDateString();
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

    // Creates rentals, statuses, the default location and encoder users BEFORE any records
    private function seedFirst(array $rows): void
    {
        $dry = $this->option('dry-run');

        $rentals = $statuses = $users = [];
        foreach ($rows as $r) {
            if (!array_filter($r)) continue;
            $rv = strtoupper(trim((string) ($r[4] ?? '')));
            $sv = strtoupper(trim((string) ($r[5] ?? '')));
            $rentals[]  = ($rv === '' || $rv === '-') ? self::DEFAULT_RENTAL : $rv;
            $statuses[] = ($sv === '' || $sv === '-') ? self::DEFAULT_STATUS : $sv;
            $users[]    = preg_replace('/\s+/', ' ', trim((string) ($r[9] ?? '')));
        }
        $rentals  = array_unique(array_filter($rentals,  fn ($v) => $v !== '' && $v !== '-'));
        $statuses = array_unique(array_filter($statuses, fn ($v) => $v !== '' && $v !== '-'));
        $users    = array_unique(array_filter($users));

        $new = ['rentals' => 0, 'statuses' => 0, 'locations' => 0, 'users' => 0];

        foreach ($rentals as $v) {
            if (!Rental::where('rental_type', $v)->exists()) {
                $new['rentals']++;
                if (!$dry) $this->lookup(Rental::class, 'rental_type', $v);
            }
        }
        foreach ($statuses as $v) {
            if (!Status::where('status', $v)->exists()) {
                $new['statuses']++;
                if (!$dry) $this->lookup(Status::class, 'status', $v);
            }
        }
        if (!Location::where('location', self::DEFAULT_LOCATION)->exists()) {
            $new['locations']++;
            if (!$dry) $this->lookup(Location::class, 'location', self::DEFAULT_LOCATION);
        }
        foreach ($users as $name) {
            if (!User::where('name', $name)->exists()) {
                $new['users']++;
                if ($dry) continue;

                $base  = Str::slug($name, '.') ?: 'user';
                $email = "$base@cemetery.local";
                for ($n = 2; User::where('email', $email)->exists(); $n++) {
                    $email = "$base$n@cemetery.local";
                }

                User::forceCreate([
                    'name'           => $name,
                    'email'          => $email,
                    'account_type'   => 'Employee',
                    'account_status' => 'active',
                    'password'       => Hash::make('password'),
                ]);
            }
        }

        $this->info(
            ($dry ? 'Would create' : 'Created first') .
            ": {$new['rentals']} rental types, {$new['statuses']} statuses, " .
            "{$new['locations']} location, {$new['users']} users"
        );
    }
}
