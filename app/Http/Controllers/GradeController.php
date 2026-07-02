<?php

namespace App\Http\Controllers;

use App\Http\Requests\GradeRequest;
use App\Http\Requests\BulkGradeRequest;
use App\Models\AcademicYear;
use App\Models\Grade;
use App\Models\Schedule;
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
                ['name' => 'school_class_id', 'label' => 'Kelas', 'type' => 'select', 'options' => $this->availableClasses($request)->orderBy('name')->pluck('name', 'id')],
                ['name' => 'subject_id', 'label' => 'Mata Pelajaran', 'type' => 'select', 'options' => $this->availableSubjects($request)->orderBy('name')->pluck('name', 'id')],
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

    public function createBulk(Request $request): View
    {
        $schedules = $this->availableSchedules($request)
            ->with(['schoolClass.students' => fn ($query) => $query->orderBy('name'), 'subject', 'teacher', 'academicYear', 'semester'])
            ->orderBy('day')
            ->orderBy('starts_at')
            ->get();

        $selectedSchedule = $request->schedule_id
            ? $schedules->firstWhere('id', (int) $request->schedule_id)
            : null;

        $existingGrades = collect();
        if ($selectedSchedule) {
            $existingGrades = Grade::where('subject_id', $selectedSchedule->subject_id)
                ->where('academic_year_id', $selectedSchedule->academic_year_id)
                ->where('semester_id', $selectedSchedule->semester_id)
                ->whereIn('student_id', $selectedSchedule->schoolClass->students->pluck('id'))
                ->get()
                ->keyBy('student_id');
        }

        return view('grades.bulk', [
            'title' => 'Input Nilai Massal',
            'schedules' => $schedules,
            'selectedSchedule' => $selectedSchedule,
            'existingGrades' => $existingGrades,
        ]);
    }

    public function store(GradeRequest $request): RedirectResponse
    {
        $data = $request->validated();
        if ($request->user()->hasRole('Guru') && ! $request->user()->hasRole('Admin')) {
            $this->ensureTeacherCanManageGradeInput($request, $data);
            $data['teacher_id'] = $request->user()->teacher?->id;
        }
        $this->ensureStudentInClass($data['student_id'], $data['school_class_id']);

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

    public function storeBulk(BulkGradeRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $schedule = Schedule::with('schoolClass.students')->findOrFail($data['schedule_id']);
        $teacherId = $request->user()->hasRole('Admin')
            ? $schedule->teacher_id
            : $request->user()->teacher?->id;
        $allowedStudentIds = $schedule->schoolClass->students->pluck('id');
        $saved = 0;

        foreach ($data['grades'] as $grade) {
            if (! $allowedStudentIds->contains((int) $grade['student_id'])) {
                continue;
            }

            Grade::updateOrCreate(
                [
                    'student_id' => $grade['student_id'],
                    'subject_id' => $schedule->subject_id,
                    'academic_year_id' => $schedule->academic_year_id,
                    'semester_id' => $schedule->semester_id,
                ],
                [
                    'school_class_id' => $schedule->school_class_id,
                    'teacher_id' => $teacherId,
                    'assignment_score' => $grade['assignment_score'],
                    'daily_test_score' => $grade['daily_test_score'],
                    'midterm_score' => $grade['midterm_score'],
                    'final_exam_score' => $grade['final_exam_score'],
                    'practice_score' => $grade['practice_score'],
                    'attitude_score' => $grade['attitude_score'] ?? null,
                    'note' => $grade['note'] ?? null,
                ]
            );
            $saved++;
        }

        return redirect()->route('grades.index', [
            'school_class_id' => $schedule->school_class_id,
            'subject_id' => $schedule->subject_id,
        ])->with('status', "{$saved} data nilai berhasil disimpan.");
    }

    public function edit(Grade $grade): View
    {
        $this->ensureCanManageGrade(request(), $grade);

        return view('resources.form', $this->formData('Edit Nilai', $grade));
    }

    public function update(GradeRequest $request, Grade $grade): RedirectResponse
    {
        $this->ensureCanManageGrade($request, $grade);
        $data = $request->validated();
        if ($request->user()->hasRole('Guru') && ! $request->user()->hasRole('Admin')) {
            $this->ensureTeacherCanManageGradeInput($request, $data);
            $data['teacher_id'] = $request->user()->teacher?->id;
        }
        $this->ensureStudentInClass($data['student_id'], $data['school_class_id']);

        $grade->update($data);

        return redirect()->route('grades.index')->with('status', 'Nilai berhasil diperbarui.');
    }

    public function destroy(Grade $grade): RedirectResponse
    {
        $this->ensureCanManageGrade(request(), $grade);
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
        } elseif ($user->hasRole('Wali Kelas') && ! $user->hasRole('Admin')) {
            $query->whereHas('schoolClass', fn ($q) => $q->where('homeroom_teacher_id', $user->teacher?->id ?? 0));
        }

        return $query;
    }

    private function availableSchedules(Request $request)
    {
        $query = Schedule::query();
        $user = $request->user()->loadMissing('teacher');

        if ($user->hasRole('Guru') && ! $user->hasRole('Admin')) {
            $query->where('teacher_id', $user->teacher?->id ?? 0);
        }

        return $query;
    }

    private function availableClasses(Request $request)
    {
        $query = SchoolClass::query();
        $user = $request->user()->loadMissing(['student', 'teacher', 'children']);

        if ($user->hasRole('Siswa')) {
            $query->whereKey($user->student?->school_class_id ?? 0);
        } elseif ($user->hasRole('Orang Tua')) {
            $query->whereIn('id', $user->children->pluck('school_class_id'));
        } elseif ($user->hasRole('Guru') && ! $user->hasRole('Admin')) {
            $query->whereIn('id', $this->availableSchedules($request)->pluck('school_class_id'));
        } elseif ($user->hasRole('Wali Kelas') && ! $user->hasRole('Admin')) {
            $query->where('homeroom_teacher_id', $user->teacher?->id ?? 0);
        }

        return $query;
    }

    private function availableSubjects(Request $request)
    {
        $query = Subject::query();

        if ($request->user()->hasRole('Guru') && ! $request->user()->hasRole('Admin')) {
            $query->whereIn('id', $this->availableSchedules($request)->pluck('subject_id'));
        }

        return $query;
    }

    private function availableStudents(Request $request)
    {
        $query = Student::query();
        $classIds = $this->availableClasses($request)->pluck('id');

        if (! $request->user()->hasRole('Admin')) {
            $query->whereIn('school_class_id', $classIds);
        }

        return $query;
    }

    private function ensureCanManageGrade(Request $request, Grade $grade): void
    {
        if ($request->user()->hasRole('Admin')) {
            return;
        }

        abort_if($grade->teacher_id !== $request->user()->teacher?->id, 403);
    }

    private function ensureTeacherCanManageGradeInput(Request $request, array $data): void
    {
        abort_if(
            ! Schedule::where('teacher_id', $request->user()->teacher?->id)
                ->where('school_class_id', $data['school_class_id'])
                ->where('subject_id', $data['subject_id'])
                ->where('academic_year_id', $data['academic_year_id'])
                ->where('semester_id', $data['semester_id'])
                ->exists(),
            403
        );
    }

    private function ensureStudentInClass(int $studentId, int $classId): void
    {
        abort_if(! Student::whereKey($studentId)->where('school_class_id', $classId)->exists(), 422);
    }

    private function formData(string $title, Grade $item): array
    {
        $request = request();
        $teacher = $request->user()->teacher;
        $teachers = $request->user()->hasRole('Admin')
            ? Teacher::orderBy('name')->pluck('name', 'id')
            : Teacher::whereKey($teacher?->id ?? 0)->pluck('name', 'id');

        return [
            'title' => $title,
            'route' => 'grades',
            'item' => $item,
            'fields' => [
                ['name' => 'student_id', 'label' => 'Siswa', 'type' => 'select', 'options' => $this->availableStudents($request)->orderBy('name')->pluck('name', 'id')],
                ['name' => 'school_class_id', 'label' => 'Kelas', 'type' => 'select', 'options' => $this->availableClasses($request)->orderBy('name')->pluck('name', 'id')],
                ['name' => 'subject_id', 'label' => 'Mata Pelajaran', 'type' => 'select', 'options' => $this->availableSubjects($request)->orderBy('name')->pluck('name', 'id')],
                ['name' => 'teacher_id', 'label' => 'Guru', 'type' => 'select', 'options' => $teachers, 'value' => $teacher?->id],
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
