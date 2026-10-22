<?php

namespace App\Http\Controllers;

use App\Models\Cedula;
use App\Models\Location;
use App\Models\Rental;
use App\Models\Status;
use Illuminate\Http\Request;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Illuminate\Validation\Rule;

class CedulaController extends Controller
{
    public function cedula_display(){

        $location = Location::select('id','location')->get();
        $status = Status::select('id','status')->get();
        $rental = Rental::select('id','rental_type')->get();

        return Inertia::render('Cedula/CedulaPrinter', [
            'rental' => $rental,
            'location' => $location,
            'status' => $status,
        ]);
    }

    public function index(Request $request)
    {
        $perPage = min(max($request->integer('per_page', 10), 1), 500);

        return Cedula::query()
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%' . $request->string('q')->trim() . '%';
                $query->where(function ($w) use ($term) {
                    $w->where('serial', 'like', $term)
                    ->orWhereRaw("CONCAT_WS(' ', first_name, middle_name, surname) like ?", [$term])
                    ->orWhereRaw("CONCAT_WS(' ', surname, first_name, middle_name) like ?", [$term]);
                });
            })
            ->latest()
            ->latest('id')
            // the name columns are needed so the full_name accessor works
            ->paginate($perPage, ['id', 'serial', 'surname', 'first_name', 'middle_name', 'created_at']);
    }

    public function store(Request $request)
    {
        $moneyFields = ['basic_tax_due', 'additional_tax_due', 'item1_due', 'item2_due', 'item3_due', 'total', 'interest', 'total_paid'];

        $rules = [
            'id' => ['nullable', 'integer'],
            'data' => ['required', 'array'],
            'data.serial' => ['nullable', 'string', 'max:30'],
            'data.date_issued' => ['required', 'date'],
            'data.place_of_issue' => ['nullable', 'string', 'max:255'],
            'data.surname' => ['required', 'string', 'max:60'],
            'data.first_name' => ['nullable', 'string', 'max:60'],
            'data.middle_name' => ['nullable', 'string', 'max:60'],
            'data.tin' => ['nullable', 'string', 'max:20'],
            'data.address' => ['nullable', 'string', 'max:255'],
            'data.sex' => ['nullable', Rule::in(['M', 'F'])],
            'data.civil' => ['nullable', Rule::in(['1', '2', '3', '4'])],
            'data.citizenship' => ['nullable', 'string', 'max:255'],
            'data.icr_no' => ['nullable', 'string', 'max:30'],
            'data.place_of_birth' => ['nullable', 'string', 'max:255'],
            'data.height' => ['nullable', 'string', 'max:10'],
            'data.weight' => ['nullable', 'string', 'max:10'],
            'data.in_words' => ['nullable', 'string', 'max:255'],
            'data.treasurer' => ['nullable', 'string', 'max:255'],
            'data.date_of_birth' => ['nullable', 'date', 'before_or_equal:today'],
        ];
        foreach ($moneyFields as $f) {
            $rules["data.$f"] = ['nullable', 'numeric', 'min:0', 'max:99999999.99'];
        }

        // validated() keeps only the keys listed above, so stray keys (like the old "year") are dropped.
        // Empty strings arrive as null thanks to Laravel's ConvertEmptyStringsToNull middleware.
        $attributes = $request->validate($rules)['data'];
        $attributes['basic_tax_due'] ??= 0; // column is NOT NULL
        $serial = $attributes['serial'] ?? null;

        if ($request->filled('id')) {
            $cedula = Cedula::findOrFail($request->integer('id'));

            if ($serial && Cedula::where('serial', $serial)->where('id', '!=', $cedula->id)->exists()) {
                return response()->json(['message' => 'Another cedula already uses this CCI2023 serial number.'], 422);
            }

            $cedula->update($attributes);
        } else {
            // Same serial = same cedula: update it instead of adding a duplicate.
            $cedula = $serial ? Cedula::firstOrNew(['serial' => $serial]) : new Cedula;
            $cedula->fill($attributes);

            if (! $cedula->exists) {
                $cedula->user_id = $request->user()->id;
            }

            $cedula->save();
        }

        return response()->json($cedula, $cedula->wasRecentlyCreated ? 201 : 200);
    }

    public function print(Request $request)
    {
        $request->validate(['pdf' => ['required', 'file', 'mimes:pdf', 'max:2048']]);

        $path = $request->file('pdf')->store('tmp');
        $full = str_replace('/', '\\', Storage::path($path));

        $printer = config('services.cedula.printer');
        $cmd = [config('services.cedula.sumatra')];
        array_push($cmd, ...($printer ? ['-print-to', $printer] : ['-print-to-default']));
        array_push($cmd, '-print-settings', 'noscale,portrait', '-silent', $full);

        try {
            $result = Cache::lock('cedula-printing', 20)->block(15, fn () => Process::run($cmd));
        } catch (LockTimeoutException) {
            return response()->json(['message' => 'Printer is busy, try again.'], 503);
        } finally {
            Storage::delete($path);
        }

        Log::info('Cedula print', [
            'exit' => $result->exitCode(),
            'out' => $result->output(),
            'err' => $result->errorOutput(),
            'cmd' => $cmd,
        ]);

        if ($result->failed()) {
            return response()->json(['message' => 'Print failed: ' . $result->errorOutput()], 500);
        }

        return response()->json(['ok' => true]);
    }

    public function show(Cedula $cedula)
    {
        return $cedula;
    }

    public function destroy(Cedula $cedula)
    {
        $cedula->delete();

        return response()->noContent();
    }
}
