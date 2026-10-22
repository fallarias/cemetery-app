<?php

namespace Database\Seeders;

use App\Models\Deseased;
use App\Models\Logs;
use App\Models\Location;
use App\Models\Rental;
use App\Models\Remarks;
use App\Models\Status;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DeseasedSeeder extends Seeder
{
    public function run(): void
    {
        $userIds = User::pluck('id')->values();

        if ($userIds->isEmpty()) {
            $this->command->error('No users found. Create a user first, then run this seeder again.');
            return;
        }

        // Make sure lookup values exist.
        $rentals = [
            'Apartment Type',
            'Peripheral Niche',
            'Additional Layer',
            'Burial of Indigents',
        ];

        $statuses = [
            'New',
            'Renewal',
            'Cancelled',
            'Renewal And Cancelled',
        ];

        $locations = [
            'Old Cemetery',
            'New Cemetery',
        ];

        foreach ($rentals as $rental) {
            Rental::firstOrCreate([
                'rental_type' => $rental,
            ]);
        }

        foreach ($statuses as $status) {
            Status::firstOrCreate([
                'status' => $status,
            ]);
        }

        foreach ($locations as $location) {
            Location::firstOrCreate([
                'location' => $location,
            ]);
        }

        // surname, givenname, middlename, suffix, name of payor,
        // rental type, status, location, date of death, year covered, amount due
        $rows = [
            ['Dela Cruz',  'Juan',       'Santos',    null,  'Maria Dela Cruz',   'Apartment',  'New',               'Old Cemetery', '2026-09-14', '2026',          8000],
            ['Reyes',      'Maria',      'Garcia',    null,  'Pedro Reyes',       'Peripheral', 'Renewal',           'New Cemetery', '2024-03-02', '2024 - 2026',   9000],
            ['Santos',     'Jose',       'Ramos',     'Jr.', 'Elena Santos',      'Add 1 Layer','New',               null,            '2025-11-20', '2025',          6500],
            ['Mendoza',    'Rosario',    'Bautista',  null,  'Carlo Mendoza',     'Apartment',  'Renewal',           'Old Cemetery', '2022-05-18', '2022 - 2024',   7500],
            ['Villanueva', 'Antonio',    'Cruz',      'Sr.', 'Luisa Villanueva',  'Peripheral', 'New',               'New Cemetery', '2026-01-09', '2026',          8000],
            ['Aquino',     'Teresita',   'Flores',    null,  'Ramon Aquino',      'Apartment',  'Cancelled',         null,            '2020-08-30', '2020 - 2023',   5000],
            ['Castillo',   'Ricardo',    'Navarro',   null,  'Nena Castillo',     'Add 1 Layer','Renewal',           'Old Cemetery', '2023-12-12', '2023 - 2025',   9500],
            ['Torres',     'Lourdes',    'Domingo',   null,  'Mario Torres',      'Apartment',  'New',               'New Cemetery', '2026-06-25', '2026 - 2027',   8500],
            ['Gonzales',   'Eduardo',    'Pascual',   'III', 'Cora Gonzales',     'Peripheral', 'Renewal',           'Old Cemetery', '2021-02-14', '2021 - 2025',  12000],
            ['Ramos',      'Estrella',   'Lopez',     null,  'Danilo Ramos',      'Apartment',  'Renewal/Cancelled', null,           '2019-10-05', '2019 - 2022',   6000],
            ['Bautista',   'Fernando',   'Aguilar',   null,  'Josefina Bautista', 'Add 1 Layer','New',               'New Cemetery', '2025-04-03', '2025',          7000],
            ['Flores',     'Remedios',   'Manalo',    null,  'Alfredo Flores',    'Peripheral', 'Cancelled',         'Old Cemetery', '2018-07-21', '2018 - 2021',   5500],
            ['Navarro',    'Gregorio',   'Salazar',   'Sr.', 'Imelda Navarro',    'Apartment',  'Renewal',           'New Cemetery', '2022-09-09', '2022 - 2026',  11000],
            ['Pascual',    'Concepcion', 'Dizon',     null,  'Rodel Pascual',     'Apartment',  'New',               null,            '2026-08-01', '2026',          8000],
            ['Lopez',      'Benjamin',   'Mercado',   'Jr.', 'Carmela Lopez',     'Peripheral', 'Renewal',           'Old Cemetery', '2023-01-27', '2023 - 2024',   7200],
            ['Domingo',    'Natividad',  'Soriano',   null,  'Felipe Domingo',    'Add 1 Layer','New',               'New Cemetery', '2026-03-15', '2026 - 2028',  10000],
            ['Salazar',    'Rodrigo',    'Velasco',   null,  'Ana Salazar',       'Apartment',  'Renewal',           'Old Cemetery', '2021-11-11', '2021 - 2023',   6800],
            ['Manalo',     'Felicidad',  'Ocampo',    null,  'Jun Manalo',        'Peripheral', 'New',               'New Cemetery', '2025-09-30', '2025 - 2026',   9000],
            ['Ocampo',     'Narciso',    'Tolentino', 'III', 'Liza Ocampo',       'Apartment',  'Cancelled',         null,            '2017-06-06', '2017 - 2019',   4500],
            ['Soriano',    'Corazon',    'Padilla',   null,  'Ben Soriano',       'Add 1 Layer','Renewal',           'Old Cemetery', '2024-10-19', '2024',          8800],
        ];

        // Extra EDIT rows for some records:
        // row index => [[days after creation, remarks or null], ...]
        $edits = [
            1  => [[3, 'Updated payor contact.']],
            3  => [[5, null]],
            8  => [[4, 'Corrected spelling of name.'], [9, null]],
            12 => [[6, 'Payment verified.']],
        ];

        Model::unguarded(function () use ($rows, $edits, $userIds) {
            DB::transaction(function () use ($rows, $edits, $userIds) {
                foreach ($rows as $i => $r) {
                    [
                        $surname,
                        $given,
                        $middle,
                        $suffix,
                        $payor,
                        $rental,
                        $status,
                        $location,
                        $death,
                        $year,
                        $amount
                    ] = $r;

                    $orNumber = sprintf('%06d', 80300 + $i);

                    if (Deseased::withTrashed()->where('or_number', $orNumber)->exists()) {
                        continue;
                    }

                    $owner = $userIds[$i % $userIds->count()];
                    $created = now()
                        ->subDays(40 - $i * 2)
                        ->setTime(9 + $i % 8, ($i * 7) % 60);

                    $startYr = (int) substr($year, 0, 4);

                    // Get lookup IDs.
                    $rentalId = Rental::where('rental_type', $rental)->value('id');
                    $statusId = Status::where('status', $status)->value('id');
                    $locationId = $location
                        ? Location::where('location', $location)->value('id')
                        : null;

                    $record = Deseased::create([
                        'surname'         => $surname,
                        'givenname'       => $given,
                        'middlename'      => $middle,
                        'suffix'          => $suffix,
                        'name_of_payor'   => $payor,
                        'date_of_payment' => sprintf(
                            '%d-%02d-%02d',
                            $startYr,
                            ($i % 9) + 1,
                            5 + $i
                        ),
                        'or_number'       => $orNumber,
                        'rental_id'       => $rentalId,
                        'status_id'       => $statusId,
                        'location_id'     => $locationId,
                        'date_of_death'   => $death,
                        'year_covered'    => $year,
                        'amount_due'      => $amount,
                        'user_id'         => $owner,
                        'created_at'      => $created,
                        'updated_at'      => $created,
                    ]);

                    $remark = Remarks::create([
                        'desease_id' => $record->id,
                        'user_id'    => $owner,
                        'remarks'    => $i % 4 === 0
                            ? 'Initial payment received.'
                            : null,
                        'type'       => 'NEW RECORD',
                        'created_at' => $created,
                        'updated_at' => $created,
                    ]);

                    Logs::create([
                        'desease_id' => $record->id,
                        'user_id'    => $owner,
                        'remarks_id' => $remark->id,
                        'created_at' => $created,
                        'updated_at' => $created,
                    ]);

                    foreach ($edits[$i] ?? [] as $n => [$days, $text]) {
                        $editor = $userIds[
                            ($i + $n + 1) % $userIds->count()
                        ];

                        $when = $created->copy()->addDays($days);

                        $edit = Remarks::create([
                            'desease_id' => $record->id,
                            'user_id'    => $editor,
                            'remarks'    => $text,
                            'type'       => 'EDIT',
                            'created_at' => $when,
                            'updated_at' => $when,
                        ]);

                        Logs::create([
                            'desease_id' => $record->id,
                            'user_id'    => $editor,
                            'remarks_id' => $edit->id,
                            'created_at' => $when,
                            'updated_at' => $when,
                        ]);
                    }
                }
            });
        });

        $this->command->info('Seeded deceased records.');
    }
}
