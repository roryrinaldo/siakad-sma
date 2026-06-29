<?php

namespace App\Http\Controllers;

use App\Http\Requests\GradeRequest;
use App\Models\AcademicYear;
use App\Models\Grade;
use App\Models\SchoolClass;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GradeController extends Controller
{
    public function index(Request $request): View
    {
        $items = $this->visibleQuery($request)
            ->with(['student', 'schoolClass', 'subject', 'teacher', 'academicYear', 'semester'])
            ->when($request->school_class_id, fn ($query, $classId) => $query->where('school_class_id', $classId))
            ->when($request->subject_id, fn ($query, $subjectId) => $query->where('subject_id', $subjectId))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('resources.index', [
            'title' => 'Nilai Siswa',
            'route' => 'grades',
            'items' => $items,
            'filters' => [
                ['name' => 'school_class_id', 'label' => 'Kelas', 'type' => 'select', 'options' => SchoolClass::orderBy('name')->pluck('name', 'id')],
                ['name' => 'subject_id', 'label' => 'Mata Pelajaran', 'type' => 'select', 'options' => Subject::orderBy('name')->pluck('name', 'id')],
            ],
            'columns' => [
                ['label' => 'Siswa', 'key' => 'student.name'],
                ['label' => 'Kelas', 'key' => 'schoolClass.name'],
                ['label' => 'Mapel', 'key' => 'subject.name'],
                ['label' => 'Nilai Akhir', 'key' => 'final_score'],
                ['label' => 'Predikat', 'key' => 'predicate', 'badge' => true],
            ],
        ]);
    }

    public function create(): View
    {
        return view('resources.form', $this->formData('Input Nilai', new Grade()));
    }

    public function store(GradeRequest $request): RedirectResponse
    {
        $data = $request->validated();
        if ($request->user()->hasRole('Guru') && ! $request->user()->hasRole('Admin')) {
            $data['teacher_id'] = $request->user()->teacher?->id;
        }

        Grade::updateOrCreate(
            [
                'student_id' => $data['student_id'],
                'subject_id' => $data['subject_id'],
                'academic_year_id' => $data['academic_year_id'],
                'semester_id' => $data['semester_id'],
            ],
            $data
        );

        return redirect()->route('grades.index')->with('status', 'Nilai berhasil disimpan.');
    }

    public function edit(Grade $grade): View
    {
        return view('resources.form', $this->formData('Edit Nilai', $grade));
    }

    public function update(GradeRequest $request, Grade $grade): RedirectResponse
    {
        $grade->update($request->validated());

        return redirect()->route('grades.index')->with('status', 'Nilai berhasil diperbarui.');
    }

    public function destroy(Grade $grade): RedirectResponse
    {
        $grade->delete();

        return back()->with('status', 'Nilai berhasil dihapus.');
    }

    private function visibleQuery(Request $request)
    {
        $query = Grade::query();
        $user = $request->user()->loadMissing(['student', 'teacher', 'children']);

        if ($user->hasRole('Siswa')) {
            $query->where('student_id', $user->student?->id ?? 0);
        } elseif ($user->hasRole('Orang Tua')) {
            $query->whereIn('student_id', $user->children->pluck('id'));
        } elseif ($user->hasRole('Guru') && ! $user->hasAnyRole(['Admin', 'Kepala Sekolah', 'Wali Kelas'])) {
            $query->where('teacher_id', $user->teacher?->id ?? 0);
        }

        return $query;
    }

    private function formData(string $title, Grade $item): array
    {
        return [
            'title' => $title,
            'route' => 'grades',
            'item' => $item,
            'fields' => [
                ['name' => 'student_id', 'label' => 'Siswa', 'type' => 'select', 'options' => Student::orderBy('name')->pluck('name', 'id')],
                ['name' => 'school_class_id', 'label' => 'Kelas', 'type' => 'select', 'options' => SchoolClass::orderBy('name')->pluck('name', 'id')],
                ['name' => 'subject_id', 'label' => 'Mata Pelajaran', 'type' => 'select', 'options' => Subject::orderBy('name')->pluck('name', 'id')],
                ['name' => 'teacher_id', 'label' => 'Guru', 'type' => 'select', 'options' => Teacher::orderBy('name')->pluck('name', 'id')],
                ['name' => 'academic_year_id', 'label' => 'Tahun Ajaran', 'type' => 'select', 'options' => AcademicYear::orderByDesc('year')->pluck('year', 'id')],
                ['name' => 'semester_id', 'label' => 'Semester', 'type' => 'select', 'options' => Semester::orderByDesc('id')->pluck('name', 'id')],
                ['name' => 'assignment_score', 'label' => 'Nilai Tugas', 'type' => 'number', 'step' => '0.01'],
                ['name' => 'daily_test_score', 'label' => 'Nilai Ulangan Harian', 'type' => 'number', 'step' => '0.01'],
                ['name' => 'midterm_score', 'label' => 'Nilai UTS', 'type' => 'number', 'step' => '0.01'],
                ['name' => 'final_exam_score', 'label' => 'Nilai UAS', 'type' => 'number', 'step' => '0.01'],
                ['name' => 'practice_score', 'label' => 'Nilai Praktik', 'type' => 'number', 'step' => '0.01'],
                ['name' => 'attitude_score', 'label' => 'Nilai Sikap', 'type' => 'text'],
                ['name' => 'note', 'label' => 'Catatan', 'type' => 'textarea'],
            ],
        ];
    }
}
