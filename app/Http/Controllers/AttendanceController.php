<?php

namespace App\Http\Controllers;

use App\Http\Requests\AttendanceRequest;
use App\Http\Requests\BulkAttendanceRequest;
use App\Models\Attendance;
use App\Models\Schedule;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Teacher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AttendanceController extends Controller
{
    public function index(Request $request): View
    {
        $items = $this->visibleQuery($request)
            ->with(['student', 'schoolClass', 'teacher', 'schedule.subject'])
            ->when($request->date, fn ($query, $date) => $query->whereDate('date', $date))
            ->when($request->school_class_id, fn ($query, $classId) => $query->where('school_class_id', $classId))
            ->when($request->status, fn ($query, $status) => $query->where('status', $status))
            ->latest('date')
            ->paginate(10)
            ->withQueryString();

        return view('resources.index', [
            'title' => 'Absensi Siswa',
            'route' => 'attendances',
            'items' => $items,
            'filters' => [
                ['name' => 'date', 'label' => 'Tanggal', 'type' => 'date'],
                ['name' => 'school_class_id', 'label' => 'Kelas', 'type' => 'select', 'options' => $this->availableClasses($request)->orderBy('name')->pluck('name', 'id')],
                ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => collect(['hadir' => 'Hadir', 'izin' => 'Izin', 'sakit' => 'Sakit', 'alpha' => 'Alpha', 'terlambat' => 'Terlambat'])],
            ],
            'columns' => [
                ['label' => 'Tanggal', 'key' => 'date'],
                ['label' => 'Siswa', 'key' => 'student.name'],
                ['label' => 'Kelas', 'key' => 'schoolClass.name'],
                ['label' => 'Status', 'key' => 'status', 'badge' => true],
            ],
        ]);
    }

    public function create(Request $request): View
    {
        return view('resources.form', $this->formData('Input Absensi', new Attendance(), $request));
    }

    public function createBulk(Request $request): View
    {
        $schedules = $this->availableSchedules($request)
            ->with(['schoolClass.students' => fn ($query) => $query->orderBy('name'), 'subject', 'teacher'])
            ->orderBy('day')
            ->orderBy('starts_at')
            ->get();

        $selectedSchedule = $request->schedule_id
            ? $schedules->firstWhere('id', (int) $request->schedule_id)
            : null;

        $existingAttendances = collect();
        if ($selectedSchedule) {
            $existingAttendances = Attendance::where('schedule_id', $selectedSchedule->id)
                ->whereDate('date', $request->date ?: now()->toDateString())
                ->get()
                ->keyBy('student_id');
        }

        return view('attendances.bulk', [
            'title' => 'Input Absensi Massal',
            'schedules' => $schedules,
            'selectedSchedule' => $selectedSchedule,
            'existingAttendances' => $existingAttendances,
            'date' => $request->date ?: now()->toDateString(),
            'statuses' => collect(['hadir' => 'Hadir', 'izin' => 'Izin', 'sakit' => 'Sakit', 'alpha' => 'Alpha', 'terlambat' => 'Terlambat']),
        ]);
    }

    public function store(AttendanceRequest $request): RedirectResponse
    {
        $data = $request->validated();
        if ($request->user()->hasRole('Guru') && ! $request->user()->hasRole('Admin')) {
            $this->ensureTeacherCanManageSchedule($request, $data['schedule_id'] ?? null);
            $data['teacher_id'] = $request->user()->teacher?->id;
        }
        $this->ensureAttendanceMatchesClass($data);

        Attendance::updateOrCreate(
            ['date' => $data['date'], 'student_id' => $data['student_id'], 'schedule_id' => $data['schedule_id'] ?? null],
            $data
        );

        return redirect()->route('attendances.index')->with('status', 'Absensi berhasil disimpan.');
    }

    public function storeBulk(BulkAttendanceRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $schedule = Schedule::with('schoolClass.students')->findOrFail($data['schedule_id']);
        $teacherId = $request->user()->hasRole('Admin')
            ? $schedule->teacher_id
            : $request->user()->teacher?->id;
        $allowedStudentIds = $schedule->schoolClass->students->pluck('id');
        $saved = 0;

        foreach ($data['attendances'] as $attendance) {
            if (! $allowedStudentIds->contains((int) $attendance['student_id'])) {
                continue;
            }

            Attendance::updateOrCreate(
                [
                    'date' => $data['date'],
                    'student_id' => $attendance['student_id'],
                    'schedule_id' => $schedule->id,
                ],
                [
                    'school_class_id' => $schedule->school_class_id,
                    'teacher_id' => $teacherId,
                    'status' => $attendance['status'],
                    'note' => $attendance['note'] ?? null,
                ]
            );
            $saved++;
        }

        return redirect()->route('attendances.index', ['date' => $data['date'], 'school_class_id' => $schedule->school_class_id])
            ->with('status', "{$saved} data absensi berhasil disimpan.");
    }

    public function edit(Attendance $attendance, Request $request): View
    {
        $this->ensureCanManageAttendance($request, $attendance);

        return view('resources.form', $this->formData('Edit Absensi', $attendance, $request));
    }

    public function update(AttendanceRequest $request, Attendance $attendance): RedirectResponse
    {
        $this->ensureCanManageAttendance($request, $attendance);
        $data = $request->validated();
        if ($request->user()->hasRole('Guru') && ! $request->user()->hasRole('Admin')) {
            $this->ensureTeacherCanManageSchedule($request, $data['schedule_id'] ?? null);
            $data['teacher_id'] = $request->user()->teacher?->id;
        }
        $this->ensureAttendanceMatchesClass($data);

        $attendance->update($data);

        return redirect()->route('attendances.index')->with('status', 'Absensi berhasil diperbarui.');
    }

    public function destroy(Attendance $attendance): RedirectResponse
    {
        $this->ensureCanManageAttendance(request(), $attendance);
        $attendance->delete();

        return back()->with('status', 'Absensi berhasil dihapus.');
    }

    private function visibleQuery(Request $request)
    {
        $query = Attendance::query();
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

    private function availableStudents(Request $request)
    {
        $query = Student::query();
        $classIds = $this->availableClasses($request)->pluck('id');

        if (! $request->user()->hasRole('Admin')) {
            $query->whereIn('school_class_id', $classIds);
        }

        return $query;
    }

    private function ensureCanManageAttendance(Request $request, Attendance $attendance): void
    {
        if ($request->user()->hasRole('Admin')) {
            return;
        }

        abort_if($attendance->teacher_id !== $request->user()->teacher?->id, 403);
    }

    private function ensureTeacherCanManageSchedule(Request $request, ?int $scheduleId): void
    {
        abort_if(
            ! $scheduleId || ! Schedule::whereKey($scheduleId)->where('teacher_id', $request->user()->teacher?->id)->exists(),
            403
        );
    }

    private function ensureAttendanceMatchesClass(array $data): void
    {
        abort_if(! Student::whereKey($data['student_id'])->where('school_class_id', $data['school_class_id'])->exists(), 422);

        if (! empty($data['schedule_id'])) {
            abort_if(! Schedule::whereKey($data['schedule_id'])->where('school_class_id', $data['school_class_id'])->exists(), 422);
        }
    }

    private function formData(string $title, Attendance $item, Request $request): array
    {
        $teacher = $request->user()->teacher;
        $schedules = $this->availableSchedules($request)
            ->with(['schoolClass', 'subject'])
            ->get()
            ->mapWithKeys(fn ($schedule) => [$schedule->id => $schedule->day.' '.$schedule->starts_at.' - '.$schedule->schoolClass->name.' '.$schedule->subject->name]);
        $teachers = $request->user()->hasRole('Admin')
            ? Teacher::orderBy('name')->pluck('name', 'id')
            : Teacher::whereKey($teacher?->id ?? 0)->pluck('name', 'id');

        return [
            'title' => $title,
            'route' => 'attendances',
            'item' => $item,
            'fields' => [
                ['name' => 'date', 'label' => 'Tanggal', 'type' => 'date'],
                ['name' => 'school_class_id', 'label' => 'Kelas', 'type' => 'select', 'options' => $this->availableClasses($request)->orderBy('name')->pluck('name', 'id')],
                ['name' => 'schedule_id', 'label' => 'Jadwal', 'type' => 'select', 'options' => $schedules],
                ['name' => 'student_id', 'label' => 'Siswa', 'type' => 'select', 'options' => $this->availableStudents($request)->orderBy('name')->pluck('name', 'id')],
                ['name' => 'teacher_id', 'label' => 'Guru', 'type' => 'select', 'options' => $teachers, 'value' => $teacher?->id],
                ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => collect(['hadir' => 'Hadir', 'izin' => 'Izin', 'sakit' => 'Sakit', 'alpha' => 'Alpha', 'terlambat' => 'Terlambat'])],
                ['name' => 'note', 'label' => 'Catatan', 'type' => 'textarea'],
            ],
        ];
    }
}
