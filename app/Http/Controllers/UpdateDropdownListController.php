<?php

namespace App\Http\Controllers;

use App\Models\Location;
use App\Models\Rental;
use App\Models\Status;
use Illuminate\Http\Request;

class UpdateDropdownListController extends Controller
{
    public function update_status(Request $request){

        $validated = $request->validate([
            'id' => ['required', 'exists:status,id'],
            'newStatus' => ['required', 'string'],
        ]);


        $update = Status::findOrFail($validated['id']);
        $update->update([
            'status' => $validated['newStatus']
        ]);

        return back()->with('success', 'Status updated successfully');
    }

    public function update_rental_type(Request $request){

        $validated = $request->validate([
            'id' => ['required', 'exists:rentals,id'],
            'newRentalType' => ['required', 'string'],
        ]);


        $update = Rental::findOrFail($validated['id']);
        $update->update([
            'rental_type' => $validated['newRentalType']
        ]);

        return back()->with('success', 'Rental Type updated successfully');
    }

    public function update_location(Request $request){

        $validated = $request->validate([
            'id' => ['required', 'exists:locations,id'],
            'newLocation' => ['required', 'string'],
        ]);


        $update = Location::findOrFail($validated['id']);
        $update->update([
            'location' => $validated['newLocation']
        ]);

        return back()->with('success', 'Location updated successfully');
    }
}
