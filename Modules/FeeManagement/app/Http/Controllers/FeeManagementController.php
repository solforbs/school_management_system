<?php

namespace Modules\FeeManagement\Http\Controllers;

use App\Http\Controllers\Concerns\Sortable;
use App\Http\Controllers\Controller;
use App\Services\Export\TableExportService;
use App\Services\FeeReminderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Modules\FeeManagement\Actions\SaveFee;
use Modules\FeeManagement\Models\Fee;

class FeeManagementController extends Controller
{
    use Sortable;

    private const HEADERS = ['Student Name', 'Admission No', 'Institution', 'Fee Title', 'Fee Type', 'Due Date', 'Amount', 'Amount Paid', 'Balance', 'Status'];

    public function __construct(
        private readonly FeeReminderService $reminderService,
    ) {}

    /**
     * Export the fee ledger (Excel or PDF) with the same filters and
     * institution/role scoping the index listing applies.
     */
    public function export(Request $request, TableExportService $exporter)
    {
        abort_unless(Auth::user()->can('export feemanagement'), 403);

        $query = Fee::with(['student.studentUserDetails', 'institution']);
        $this->scopeToViewer($query);

        $search = $request->string('search')->value();
        $status = $request->string('status')->value();
        $feeType = $request->string('fee_type')->value();
        $classId = $request->string('class_id')->value();

        $query->when($search !== '', function ($q) use ($search) {
            $term = '%'.$search.'%';

            $q->where(function ($sub) use ($term) {
                $sub->where('title', 'like', $term)
                    ->orWhereHas('student', fn ($sq) => $sq->where('name', 'like', $term)->orWhere('email', 'like', $term));
            });
        })
            ->when($status !== '', fn ($q) => $q->withStatus($status))
            ->when($feeType !== '', fn ($q) => $q->where('fee_type', $feeType))
            ->when($classId !== '', fn ($q) => $q->whereHas('student.studentUserDetails', fn ($sq) => $sq->where('class_id', $classId)))
            ->orderBy('due_date', 'asc');

        $rows = $query->get()->map(fn (Fee $fee) => $this->toExportRow($fee));

        $format = $request->string('format')->lower()->value();
        $filename = 'fee-invoices-'.now()->format('Y-m-d');

        return $format === 'pdf'
            ? $exporter->pdf($rows, self::HEADERS, $filename, 'Fee Invoices')
            : $exporter->excel($rows, self::HEADERS, $filename, 'Fee Invoices');
    }

    /**
     * Flatten a fee into the row shape shared by the Excel and PDF exports.
     *
     * @return array<int, string>
     */
    private function toExportRow(Fee $fee): array
    {
        $student = $fee->student;

        return [
            $student?->name ?? 'Unknown Student',
            $student?->studentUserDetails?->admission_number ?? 'N/A',
            $fee->institution?->name ?? 'N/A',
            $fee->title,
            ucfirst($fee->fee_type ?? ''),
            $fee->due_date ? $fee->due_date->format('Y-m-d') : '',
            number_format((float) ($fee->amount ?? 0), 2),
            number_format((float) ($fee->amount_paid ?? 0), 2),
            number_format((float) $fee->balance, 2),
            ucfirst($fee->status ?? ''),
        ];
    }

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

        if ($result['parents_queued'] === 0) {
            return redirect()->route('feemanagement.index')
                ->with('success', 'No outstanding fee balances to remind anyone about.');
        }

        $message = "Reminders queued for {$result['parents_queued']} parent(s) - {$result['emails_queued']} email(s), {$result['sms_queued']} SMS. They go out gradually in the background to protect your messaging budget.";
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
