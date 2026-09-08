<?php

namespace Modules\Staff\Http\Controllers;

use App\Http\Controllers\Concerns\Sortable;
use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Modules\Institution\Models\Institution;
use Modules\Staff\Actions\SaveStaffPayment;
use Modules\Staff\Models\StaffDetails;
use Modules\Staff\Models\StaffPayment;

/**
 * Payroll: what each staff member is paid, month by month. Owned by the
 * Accountant (and the Director), alongside fee management.
 */
class StaffPaymentController extends Controller
{
    use Sortable;

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        abort_unless(Auth::user()->can('create payroll'), 403);

        $validated = $request->validate(SaveStaffPayment::rules());

        $staff = $this->findStaff($validated['staff_details_id']);
        $period = Carbon::parse($validated['period'].'-01')->startOfMonth();

        if (SaveStaffPayment::periodAlreadyRecorded($staff, $period)) {
            return redirect()->back()->withInput()
                ->with('error', 'A payment for '.$staff->name.' in '.$period->format('F Y').' already exists.');
        }

        try {
            SaveStaffPayment::handle($validated, $staff, recordedBy: Auth::id());

            return redirect()->route('staff.payments.index')->with('success', 'Staff payment recorded successfully!');
        } catch (\Exception $e) {
            Log::error('Staff payment creation failed: '.$e->getMessage());

            return redirect()->back()->withInput()
                ->with('error', 'Failed to record staff payment: '.$e->getMessage());
        }
    }

    /**
     * Show the specified resource.
     */
    public function show(StaffPayment $payment)
    {
        abort_unless(Auth::user()->can('view payroll'), 403);

        $this->authorizeAccessTo($payment);

        $payment->load(['staff', 'institution', 'recordedBy']);

        return view('staff::payments.show', compact('payment'));
    }

    /**
     * The payslip as a PDF - what the "Print Payslip" button hands over, so
     * a printed copy is the voucher itself rather than a screenshot of the
     * browser window. Gated exactly like show(): if you may view the
     * payslip, you may print it.
     */
    public function payslip(StaffPayment $payment)
    {
        abort_unless(Auth::user()->can('view payroll'), 403);

        $this->authorizeAccessTo($payment);

        $payment->load(['staff', 'institution', 'recordedBy']);

        $pdf = Pdf::loadView('staff::pdf.payslip', [
            'payment' => $payment,
            'institution' => $payment->institution,
            'logoDataUri' => $this->logoDataUri($payment->institution),
        ])->setPaper('a4');

        $reference = str_pad((string) $payment->id, 5, '0', STR_PAD_LEFT);

        return $pdf->download("payslip-{$reference}-{$payment->period->format('Y-m')}.pdf");
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, StaffPayment $payment)
    {
        abort_unless(Auth::user()->can('update payroll'), 403);

        $this->authorizeAccessTo($payment);

        $validated = $request->validate(SaveStaffPayment::rules());

        $staff = $this->findStaff($validated['staff_details_id']);
        $period = Carbon::parse($validated['period'].'-01')->startOfMonth();

        if (SaveStaffPayment::periodAlreadyRecorded($staff, $period, $payment)) {
            return redirect()->back()->withInput()
                ->with('error', 'A payment for '.$staff->name.' in '.$period->format('F Y').' already exists.');
        }

        try {
            SaveStaffPayment::handle($validated, $staff, $payment);

            return redirect()->route('staff.payments.show', $payment)->with('success', 'Staff payment updated successfully!');
        } catch (\Exception $e) {
            Log::error('Staff payment update failed: '.$e->getMessage());

            return redirect()->back()->withInput()
                ->with('error', 'Failed to update staff payment: '.$e->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(StaffPayment $payment)
    {
        abort_unless(Auth::user()->can('delete payroll'), 403);

        $this->authorizeAccessTo($payment);

        $payment->delete();

        return redirect()->route('staff.payments.index')->with('success', 'Staff payment removed successfully!');
    }

    /**
     * Resolve the selected staff member, refusing anyone outside the
     * viewer's institution - 'exists' alone would let a crafted request
     * pay another school's staff.
     */
    private function findStaff(int|string $id): StaffDetails
    {
        $staff = StaffDetails::findOrFail($id);

        if (! isAdmin()) {
            abort_unless($staff->institution_id === currentInstitution()?->id, 403);
        }

        return $staff;
    }

    /**
     * Ensure a non-admin viewer only manages payroll from their currently
     * active institution.
     */
    private function authorizeAccessTo(StaffPayment $payment): void
    {
        if (isAdmin()) {
            return;
        }

        abort_unless($payment->institution_id === currentInstitution()?->id, 403);
    }

    /**
     * The institution logo inlined for the PDF letterhead - dompdf can't
     * reach the storage disk over HTTP, so the bytes travel with the view.
     */
    private function logoDataUri(?Institution $institution): ?string
    {
        if (! $institution?->logo || ! Storage::disk('public')->exists($institution->logo)) {
            return null;
        }

        $mime = Storage::disk('public')->mimeType($institution->logo);

        return 'data:'.$mime.';base64,'.base64_encode(Storage::disk('public')->get($institution->logo));
    }
}
