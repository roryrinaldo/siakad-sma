<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\SchoolClass;
use App\Models\Semester;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class ClassRecapController extends Controller
{
    public function index(Request $request): View
    {
        return view('class-recaps.index', $this->recapData($request) + [
            'title' => 'Rekap Akademik Kelas',
        ]);
    }

    public function export(Request $request, string $format): Response
    {
        $data = $this->recapData($request);
        $headers = ['NIS', 'Siswa', 'Mapel Dinilai', 'Rata-rata', 'Predikat', 'Hadir', 'Izin', 'Sakit', 'Alpha', 'Terlambat', 'Raport'];
        $rows = $data['rows']->map(fn (array $row) => [
            $row['student']->nis,
            $row['student']->name,
            $row['subject_count'],
            $row['average_score'] !== null ? number_format($row['average_score'], 2) : '-',
            $row['predicate'],
            $row['attendance']['hadir'],
            $row['attendance']['izin'],
            $row['attendance']['sakit'],
            $row['attendance']['alpha'],
            $row['attendance']['terlambat'],
            $row['report_card'] ? ($row['report_card']->is_validated ? 'Tervalidasi' : 'Draft') : 'Belum ada',
        ])->all();
        $filename = 'rekap-kelas-'.str($data['selectedClass']?->name ?: 'kelas')->slug();

        if ($format === 'pdf') {
            return Pdf::loadView('reports.table-pdf', [
                'title' => 'Rekap Akademik Kelas '.$data['selectedClass']?->name,
                'headers' => $headers,
                'rows' => $rows,
            ])->setPaper('a4', 'landscape')->download($filename.'.pdf');
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

    private function recapData(Request $request): array
    {
        $classes = $this->availableClasses($request)
            ->with('homeroomTeacher')
            ->orderBy('name')
            ->get();
        $academicYears = AcademicYear::orderByDesc('year')->get();
        $semesters = Semester::with('academicYear')->orderByDesc('id')->get();
        $activeAcademicYear = AcademicYear::where('is_active', true)->first();
        $activeSemester = Semester::where('is_active', true)->first();

        $selectedClass = $classes->firstWhere('id', (int) $request->input('school_class_id'))
            ?? $classes->first();
        $selectedAcademicYear = $academicYears->firstWhere('id', (int) $request->input('academic_year_id'))
            ?? $activeAcademicYear
            ?? $academicYears->first();
        $selectedSemester = $semesters->firstWhere('id', (int) $request->input('semester_id'))
            ?? $activeSemester
            ?? $semesters->first();

        $rows = collect();
        if ($selectedClass && $selectedAcademicYear && $selectedSemester) {
            $students = $selectedClass->students()
                ->with([
                    'grades' => fn ($query) => $query
                        ->with('subject')
                        ->where('academic_year_id', $selectedAcademicYear->id)
                        ->where('semester_id', $selectedSemester->id),
                    'reportCards' => fn ($query) => $query
                        ->where('academic_year_id', $selectedAcademicYear->id)
                        ->where('semester_id', $selectedSemester->id),
                ])
                ->orderBy('name')
                ->get();

            $attendanceQuery = Attendance::whereIn('student_id', $students->pluck('id'));
            if ($selectedSemester->starts_on && $selectedSemester->ends_on) {
                $attendanceQuery->whereBetween('date', [
                    $selectedSemester->starts_on->toDateString(),
                    $selectedSemester->ends_on->toDateString(),
                ]);
            }

            $attendanceCounts = $attendanceQuery
                ->selectRaw('student_id, status, count(*) as total')
                ->groupBy('student_id', 'status')
                ->get()
                ->groupBy('student_id');

            $rows = $students->map(function ($student) use ($attendanceCounts) {
                $counts = $attendanceCounts->get($student->id, collect())->pluck('total', 'status');
                $average = $student->grades->avg('final_score');
                $reportCard = $student->reportCards->first();

                return [
                    'student' => $student,
                    'subject_count' => $student->grades->count(),
                    'average_score' => $average ? round((float) $average, 2) : null,
                    'predicate' => $this->predicateFor($average),
                    'attendance' => [
                        'hadir' => $counts['hadir'] ?? 0,
                        'izin' => $counts['izin'] ?? 0,
                        'sakit' => $counts['sakit'] ?? 0,
                        'alpha' => $counts['alpha'] ?? 0,
                        'terlambat' => $counts['terlambat'] ?? 0,
                    ],
                    'report_card' => $reportCard,
                ];
            });
        }

        return [
            'classes' => $classes,
            'academicYears' => $academicYears,
            'semesters' => $semesters,
            'selectedClass' => $selectedClass,
            'selectedAcademicYear' => $selectedAcademicYear,
            'selectedSemester' => $selectedSemester,
            'rows' => $rows,
        ];
    }

    private function availableClasses(Request $request)
    {
        $user = $request->user()->loadMissing('teacher');
        $query = SchoolClass::query();

        if ($user->hasRole('Wali Kelas') && ! $user->hasAnyRole(['Admin', 'Kepala Sekolah'])) {
            $query->where('homeroom_teacher_id', $user->teacher?->id ?? 0);
        }

        return $query;
    }

    private function predicateFor(?float $score): string
    {
        return match (true) {
            $score === null => '-',
            $score >= 90 => 'A',
            $score >= 80 => 'B',
            $score >= 70 => 'C',
            default => 'D',
        };
    }
}
