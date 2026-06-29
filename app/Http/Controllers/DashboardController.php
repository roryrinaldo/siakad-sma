<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\Attendance;
use App\Models\Grade;
use App\Models\Schedule;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user()->loadMissing(['student.schoolClass', 'teacher', 'children.schoolClass']);
        $teacher = $user->teacher;
        $student = $user->student;
        $children = $user->children;

        $stats = [
            'Total Siswa' => Student::count(),
            'Total Guru' => Teacher::count(),
            'Total Kelas' => SchoolClass::count(),
            'Mata Pelajaran' => Subject::count(),
            'Siswa Aktif' => Student::where('status', 'aktif')->count(),
            'Absensi Hari Ini' => Attendance::whereDate('date', now()->toDateString())->count(),
            'Alpha Hari Ini' => Attendance::whereDate('date', now()->toDateString())->where('status', 'alpha')->count(),
            'Rata-rata Nilai' => number_format((float) Grade::avg('final_score'), 1),
        ];

        $today = now()->locale('id')->translatedFormat('l');
        $role = $user->roles->pluck('name')->first() ?? 'Pengguna';

        $schedules = Schedule::query()
            ->with(['schoolClass', 'subject', 'teacher'])
            ->when($teacher && ! $user->hasRole('Admin'), fn ($query) => $query->where('teacher_id', $teacher->id))
            ->when($student, fn ($query) => $query->where('school_class_id', $student->school_class_id))
            ->where('day', $this->normalizeDay($today))
            ->orderBy('starts_at')
            ->limit(6)
            ->get();

        $announcements = Announcement::query()
            ->where('status', 'publish')
            ->where(function ($query) use ($role, $student, $children): void {
                $classIds = collect([$student?->school_class_id])
                    ->merge($children->pluck('school_class_id'))
                    ->filter()
                    ->values();

                $query->whereNull('target_role')->orWhere('target_role', $role);

                if ($classIds->isNotEmpty()) {
                    $query->orWhereIn('target_class_id', $classIds);
                }
            })
            ->latest('published_at')
            ->limit(5)
            ->get();

        return view('dashboard.index', compact('stats', 'schedules', 'announcements', 'role', 'student', 'teacher', 'children'));
    }

    private function normalizeDay(string $day): string
    {
        return match ($day) {
            'Monday' => 'Senin',
            'Tuesday' => 'Selasa',
            'Wednesday' => 'Rabu',
            'Thursday' => 'Kamis',
            'Friday' => 'Jumat',
            'Saturday' => 'Sabtu',
            default => $day,
        };
    }
}
