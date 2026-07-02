<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReportCardRequest;
use App\Http\Requests\GenerateReportCardsRequest;
use App\Models\AcademicYear;
use App\Models\ReportCard;
use App\Models\SchoolClass;
use App\Models\Semester;
use App\Models\Student;
use Barryvdh\DomPDF\Facade\Pdf;
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
            ->when($request->user()->hasRole('Wali Kelas') && ! $request->user()->hasRole('Admin'), fn ($query) => $query->whereHas('schoolClass', fn ($q) => $q->where('homeroom_teacher_id', $request->user()->teacher?->id ?? 0)))
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

    public function createGenerate(Request $request): View
    {
        return view('report-cards.generate', [
            'title' => 'Generate Raport Massal',
            'classes' => $this->availableClasses($request)->withCount('students')->orderBy('name')->get(),
            'academicYears' => AcademicYear::orderByDesc('year')->pluck('year', 'id'),
            'semesters' => Semester::orderByDesc('id')->pluck('name', 'id'),
            'activeAcademicYear' => AcademicYear::where('is_active', true)->first(),
            'activeSemester' => Semester::where('is_active', true)->first(),
        ]);
    }

    public function store(ReportCardRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $this->ensureCanManageClass($request, (int) $data['school_class_id']);
        $this->ensureStudentInClass((int) $data['student_id'], (int) $data['school_class_id']);
        $data['is_validated'] = $request->boolean('is_validated');
        ReportCard::updateOrCreate(
            ['student_id' => $data['student_id'], 'academic_year_id' => $data['academic_year_id'], 'semester_id' => $data['semester_id']],
            $data
        );

        return redirect()->route('report-cards.index')->with('status', 'Raport berhasil disimpan.');
    }

    public function generate(GenerateReportCardsRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $this->ensureCanManageClass($request, (int) $data['school_class_id']);
        $schoolClass = SchoolClass::with('students')->findOrFail($data['school_class_id']);
        $generated = 0;

        foreach ($schoolClass->students as $student) {
            ReportCard::updateOrCreate(
                [
                    'student_id' => $student->id,
                    'academic_year_id' => $data['academic_year_id'],
                    'semester_id' => $data['semester_id'],
                ],
                [
                    'school_class_id' => $schoolClass->id,
                    'homeroom_note' => $data['homeroom_note'] ?? null,
                    'is_validated' => $request->boolean('is_validated'),
                ]
            );
            $generated++;
        }

        return redirect()->route('report-cards.index')
            ->with('status', "{$generated} raport berhasil dibuat atau diperbarui.");
    }

    public function show(ReportCard $reportCard): View
    {
        $this->ensureCanView($reportCard, request());
        $data = $this->reportCardData($reportCard);

        return view('reports.report-card', $data);
    }

    public function pdf(ReportCard $reportCard)
    {
        $this->ensureCanView($reportCard, request());
        $data = $this->reportCardData($reportCard);
        $reportCard->update(['printed_at' => now()]);

        return Pdf::loadView('reports.report-card-pdf', $data)
            ->setPaper('a4')
            ->download('raport-'.$reportCard->student->nis.'-'.$reportCard->semester->name.'.pdf');
    }

    public function edit(ReportCard $reportCard): View
    {
        $this->ensureCanManageReportCard(request(), $reportCard);

        return view('resources.form', $this->formData('Edit Raport', $reportCard));
    }

    public function update(ReportCardRequest $request, ReportCard $reportCard): RedirectResponse
    {
        $this->ensureCanManageReportCard($request, $reportCard);
        $data = $request->validated();
        $this->ensureCanManageClass($request, (int) $data['school_class_id']);
        $this->ensureStudentInClass((int) $data['student_id'], (int) $data['school_class_id']);
        $data['is_validated'] = $request->boolean('is_validated');
        $reportCard->update($data);

        return redirect()->route('report-cards.index')->with('status', 'Raport berhasil diperbarui.');
    }

    public function validateCard(ReportCard $reportCard): RedirectResponse
    {
        $this->ensureCanManageReportCard(request(), $reportCard);
        $reportCard->update(['is_validated' => true]);

        return back()->with('status', 'Raport berhasil divalidasi.');
    }

    public function destroy(ReportCard $reportCard): RedirectResponse
    {
        $this->ensureCanManageReportCard(request(), $reportCard);
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
                ['name' => 'student_id', 'label' => 'Siswa', 'type' => 'select', 'options' => $this->availableStudents(request())->orderBy('name')->pluck('name', 'id')],
                ['name' => 'school_class_id', 'label' => 'Kelas', 'type' => 'select', 'options' => $this->availableClasses(request())->orderBy('name')->pluck('name', 'id')],
                ['name' => 'academic_year_id', 'label' => 'Tahun Ajaran', 'type' => 'select', 'options' => AcademicYear::orderByDesc('year')->pluck('year', 'id')],
                ['name' => 'semester_id', 'label' => 'Semester', 'type' => 'select', 'options' => Semester::orderByDesc('id')->pluck('name', 'id')],
                ['name' => 'homeroom_note', 'label' => 'Catatan Wali Kelas', 'type' => 'textarea'],
                ['name' => 'is_validated', 'label' => 'Validasi Wali Kelas', 'type' => 'checkbox'],
            ],
        ];
    }

    private function availableClasses(Request $request)
    {
        $query = SchoolClass::query();
        $user = $request->user()->loadMissing('teacher');

        if ($user->hasRole('Wali Kelas') && ! $user->hasRole('Admin')) {
            $query->where('homeroom_teacher_id', $user->teacher?->id ?? 0);
        }

        return $query;
    }

    private function availableStudents(Request $request)
    {
        $query = Student::query();

        if (! $request->user()->hasRole('Admin')) {
            $query->whereIn('school_class_id', $this->availableClasses($request)->pluck('id'));
        }

        return $query;
    }

    private function reportCardData(ReportCard $reportCard): array
    {
        $reportCard->load(['student', 'schoolClass.homeroomTeacher', 'academicYear', 'semester']);
        $grades = $reportCard->student->grades()
            ->with('subject')
            ->where('academic_year_id', $reportCard->academic_year_id)
            ->where('semester_id', $reportCard->semester_id)
            ->orderBy('subject_id')
            ->get();
        $attendanceQuery = $reportCard->student->attendances();

        if ($reportCard->semester?->starts_on && $reportCard->semester?->ends_on) {
            $attendanceQuery->whereBetween('date', [
                $reportCard->semester->starts_on->toDateString(),
                $reportCard->semester->ends_on->toDateString(),
            ]);
        }

        $attendanceSummary = $attendanceQuery
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return compact('reportCard', 'grades', 'attendanceSummary');
    }

    private function ensureCanView(ReportCard $reportCard, Request $request): void
    {
        $user = $request->user()->loadMissing(['student', 'teacher', 'children']);

        if ($user->hasAnyRole(['Admin', 'Kepala Sekolah'])) {
            return;
        }

        if ($user->hasRole('Siswa')) {
            abort_if($reportCard->student_id !== $user->student?->id, 403);

            return;
        }

        if ($user->hasRole('Orang Tua')) {
            abort_if(! $user->children->pluck('id')->contains($reportCard->student_id), 403);

            return;
        }

        if ($user->hasRole('Wali Kelas')) {
            abort_if($reportCard->schoolClass?->homeroom_teacher_id !== $user->teacher?->id, 403);

            return;
        }

        abort(403);
    }

    private function ensureCanManageReportCard(Request $request, ReportCard $reportCard): void
    {
        if ($request->user()->hasRole('Admin')) {
            return;
        }

        abort_if($reportCard->schoolClass?->homeroom_teacher_id !== $request->user()->teacher?->id, 403);
    }

    private function ensureCanManageClass(Request $request, int $classId): void
    {
        if ($request->user()->hasRole('Admin')) {
            return;
        }

        abort_if(
            ! SchoolClass::whereKey($classId)->where('homeroom_teacher_id', $request->user()->teacher?->id)->exists(),
            403
        );
    }

    private function ensureStudentInClass(int $studentId, int $classId): void
    {
        abort_if(! Student::whereKey($studentId)->where('school_class_id', $classId)->exists(), 422);
    }
}
