<?php

namespace App\Http\Controllers;

use App\Models\Location;
use App\Models\Rental;
use App\Models\Status;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class AdminPageController extends Controller
{
    public function user_page(Request $request){

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
}
