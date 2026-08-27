<?php

namespace Modules\FeeManagement\Http\Controllers;

use App\Http\Controllers\Concerns\Sortable;
use App\Http\Controllers\Controller;
use App\Services\FeeReminderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Modules\FeeManagement\Actions\SaveFee;
use Modules\FeeManagement\Models\Fee;

class FeeManagementController extends Controller
{
    use Sortable;

    public function __construct(
        private readonly FeeReminderService $reminderService,
    ) {}

    /**
     * Display a listing of the resource.
     */
    /**
     * Send a consolidated fee-balance reminder (email + SMS) to every
     * parent with at least one outstanding fee, scoped the same way the
     * listing itself is.
     */
    public function sendReminders()
    {
        abort_unless(Auth::user()->can('create feemanagement'), 403);

        $query = Fee::query();
        $this->scopeToViewer($query);

        $result = $this->reminderService->sendForDefaulters($query);

        if ($result['parents_notified'] === 0) {
            return redirect()->route('feemanagement.index')
                ->with('success', 'No outstanding fee balances to remind anyone about.');
        }

        $message = "Reminders sent to {$result['parents_notified']} parent(s) - {$result['emails_sent']} email(s), {$result['sms_sent']} SMS.";
        if ($result['skipped_no_contact'] > 0) {
            $message .= " {$result['skipped_no_contact']} parent(s) skipped - no email or phone on file.";
        }

        return redirect()->route('feemanagement.index')->with('success', $message);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        abort_unless(Auth::user()->can('create feemanagement'), 403);

        $validated = $request->validate(SaveFee::rules());

        try {
            SaveFee::handle($validated);

            return redirect()->route('feemanagement.index')->with('success', 'Fee created successfully!');
        } catch (\Exception $e) {
            Log::error('Fee creation failed: '.$e->getMessage());

            return redirect()->back()->withInput()
                ->with('error', 'Failed to create fee: '.$e->getMessage());
        }
    }

    /**
     * Show the specified resource.
     */
    public function show($id)
    {
        abort_unless(Auth::user()->can('view feemanagement'), 403);

        $query = Fee::with(['student.studentUserDetails', 'institution', 'parent']);
        $this->scopeToViewer($query);

        $fee = $query->findOrFail($id);

        return view('feemanagement::show', compact('fee'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        abort_unless(Auth::user()->can('update feemanagement'), 403);

        $query = Fee::query();
        $this->scopeToViewer($query);
        $fee = $query->findOrFail($id);

        $validated = $request->validate(SaveFee::rules(withStudent: false));

        try {
            SaveFee::handle($validated, $fee);

            return redirect()->route('feemanagement.show', $fee->id)->with('success', 'Fee updated successfully!');
        } catch (\Exception $e) {
            Log::error('Fee update failed: '.$e->getMessage());

            return redirect()->back()->withInput()
                ->with('error', 'Failed to update fee: '.$e->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        abort_unless(Auth::user()->can('delete feemanagement'), 403);

        $query = Fee::query();
        $this->scopeToViewer($query);
        $fee = $query->findOrFail($id);

        $fee->delete();

        return redirect()->route('feemanagement.index')->with('success', 'Fee removed successfully!');
    }

    /**
     * Scope a Fee query to what the authenticated user is allowed to see:
     * admins see everything, institution owners/staff see their institutions'
     * fees, parents see their children's fees, and students see their own.
     */
    private function scopeToViewer($query): void
    {
        $user = Auth::user();

        if (isAdmin()) {
            return;
        }

        if ($user->hasRole('Parent')) {
            $query->where('parent_id', $user->id);

            return;
        }

        if ($user->hasRole('Student')) {
            $query->where('student_id', $user->id);

            return;
        }

        $query->where('institution_id', currentInstitution()?->id ?? 0);
    }
}
