<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReportCardRequest;
use App\Models\AcademicYear;
use App\Models\ReportCard;
use App\Models\SchoolClass;
use App\Models\Semester;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportCardController extends Controller
{
    public function index(Request $request): View
    {
        $items = ReportCard::with(['student', 'schoolClass', 'academicYear', 'semester'])
            ->when($request->user()->hasRole('Siswa'), fn ($query) => $query->where('student_id', $request->user()->student?->id ?? 0))
            ->when($request->user()->hasRole('Orang Tua'), fn ($query) => $query->whereIn('student_id', $request->user()->children()->pluck('students.id')))
            ->latest()
            ->paginate(10);

        return view('resources.index', [
            'title' => 'Raport Digital',
            'route' => 'report-cards',
            'items' => $items,
            'columns' => [
                ['label' => 'Siswa', 'key' => 'student.name'],
                ['label' => 'Kelas', 'key' => 'schoolClass.name'],
                ['label' => 'Tahun Ajaran', 'key' => 'academicYear.year'],
                ['label' => 'Semester', 'key' => 'semester.name'],
                ['label' => 'Validasi', 'key' => 'is_validated', 'boolean' => true],
            ],
        ]);
    }

    public function create(): View
    {
        return view('resources.form', $this->formData('Buat Raport', new ReportCard()));
    }

    public function store(ReportCardRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['is_validated'] = $request->boolean('is_validated');
        ReportCard::updateOrCreate(
            ['student_id' => $data['student_id'], 'academic_year_id' => $data['academic_year_id'], 'semester_id' => $data['semester_id']],
            $data
        );

        return redirect()->route('report-cards.index')->with('status', 'Raport berhasil disimpan.');
    }

    public function show(ReportCard $reportCard): View
    {
        $reportCard->load(['student', 'schoolClass', 'academicYear', 'semester']);
        $grades = $reportCard->student->grades()
            ->with('subject')
            ->where('academic_year_id', $reportCard->academic_year_id)
            ->where('semester_id', $reportCard->semester_id)
            ->get();
        $attendanceSummary = $reportCard->student->attendances()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return view('reports.report-card', compact('reportCard', 'grades', 'attendanceSummary'));
    }

    public function edit(ReportCard $reportCard): View
    {
        return view('resources.form', $this->formData('Edit Raport', $reportCard));
    }

    public function update(ReportCardRequest $request, ReportCard $reportCard): RedirectResponse
    {
        $data = $request->validated();
        $data['is_validated'] = $request->boolean('is_validated');
        $reportCard->update($data);

        return redirect()->route('report-cards.index')->with('status', 'Raport berhasil diperbarui.');
    }

    public function validateCard(ReportCard $reportCard): RedirectResponse
    {
        $reportCard->update(['is_validated' => true]);

        return back()->with('status', 'Raport berhasil divalidasi.');
    }

    public function destroy(ReportCard $reportCard): RedirectResponse
    {
        $reportCard->delete();

        return back()->with('status', 'Raport berhasil dihapus.');
    }

    private function formData(string $title, ReportCard $item): array
    {
        return [
            'title' => $title,
            'route' => 'report-cards',
            'item' => $item,
            'fields' => [
                ['name' => 'student_id', 'label' => 'Siswa', 'type' => 'select', 'options' => Student::orderBy('name')->pluck('name', 'id')],
                ['name' => 'school_class_id', 'label' => 'Kelas', 'type' => 'select', 'options' => SchoolClass::orderBy('name')->pluck('name', 'id')],
                ['name' => 'academic_year_id', 'label' => 'Tahun Ajaran', 'type' => 'select', 'options' => AcademicYear::orderByDesc('year')->pluck('year', 'id')],
                ['name' => 'semester_id', 'label' => 'Semester', 'type' => 'select', 'options' => Semester::orderByDesc('id')->pluck('name', 'id')],
                ['name' => 'homeroom_note', 'label' => 'Catatan Wali Kelas', 'type' => 'textarea'],
                ['name' => 'is_validated', 'label' => 'Validasi Wali Kelas', 'type' => 'checkbox'],
            ],
        ];
    }
}
