<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <style>
        body { color: #0f172a; font-family: DejaVu Sans, sans-serif; font-size: 12px; line-height: 1.45; }
        h1 { font-size: 20px; margin: 0 0 4px; text-align: center; text-transform: uppercase; }
        h2 { font-size: 14px; margin: 18px 0 8px; }
        .subtitle { margin-bottom: 20px; text-align: center; }
        .meta { margin-bottom: 16px; width: 100%; }
        .meta td { padding: 3px 0; vertical-align: top; }
        .meta td:first-child { color: #475569; width: 120px; }
        table.data { border-collapse: collapse; width: 100%; }
        table.data th, table.data td { border: 1px solid #cbd5e1; padding: 7px 8px; text-align: left; }
        table.data th { background: #e2e8f0; font-weight: bold; }
        .summary { margin-top: 6px; width: 100%; }
        .summary td { border: 1px solid #cbd5e1; padding: 7px 8px; }
        .note { border: 1px solid #cbd5e1; min-height: 70px; padding: 10px; }
        .footer { margin-top: 28px; width: 100%; }
        .footer td { text-align: center; vertical-align: top; width: 50%; }
        .signature { margin-top: 56px; font-weight: bold; }
        .status { margin-top: 8px; }
    </style>
</head>
<body>
<h1>Raport Digital SMA</h1>
<div class="subtitle">{{ $reportCard->academicYear->year }} - Semester {{ $reportCard->semester->name }}</div>

<table class="meta">
    <tr>
        <td>Nama Siswa</td>
        <td>: {{ $reportCard->student->name }}</td>
        <td>Kelas</td>
        <td>: {{ $reportCard->schoolClass->name }}</td>
    </tr>
    <tr>
        <td>NIS</td>
        <td>: {{ $reportCard->student->nis }}</td>
        <td>NISN</td>
        <td>: {{ $reportCard->student->nisn ?: '-' }}</td>
    </tr>
</table>

<h2>Nilai Mata Pelajaran</h2>
<table class="data">
    <thead>
    <tr>
        <th style="width: 42%">Mata Pelajaran</th>
        <th style="width: 18%">Nilai Akhir</th>
        <th style="width: 15%">Predikat</th>
        <th>Catatan</th>
    </tr>
    </thead>
    <tbody>
    @forelse ($grades as $grade)
        <tr>
            <td>{{ $grade->subject->name }}</td>
            <td>{{ number_format((float) $grade->final_score, 2) }}</td>
            <td>{{ $grade->predicate }}</td>
            <td>{{ $grade->note ?: '-' }}</td>
        </tr>
    @empty
        <tr>
            <td colspan="4" style="text-align: center;">Nilai belum tersedia.</td>
        </tr>
    @endforelse
    </tbody>
</table>

<h2>Rekap Absensi</h2>
<table class="summary">
    <tr>
        @foreach (['hadir' => 'Hadir', 'izin' => 'Izin', 'sakit' => 'Sakit', 'alpha' => 'Alpha', 'terlambat' => 'Terlambat'] as $status => $label)
            <td><strong>{{ $label }}</strong><br>{{ $attendanceSummary[$status] ?? 0 }}</td>
        @endforeach
    </tr>
</table>

<h2>Catatan Wali Kelas</h2>
<div class="note">{{ $reportCard->homeroom_note ?: '-' }}</div>
<div class="status">Status: {{ $reportCard->is_validated ? 'Tervalidasi' : 'Belum tervalidasi' }}</div>

<table class="footer">
    <tr>
        <td>
            Orang Tua/Wali
            <div class="signature">...........................</div>
        </td>
        <td>
            Wali Kelas
            <div class="signature">{{ $reportCard->schoolClass->homeroomTeacher?->name ?: '...........................' }}</div>
        </td>
    </tr>
</table>
</body>
</html>
