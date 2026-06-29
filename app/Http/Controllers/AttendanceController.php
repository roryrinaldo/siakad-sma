<?php

namespace App\Http\Controllers;

use App\Http\Requests\AttendanceRequest;
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
                ['name' => 'school_class_id', 'label' => 'Kelas', 'type' => 'select', 'options' => SchoolClass::orderBy('name')->pluck('name', 'id')],
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

    public function store(AttendanceRequest $request): RedirectResponse
    {
        $data = $request->validated();
        if ($request->user()->hasRole('Guru') && ! $request->user()->hasRole('Admin')) {
            $data['teacher_id'] = $request->user()->teacher?->id;
        }

        Attendance::updateOrCreate(
            ['date' => $data['date'], 'student_id' => $data['student_id'], 'schedule_id' => $data['schedule_id'] ?? null],
            $data
        );

        return redirect()->route('attendances.index')->with('status', 'Absensi berhasil disimpan.');
    }

    public function edit(Attendance $attendance, Request $request): View
    {
        return view('resources.form', $this->formData('Edit Absensi', $attendance, $request));
    }

    public function update(AttendanceRequest $request, Attendance $attendance): RedirectResponse
    {
        $attendance->update($request->validated());

        return redirect()->route('attendances.index')->with('status', 'Absensi berhasil diperbarui.');
    }

    public function destroy(Attendance $attendance): RedirectResponse
    {
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
        }

        return $query;
    }

    private function formData(string $title, Attendance $item, Request $request): array
    {
        $teacher = $request->user()->teacher;

        return [
            'title' => $title,
            'route' => 'attendances',
            'item' => $item,
            'fields' => [
                ['name' => 'date', 'label' => 'Tanggal', 'type' => 'date'],
                ['name' => 'school_class_id', 'label' => 'Kelas', 'type' => 'select', 'options' => SchoolClass::orderBy('name')->pluck('name', 'id')],
                ['name' => 'schedule_id', 'label' => 'Jadwal', 'type' => 'select', 'options' => Schedule::with(['schoolClass', 'subject'])->get()->mapWithKeys(fn ($schedule) => [$schedule->id => $schedule->day.' '.$schedule->starts_at.' - '.$schedule->schoolClass->name.' '.$schedule->subject->name])],
                ['name' => 'student_id', 'label' => 'Siswa', 'type' => 'select', 'options' => Student::orderBy('name')->pluck('name', 'id')],
                ['name' => 'teacher_id', 'label' => 'Guru', 'type' => 'select', 'options' => Teacher::orderBy('name')->pluck('name', 'id'), 'value' => $teacher?->id],
                ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => collect(['hadir' => 'Hadir', 'izin' => 'Izin', 'sakit' => 'Sakit', 'alpha' => 'Alpha', 'terlambat' => 'Terlambat'])],
                ['name' => 'note', 'label' => 'Catatan', 'type' => 'textarea'],
            ],
        ];
    }
}
