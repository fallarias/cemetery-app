<?php

namespace App\Http\Controllers;

use App\Models\Deseased;
use App\Models\Location;
use App\Models\Logs;
use App\Models\Remarks;
use App\Models\Rental;
use App\Models\Status;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;
use Spatie\SimpleExcel\SimpleExcelWriter;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Common\Entity\Style\CellAlignment;
use OpenSpout\Common\Entity\Style\Border;
use OpenSpout\Common\Entity\Style\BorderPart;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\CellVerticalAlignment;


class EmployeePageController extends Controller
{
    public function display_employee(Request $request)
    {
        $search  = trim($request->input('search', ''));
        $perPage = $request->input('per_page', 10);
        $perPage = ($perPage === 'all' || (int) $perPage <= 0) ? 500 : (int) $perPage;

        $paginated = Deseased::with(['remarks', 'user', 'rental', 'status', 'location'])
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($q) use ($search) {
                    $like = "%{$search}%";
                    $q->where('surname', 'like', $like)
                    ->orWhere('givenname', 'like', $like)
                    ->orWhere('middlename', 'like', $like)
                    ->orWhere('or_number', 'like', $like)
                    ->orWhere('name_of_payor', 'like', $like)
                    ->orWhereHas('status', fn ($s) => $s->where('status', 'like', $like))
                    ->orWhereHas('user', fn ($s) => $s->where('name', 'like', $like))
                    ->orWhereRaw("CONCAT(surname, ' ', givenname) LIKE ?", [$like])
                    ->orWhereRaw("CONCAT(givenname, ' ', surname) LIKE ?", [$like]);
                });
            })
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        $up = fn ($v) => $v === null ? null : mb_strtoupper(trim($v));

        $records = $paginated->setCollection(
        $paginated->getCollection()->map(fn ($item) => [
            'id'              => $item->id,
            'surname'         => $up($item->surname),
            'givenname'       => $up($item->givenname),
            'middlename'      => $up($item->middlename),
            'suffix'          => $up($item->suffix),
            'deseased_name'   => $up(trim("{$item->surname}, {$item->givenname} {$item->middlename} {$item->suffix}")),
            'date_of_death'   => $item->date_of_death,
            'or_number'       => $item->or_number,
            'name_of_payor'   => $up($item->name_of_payor),
            'date_of_payment' => $item->date_of_payment,
            'status'          => $up($item->status->status),
            'status_id'       => $item->status_id,
            'rental_type'     => $up($item->rental->rental_type),
            'rental_id'       => $item->rental_id,
            'location'        => $up($item->location?->location),
            'location_id'     => $item->location_id,
            'year_covered'    => $item->year_covered,
            'amount_due'      => $item->amount_due,
            'encoded_by'      => $up($item->user?->name),
            'remarks'         => $up($item->remarks->last()?->remarks),
        ])
    );

        $location = Location::select('id','location')->get();
        $status = Status::select('id','status')->get();
        $rental = Rental::select('id','rental_type')->get();

        return Inertia::render('Employee/EmployeeHomePage', [
            'records' => $records,
            'rental' => $rental,
            'location' => $location,
            'status' => $status,
            'filters' => ['search' => $search],
        ]);
    }

    public function store_new_record(Request $request){

        $request->merge([
            'amount_due' => str_replace(',', '', (string) $request->input('amount_due')),
        ]);

        $validated = $request->validate([
            'surname'         => ['required', 'string'],
            'givenname'       => ['required', 'string'],
            'middlename'      => ['nullable', 'string'],
            'suffix'          => ['nullable', 'string'],
            'name_of_payor'   => ['required', 'string'],
            'date_of_payment' => ['required', 'date'],
            'or_number'       => ['required', 'string', 'regex:/^[0-9]+$/'],
            'rental_type'     => ['nullable', 'required_without:new_rental_type'],
            'new_rental_type' => ['nullable', 'string', 'max:255'],
            'status'          => ['nullable', 'required_without:new_status'],
            'new_status'      => ['nullable', 'string', 'max:255'],
            'location'        => ['nullable'],
            'new_location'    => ['nullable', 'string', 'max:255'],
            'date_of_death'   => ['nullable', 'date', 'before_or_equal:today'],
            'year_covered'    => $this->yearCoveredRules(),
            'amount_due'      => ['required', 'numeric'],
            'remarks'         => ['nullable', 'string'],
        ]);

        $user = Auth::id();

        $rentalId   = $this->resolveLookup(Rental::class,   'rental_type', 'rental_type', $validated);
        $statusId   = $this->resolveLookup(Status::class,   'status',      'status',      $validated);
        $locationId = $this->resolveLookup(Location::class, 'location',    'location',    $validated);

        $new_record = Deseased::create([
            'surname'           => $validated['surname'],
            'givenname'         => $validated['givenname'],
            'middlename'        => $validated['middlename'],
            'suffix'            => $validated['suffix'],
            'name_of_payor'     => $validated['name_of_payor'],
            'date_of_payment'   => $validated['date_of_payment'],
            'or_number'         => $validated['or_number'],
            'rental_id'         => $rentalId,
            'status_id'         => $statusId,
            'location_id'       => $locationId,
            'date_of_death'     => $validated['date_of_death']  ?? now()->toDateString(),
            'year_covered'      => $validated['year_covered'],
            'amount_due'        => $validated['amount_due'],
            'user_id'           => $user,
        ]);

        $remarks = Remarks::create([
            'desease_id'    => $new_record->id,
            'user_id'       => $user,
            'remarks'       => $validated['remarks'],
            'type'          => 'NEW RECORD',
        ]);

        Logs::create([
            'desease_id'    => $new_record->id,
            'user_id'       => $user,
            'remarks_id'    => $remarks->id,
        ]);

        return redirect()->back()->with('success', 'New Record has been added.');

    }

    public function update_record(Request $request, int $id){

        $deseased = Deseased::findOrFail($id);

        $request->merge([
            'amount_due' => str_replace(',', '', (string) $request->input('amount_due')),
        ]);

        $validated = $request->validate([
            'surname'         => ['required', 'string'],
            'givenname'       => ['required', 'string'],
            'middlename'      => ['nullable', 'string'],
            'suffix'          => ['nullable', 'string'],
            'name_of_payor'   => ['required', 'string'],
            'date_of_payment' => ['required', 'date'],
            'or_number'       => ['required', 'string', 'regex:/^[0-9]+$/'],
            'rental_id'     => ['required', 'integer', 'exists:rentals,id'],
            'status_id'          => ['required', 'integer', 'exists:status,id'],
            'location_id'        => ['nullable', 'integer', 'exists:locations,id'],
            'date_of_death'   => ['required', 'date', 'before_or_equal:today'],
            'year_covered'    => $this->yearCoveredRules(),
            'amount_due'      => ['required', 'numeric'],
            'remarks'         => ['nullable', 'string'],
        ]);

        $user = Auth::id();

        DB::transaction(function () use ($validated, $deseased, $user) {
            $deseased->update(Arr::except($validated, ['remarks']));

            $remarks = Remarks::create([
                'desease_id' => $deseased->id,
                'user_id'    => $user,
                'remarks'    => $validated['remarks'] ?? null,
                'type'       => 'EDIT',
            ]);

            Logs::create([
                'desease_id' => $deseased->id,
                'user_id'    => $user,
                'remarks_id' => $remarks->id,
            ]);
        });

        return redirect()->back()->with('success', 'Record has been updated.');
    }

    public function destroy(int $id)
    {
        $deseased = Deseased::findOrFail($id);
        $deseased->delete();
        return redirect()->back()->with('success', 'Record deleted.');
    }

    public function export(Request $request)
    {
        set_time_limit(0);
        ini_set('memory_limit', '512M');

        $all  = $request->boolean('all');
        $from = $request->input('from');
        $to   = $request->input('to') ?: $from;

        $scopes = [
            'year_covered'    => 'coveringYears',
            'date_of_death'   => 'coveringDeath',
            'date_of_payment' => 'coveringPayment',
        ];

        $scope = $scopes[$request->input('export_year')] ?? 'coveringYears';

        $exportLabel = '';

        if ($scope == 'coveringYears'){
            $exportLabel = 'YearCovered';
        } else if ($scope == 'coveringDeath'){
            $exportLabel = 'DateOfDeath';
        } else {
            $exportLabel = 'DateOfPayment';
        }

        abort_if(!$all && !$from, 422, 'Select All or a year to export.');

        // ── File name label ──────────────────────────────────────────────
        if ($all || !$from) {
            $label = 'All';
        } elseif ((int) $from === (int) $to) {
            $label = (int) $from . '_' . $exportLabel;         // Deseased_Records_2022_YearCovered
        } else {
            $label = (int) $from . '_to_' . (int) $to. '_' . $exportLabel;  // Deseased_Records_2022_to_2023_YearCovered
        }
        $baseName = "Deseased_Records_{$label}";

        // ── Styles (same as the old ExcelLayout.js) ──────────────────────
        $border = fn (string $color) => new Border(
            new BorderPart(Border::TOP,    $color, Border::WIDTH_THIN, Border::STYLE_SOLID),
            new BorderPart(Border::BOTTOM, $color, Border::WIDTH_THIN, Border::STYLE_SOLID),
            new BorderPart(Border::LEFT,   $color, Border::WIDTH_THIN, Border::STYLE_SOLID),
            new BorderPart(Border::RIGHT,  $color, Border::WIDTH_THIN, Border::STYLE_SOLID),
        );

        $headerStyle = (new Style())
            ->setFontName('Times New Roman')
            ->setFontSize(12)
            ->setFontBold()
            ->setFontColor(Color::WHITE)
            ->setBackgroundColor('008000')
            ->setCellAlignment(CellAlignment::CENTER)
            ->setCellVerticalAlignment(CellVerticalAlignment::CENTER)
            ->setShouldWrapText(true)
            ->setBorder($border('006400'));

        $rowStyle = (new Style())
            ->setFontName('Times New Roman')
            ->setFontSize(12)
            ->setCellAlignment(CellAlignment::CENTER)
            ->setCellVerticalAlignment(CellVerticalAlignment::CENTER)
            ->setShouldWrapText(true)
            ->setBorder($border('D1D5DB'));

        // Column widths: 1 = #, 2 = Deceased Name, ... 12 = Remarks
        $widths = [1 => 6, 2 => 34, 3 => 16, 4 => 16, 5 => 28, 6 => 28, 7 => 16,  8 => 14, 9 => 16, 10 => 14, 11 => 20, 12 => 24, 13 => 20, 14 => 34, 15 => 38, 16 => 28, 17 => 24];

        $query = Deseased::with([
            'latestRemark',
            'latestEdit.user:id,name',
            'user:id,name',
            'rental:id,rental_type',
            'location:id,location',
            'status:id,status',
            ])->when(!$all && $from, fn ($q) => $q->{$scope}((int) $from, (int) $to));

        $dir         = storage_path('app/exports/' . uniqid());
        $rowsPerFile = 100000;
        @mkdir($dir, 0777, true);
        register_shutdown_function(fn () => @rmdir($dir));

        $part = 1; $count = 0; $no = 0; $writer = null; $files = [];

        $up = fn ($v) => is_string($v) ? mb_strtoupper($v, 'UTF-8') : $v;

        foreach ($query->lazyByIdDesc(2000) as $item) {
            if ($writer === null || $count >= $rowsPerFile) {
                $writer?->close();
                $path    = "{$dir}/{$baseName}_part{$part}.xlsx";
                $files[] = $path;
                $writer  = SimpleExcelWriter::create($path, '', function ($w) use ($widths) {
                    foreach ($widths as $col => $width) {
                        $w->getOptions()->setColumnWidth($width, $col);
                    }
                })->setHeaderStyle($headerStyle);
                $part++; $count = 0;
            }

            $no++;
            $count++;
            $row = [
                '#'               => $no,
                'Deceased Name'   => preg_replace('/\s+/', ' ', trim("{$item->surname}, {$item->givenname} {$item->middlename} {$item->suffix}")),
                'Date of Death'   => $item->date_of_death,
                'OR Number'       => $item->or_number,
                'Name of Payor'   => $item->name_of_payor,
                'Date of Payment' => $item->date_of_payment,
                'Rental Type'     => $item->rental?->rental_type,
                'Year Covered'    => $item->year_covered,
                'Amount Due'      => number_format((float) $item->amount_due, 2),
                'Status'          => $item->status?->status,
                'Type'            => $item->latestRemark?->type,
                'Location'        => $item->location?->location,
                'Encoded By'      => $item->user?->name,
                'Date Created'    => Carbon::parse($item->created_at)->format('F d, Y h:i A'),
                'Remarks'         => $item->latestRemark?->remarks,
                'Modified By'     => $item->latestEdit?->user?->name,
                'Date Modified'   => $item->latestEdit?->created_at?->format('F d, Y h:i A'),
            ];

            $writer->addRow(
                array_combine(
                    array_map($up, array_keys($row)),
                    array_map($up, array_values($row))
                ),
                $rowStyle
            );
        }
        $writer?->close();

        if (empty($files)) {
            return back()->with('error', 'No records found for that selection.');
        }

        // One file: download it with the clean name
        if (count($files) === 1) {
            return response()->download($files[0], "{$baseName}.xlsx")->deleteFileAfterSend(true);
        }

        // Several files: zip them
        $zipPath = "{$dir}/{$baseName}.zip";
        $zip = new \ZipArchive();
        $zip->open($zipPath, \ZipArchive::CREATE);
        foreach ($files as $file) $zip->addFile($file, basename($file));
        $zip->close();
        foreach ($files as $file) @unlink($file);

        return response()->download($zipPath, "{$baseName}.zip")->deleteFileAfterSend(true);
    }

    public function exportCheck(Request $request)
    {
        $all  = $request->boolean('all');
        $from = $request->input('from');
        $to   = $request->input('to') ?: $from;

        $scopes = [
        'year_covered'    => 'coveringYears',
        'date_of_death'   => 'coveringDeath',
        'date_of_payment' => 'coveringPayment',
        ];

        $scope = $scopes[$request->input('export_year')] ?? 'coveringYears';

        $count = Deseased::query()
            ->when(!$all && $from, fn ($q) => $q->{$scope}((int) $from, (int) $to))
            ->count();

        return response()->json(['count' => $count]);
    }

    private function yearCoveredRules(): array
    {
        return [
            'required', 'string',
            'regex:/^\d{4}(\s*-\s*\d{4})?$/',
            function ($attribute, $value, $fail) {
                preg_match_all('/\d{4}/', $value, $m);
                $years = array_map('intval', $m[0]);

                if (count($years) === 2 && $years[1] < $years[0]) {
                    $fail('The end year must not be earlier than the start year.');
                }
            },
        ];
    }

    private function resolveLookup(string $model, string $column, string $field, array $validated): ?int
    {
        $new = trim((string) ($validated["new_{$field}"] ?? ''));
        $id  = $validated[$field] ?? null;

        if ($new !== '') {
            return $model::firstOrCreate([$column => $new])->id;
        }

        if (filled($id)) {
            if (!$model::whereKey($id)->exists()) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    $field => "The selected {$field} does not exist.",
                ]);
            }
            return (int) $id;
        }

        return null;
    }
}
