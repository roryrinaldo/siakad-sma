<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\AcademicYear;
use App\Models\Grade;
use App\Models\ReportCard;
use App\Models\SchoolClass;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function index(): View
    {
        $request = request();

        return view('reports.index', [
            'reports' => $this->availableReports($request),
            'classes' => $this->visibleClasses($request)->orderBy('name')->pluck('name', 'id'),
            'subjects' => $this->visibleSubjects($request)->orderBy('name')->pluck('name', 'id'),
            'students' => $this->visibleStudents($request)->orderBy('name')->pluck('name', 'id'),
            'academicYears' => AcademicYear::orderByDesc('year')->pluck('year', 'id'),
            'semesters' => Semester::orderByDesc('id')->pluck('name', 'id'),
        ]);
    }

    public function students(Request $request, string $format): Response
    {
        $rows = $this->visibleStudents($request)->with('schoolClass')
            ->when($request->school_class_id, fn ($query, $classId) => $query->where('school_class_id', $classId))
            ->when($request->status, fn ($query, $status) => $query->where('status', $status))
            ->orderBy('name')
            ->get();

        return $this->export($format, 'laporan-siswa', ['NIS', 'Nama', 'Kelas', 'Status'], $rows->map(fn ($student) => [
            $student->nis,
            $student->name,
            $student->schoolClass?->name,
            $student->status,
        ])->all());
    }

    public function teachers(Request $request, string $format): Response
    {
        abort_if(! $request->user()->hasAnyRole(['Admin', 'Kepala Sekolah']), 403);

        $rows = Teacher::query()
            ->when($request->filled('is_active'), fn ($query) => $query->where('is_active', $request->boolean('is_active')))
            ->orderBy('name')
            ->get();

        return $this->export($format, 'laporan-guru', ['NIP', 'Nama', 'Email', 'Status'], $rows->map(fn ($teacher) => [
            $teacher->nip,
            $teacher->name,
            $teacher->email,
            $teacher->is_active ? 'Aktif' : 'Nonaktif',
        ])->all());
    }

    public function attendances(Request $request, string $format): Response
    {
        $rows = Attendance::with(['student', 'schoolClass'])
            ->tap(fn ($query) => $this->scopeAttendanceReport($query, $request))
            ->when($request->school_class_id, fn ($query, $classId) => $query->where('school_class_id', $classId))
            ->when($request->student_id, fn ($query, $studentId) => $query->where('student_id', $studentId))
            ->when($request->status, fn ($query, $status) => $query->where('status', $status))
            ->when($request->date_from, fn ($query, $date) => $query->whereDate('date', '>=', $date))
            ->when($request->date_to, fn ($query, $date) => $query->whereDate('date', '<=', $date))
            ->latest('date')
            ->get();

        return $this->export($format, 'laporan-absensi', ['Tanggal', 'Siswa', 'Kelas', 'Status'], $rows->map(fn ($attendance) => [
            optional($attendance->date)->format('Y-m-d'),
            $attendance->student?->name,
            $attendance->schoolClass?->name,
            $attendance->status,
        ])->all());
    }

    public function grades(Request $request, string $format): Response
    {
        $rows = Grade::with(['student', 'schoolClass', 'subject', 'academicYear', 'semester'])
            ->tap(fn ($query) => $this->scopeGradeReport($query, $request))
            ->when($request->school_class_id, fn ($query, $classId) => $query->where('school_class_id', $classId))
            ->when($request->student_id, fn ($query, $studentId) => $query->where('student_id', $studentId))
            ->when($request->subject_id, fn ($query, $subjectId) => $query->where('subject_id', $subjectId))
            ->when($request->academic_year_id, fn ($query, $yearId) => $query->where('academic_year_id', $yearId))
            ->when($request->semester_id, fn ($query, $semesterId) => $query->where('semester_id', $semesterId))
            ->orderByDesc('final_score')
            ->get();

        return $this->export($format, 'laporan-nilai', ['Siswa', 'Kelas', 'Mapel', 'Tahun Ajaran', 'Semester', 'Nilai Akhir', 'Predikat', 'Catatan'], $rows->map(fn ($grade) => [
            $grade->student?->name,
            $grade->schoolClass?->name,
            $grade->subject?->name,
            $grade->academicYear?->year,
            $grade->semester?->name,
            $grade->final_score,
            $grade->predicate,
            $grade->note,
        ])->all());
    }

    public function reportCards(Request $request, string $format): Response
    {
        abort_if($request->user()->hasRole('Guru') && ! $request->user()->hasAnyRole(['Admin', 'Kepala Sekolah', 'Wali Kelas']), 403);

        $rows = ReportCard::with(['student', 'schoolClass', 'academicYear', 'semester'])
            ->tap(fn ($query) => $this->scopeReportCardReport($query, $request))
            ->when($request->school_class_id, fn ($query, $classId) => $query->where('school_class_id', $classId))
            ->when($request->student_id, fn ($query, $studentId) => $query->where('student_id', $studentId))
            ->when($request->academic_year_id, fn ($query, $yearId) => $query->where('academic_year_id', $yearId))
            ->when($request->semester_id, fn ($query, $semesterId) => $query->where('semester_id', $semesterId))
            ->when($request->filled('is_validated'), fn ($query) => $query->where('is_validated', $request->boolean('is_validated')))
            ->get();

        return $this->export($format, 'laporan-raport', ['Siswa', 'Kelas', 'Tahun Ajaran', 'Semester', 'Validasi'], $rows->map(fn ($card) => [
            $card->student?->name,
            $card->schoolClass?->name,
            $card->academicYear?->year,
            $card->semester?->name,
            $card->is_validated ? 'Tervalidasi' : 'Draft',
        ])->all());
    }

    private function export(string $format, string $filename, array $headers, array $rows): Response
    {
        if ($format === 'pdf' && class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('reports.table-pdf', [
                'title' => str($filename)->replace('-', ' ')->title(),
                'headers' => $headers,
                'rows' => $rows,
            ]);

            return $pdf->download($filename.'.pdf');
        }

        $stream = fopen('php://temp', 'r+');
        fputcsv($stream, $headers);
        foreach ($rows as $row) {
            fputcsv($stream, $row);
        }
        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$filename.'.csv"',
        ]);
    }

    private function availableReports(Request $request): array
    {
        $reports = [
            ['label' => 'Laporan Data Siswa', 'route' => 'reports.students', 'roles' => ['Admin', 'Kepala Sekolah', 'Guru', 'Wali Kelas']],
            ['label' => 'Laporan Data Guru', 'route' => 'reports.teachers', 'roles' => ['Admin', 'Kepala Sekolah']],
            ['label' => 'Laporan Absensi Siswa', 'route' => 'reports.attendances', 'roles' => ['Admin', 'Kepala Sekolah', 'Guru', 'Wali Kelas']],
            ['label' => 'Laporan Nilai Siswa', 'route' => 'reports.grades', 'roles' => ['Admin', 'Kepala Sekolah', 'Guru', 'Wali Kelas']],
            ['label' => 'Laporan Raport Siswa', 'route' => 'reports.report-cards', 'roles' => ['Admin', 'Kepala Sekolah', 'Wali Kelas']],
        ];

        return collect($reports)
            ->filter(fn (array $report) => $request->user()->hasAnyRole($report['roles']))
            ->values()
            ->all();
    }

    private function visibleClasses(Request $request)
    {
        $query = SchoolClass::query();
        $user = $request->user()->loadMissing('teacher');

        if ($user->hasRole('Guru') && ! $user->hasAnyRole(['Admin', 'Kepala Sekolah', 'Wali Kelas'])) {
            $query->whereIn('id', $this->teacherClassIds($request));
        } elseif ($user->hasRole('Wali Kelas') && ! $user->hasAnyRole(['Admin', 'Kepala Sekolah'])) {
            $query->where('homeroom_teacher_id', $user->teacher?->id ?? 0);
        }

        return $query;
    }

    private function visibleStudents(Request $request)
    {
        $query = Student::query();
        $classIds = $this->visibleClasses($request)->pluck('id');

        if ($request->user()->hasAnyRole(['Guru', 'Wali Kelas']) && ! $request->user()->hasAnyRole(['Admin', 'Kepala Sekolah'])) {
            $query->whereIn('school_class_id', $classIds);
        }

        return $query;
    }

    private function visibleSubjects(Request $request)
    {
        $query = Subject::query();
        $user = $request->user()->loadMissing('teacher');

        if ($user->hasRole('Guru') && ! $user->hasAnyRole(['Admin', 'Kepala Sekolah', 'Wali Kelas'])) {
            $query->whereIn('id', $this->teacherSubjectIds($request));
        }

        return $query;
    }

    private function scopeAttendanceReport($query, Request $request): void
    {
        $user = $request->user()->loadMissing('teacher');

        if ($user->hasRole('Guru') && ! $user->hasAnyRole(['Admin', 'Kepala Sekolah', 'Wali Kelas'])) {
            $query->where('teacher_id', $user->teacher?->id ?? 0);
        } elseif ($user->hasRole('Wali Kelas') && ! $user->hasAnyRole(['Admin', 'Kepala Sekolah'])) {
            $query->whereIn('school_class_id', $this->visibleClasses($request)->pluck('id'));
        }
    }

    private function scopeGradeReport($query, Request $request): void
    {
        $user = $request->user()->loadMissing('teacher');

        if ($user->hasRole('Guru') && ! $user->hasAnyRole(['Admin', 'Kepala Sekolah', 'Wali Kelas'])) {
            $query->where('teacher_id', $user->teacher?->id ?? 0);
        } elseif ($user->hasRole('Wali Kelas') && ! $user->hasAnyRole(['Admin', 'Kepala Sekolah'])) {
            $query->whereIn('school_class_id', $this->visibleClasses($request)->pluck('id'));
        }
    }

    private function scopeReportCardReport($query, Request $request): void
    {
        if ($request->user()->hasRole('Wali Kelas') && ! $request->user()->hasAnyRole(['Admin', 'Kepala Sekolah'])) {
            $query->whereIn('school_class_id', $this->visibleClasses($request)->pluck('id'));
        }
    }

    private function teacherClassIds(Request $request)
    {
        $teacherId = $request->user()?->teacher?->id;

        return $teacherId
            ? \App\Models\Schedule::where('teacher_id', $teacherId)->pluck('school_class_id')
            : collect();
    }

    private function teacherSubjectIds(Request $request)
    {
        $teacherId = $request->user()?->teacher?->id;

        return $teacherId
            ? \App\Models\Schedule::where('teacher_id', $teacherId)->pluck('subject_id')
            : collect();
    }
}
