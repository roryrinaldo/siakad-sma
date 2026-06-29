<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Grade;
use App\Models\ReportCard;
use App\Models\Student;
use App\Models\Teacher;
use Illuminate\Http\Response;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function index(): View
    {
        return view('reports.index');
    }

    public function students(string $format): Response
    {
        $rows = Student::with('schoolClass')->orderBy('name')->get();

        return $this->export($format, 'laporan-siswa', ['NIS', 'Nama', 'Kelas', 'Status'], $rows->map(fn ($student) => [
            $student->nis,
            $student->name,
            $student->schoolClass?->name,
            $student->status,
        ])->all());
    }

    public function teachers(string $format): Response
    {
        $rows = Teacher::orderBy('name')->get();

        return $this->export($format, 'laporan-guru', ['NIP', 'Nama', 'Email', 'Status'], $rows->map(fn ($teacher) => [
            $teacher->nip,
            $teacher->name,
            $teacher->email,
            $teacher->is_active ? 'Aktif' : 'Nonaktif',
        ])->all());
    }

    public function attendances(string $format): Response
    {
        $rows = Attendance::with(['student', 'schoolClass'])->latest('date')->get();

        return $this->export($format, 'laporan-absensi', ['Tanggal', 'Siswa', 'Kelas', 'Status'], $rows->map(fn ($attendance) => [
            optional($attendance->date)->format('Y-m-d'),
            $attendance->student?->name,
            $attendance->schoolClass?->name,
            $attendance->status,
        ])->all());
    }

    public function grades(string $format): Response
    {
        $rows = Grade::with(['student', 'schoolClass', 'subject'])->orderByDesc('final_score')->get();

        return $this->export($format, 'laporan-nilai', ['Siswa', 'Kelas', 'Mapel', 'Nilai Akhir', 'Predikat'], $rows->map(fn ($grade) => [
            $grade->student?->name,
            $grade->schoolClass?->name,
            $grade->subject?->name,
            $grade->final_score,
            $grade->predicate,
        ])->all());
    }

    public function reportCards(string $format): Response
    {
        $rows = ReportCard::with(['student', 'schoolClass', 'academicYear', 'semester'])->get();

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
}
