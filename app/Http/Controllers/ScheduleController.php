<?php

namespace App\Http\Controllers;

use App\Http\Requests\ScheduleRequest;
use App\Models\AcademicYear;
use App\Models\Schedule;
use App\Models\SchoolClass;
use App\Models\Semester;
use App\Models\Subject;
use App\Models\Teacher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ScheduleController extends Controller
{
    public function index(Request $request): View
    {
        $items = Schedule::with(['schoolClass', 'subject', 'teacher', 'academicYear', 'semester'])
            ->when($request->user()->hasRole('Siswa'), fn ($query) => $query->where('school_class_id', $request->user()->student?->school_class_id ?? 0))
            ->when($request->user()->hasRole('Orang Tua'), fn ($query) => $query->whereIn('school_class_id', $request->user()->children()->pluck('school_class_id')))
            ->when($request->user()->hasRole('Guru') && ! $request->user()->hasAnyRole(['Admin', 'Kepala Sekolah', 'Wali Kelas']), fn ($query) => $query->where('teacher_id', $request->user()->teacher?->id ?? 0))
            ->when($request->user()->hasRole('Wali Kelas') && ! $request->user()->hasRole('Admin'), fn ($query) => $query->whereHas('schoolClass', fn ($q) => $q->where('homeroom_teacher_id', $request->user()->teacher?->id ?? 0)))
            ->orderBy('day')
            ->orderBy('starts_at')
            ->paginate(10);

        return view('resources.index', [
            'title' => 'Jadwal Pelajaran',
            'route' => 'schedules',
            'items' => $items,
            'columns' => [
                ['label' => 'Hari', 'key' => 'day'],
                ['label' => 'Jam', 'key' => 'time_range'],
                ['label' => 'Kelas', 'key' => 'schoolClass.name'],
                ['label' => 'Mapel', 'key' => 'subject.name'],
                ['label' => 'Guru', 'key' => 'teacher.name'],
            ],
        ]);
    }

    public function create(): View
    {
        return view('resources.form', $this->formData('Tambah Jadwal', new Schedule()));
    }

    public function show(Schedule $schedule): View
    {
        $schedule->load(['schoolClass', 'subject', 'teacher', 'academicYear', 'semester']);

        return view('resources.show', [
            'title' => 'Detail Jadwal',
            'route' => 'schedules',
            'item' => $schedule,
            'columns' => [
                ['label' => 'Hari', 'key' => 'day'],
                ['label' => 'Jam', 'key' => 'time_range'],
                ['label' => 'Kelas', 'key' => 'schoolClass.name'],
                ['label' => 'Mata Pelajaran', 'key' => 'subject.name'],
                ['label' => 'Guru', 'key' => 'teacher.name'],
                ['label' => 'Ruang', 'key' => 'room'],
            ],
        ]);
    }

    public function store(ScheduleRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $this->ensureNoConflict($data);
        Schedule::create($data);

        return redirect()->route('schedules.index')->with('status', 'Jadwal berhasil ditambahkan.');
    }

    public function edit(Schedule $schedule): View
    {
        return view('resources.form', $this->formData('Edit Jadwal', $schedule));
    }

    public function update(ScheduleRequest $request, Schedule $schedule): RedirectResponse
    {
        $data = $request->validated();
        $this->ensureNoConflict($data, $schedule->id);
        $schedule->update($data);

        return redirect()->route('schedules.index')->with('status', 'Jadwal berhasil diperbarui.');
    }

    public function destroy(Schedule $schedule): RedirectResponse
    {
        $schedule->delete();

        return back()->with('status', 'Jadwal berhasil dihapus.');
    }

    private function formData(string $title, Schedule $item): array
    {
        return [
            'title' => $title,
            'route' => 'schedules',
            'item' => $item,
            'fields' => [
                ['name' => 'school_class_id', 'label' => 'Kelas', 'type' => 'select', 'options' => SchoolClass::orderBy('name')->pluck('name', 'id')],
                ['name' => 'subject_id', 'label' => 'Mata Pelajaran', 'type' => 'select', 'options' => Subject::orderBy('name')->pluck('name', 'id')],
                ['name' => 'teacher_id', 'label' => 'Guru', 'type' => 'select', 'options' => Teacher::orderBy('name')->pluck('name', 'id')],
                ['name' => 'academic_year_id', 'label' => 'Tahun Ajaran', 'type' => 'select', 'options' => AcademicYear::orderByDesc('year')->pluck('year', 'id')],
                ['name' => 'semester_id', 'label' => 'Semester', 'type' => 'select', 'options' => Semester::orderByDesc('id')->pluck('name', 'id')],
                ['name' => 'day', 'label' => 'Hari', 'type' => 'select', 'options' => collect(['Senin' => 'Senin', 'Selasa' => 'Selasa', 'Rabu' => 'Rabu', 'Kamis' => 'Kamis', 'Jumat' => 'Jumat', 'Sabtu' => 'Sabtu'])],
                ['name' => 'starts_at', 'label' => 'Jam Mulai', 'type' => 'time'],
                ['name' => 'ends_at', 'label' => 'Jam Selesai', 'type' => 'time'],
                ['name' => 'room', 'label' => 'Ruang', 'type' => 'text'],
            ],
        ];
    }

    private function ensureNoConflict(array $data, ?int $except = null): void
    {
        $conflict = Schedule::query()
            ->when($except, fn ($query) => $query->whereKeyNot($except))
            ->where('day', $data['day'])
            ->where('academic_year_id', $data['academic_year_id'])
            ->where('semester_id', $data['semester_id'])
            ->where('starts_at', '<', $data['ends_at'])
            ->where('ends_at', '>', $data['starts_at'])
            ->where(fn ($query) => $query
                ->where('teacher_id', $data['teacher_id'])
                ->orWhere('school_class_id', $data['school_class_id']))
            ->exists();

        abort_if($conflict, 422, 'Jadwal bentrok untuk guru atau kelas pada jam tersebut.');
    }
}
