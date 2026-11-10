<?php

namespace App\Http\Controllers;

use App\Models\Location;
use App\Models\RecordLogs;
use App\Models\Rental;
use App\Models\Status;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class AdminPageController extends Controller
{
    public function user_page(Request $request) {

        $user = Auth::user();

        if($user->account_type != 'Admin') {
            return redirect('/employee/page');
        }
        $perPage = $request->input('per_page', 10);
        $perPage = ($perPage === 'all' || (int) $perPage <= 0) ? 1000 : (int) $perPage;

        $paginated = User::where('account_type', 'Employee')->latest()->paginate($perPage)->withQueryString();

        $records = $paginated->setCollection(
            $paginated->getCollection()->map(fn ($item) => [
                'id'              => $item->id,
                'name'         => $item->name,
                'email'          => $item->email,
                'account_type'      => $item->account_type,
                'account_status'          => $item->account_status,
            ])
        );

        $location = Location::select('id','location')->get();
        $status = Status::select('id','status')->get();
        $rental = Rental::select('id','rental_type')->get();

        return Inertia::render('Admin/ApprovalPage', [
            'status' => $status,
            'rental' => $rental,
            'location' => $location,
            'records' => $records,
        ]);
    }

    public function user_logs(Request $request)
    {
        $search  = trim((string) $request->input('search', ''));
        $perPage = (int) $request->input('per_page', 25);

        $logs = RecordLogs::query()
            ->join('users', 'users.id', '=', 'record_logs.user_id')
            ->join('deseased_info', 'deseased_info.id', '=', 'record_logs.desease_id')
            ->select(
                'record_logs.id',
                'record_logs.message',
                'record_logs.type',
                'record_logs.created_at',
                'users.name as user_name',
                DB::raw("CONCAT(deseased_info.surname, ', ', deseased_info.givenname) as deseased_name")
            )
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($q) use ($search) {
                    $q->where('record_logs.message', 'like', "%{$search}%")
                      ->orWhere('record_logs.type', 'like', "%{$search}%")
                      ->orWhere('users.name', 'like', "%{$search}%")
                      ->orWhere('deseased_info.surname', 'like', "%{$search}%")
                      ->orWhere('deseased_info.givenname', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('record_logs.created_at')
            ->orderByDesc('record_logs.id')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn ($log) => [
                'id'            => $log->id,
                'type'          => $log->type,
                'message'       => $log->message,
                'deseased_name' => $log->deseased_name,
                'user_name'     => $log->user_name,
                'created_at'    => $log->created_at->format('M d, Y h:i A'),
            ]);

        return Inertia::render('Admin/UserLogs', [
            'logs'    => $logs,
            'filters' => ['search' => $search],
        ]);
    }
}
