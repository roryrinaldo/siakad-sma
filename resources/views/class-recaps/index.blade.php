@extends('layouts.app')

@section('content')
<div class="mb-5">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-semibold">{{ $title }}</h1>
            <p class="text-sm text-slate-500">Pantau nilai, absensi, dan status raport siswa dalam satu kelas.</p>
        </div>
        @php
            $query = request()->query();
            $exportUrl = fn (string $format) => route('class-recaps.export', $format).($query ? '?'.http_build_query($query) : '');
        @endphp
        <div class="flex gap-2">
            <a href="{{ $exportUrl('pdf') }}" class="rounded-md bg-red-600 px-3 py-2 text-sm font-medium text-white hover:bg-red-700">PDF</a>
            <a href="{{ $exportUrl('csv') }}" class="rounded-md bg-emerald-600 px-3 py-2 text-sm font-medium text-white hover:bg-emerald-700">CSV</a>
        </div>
    </div>
</div>

<form method="GET" class="mb-5 grid gap-3 rounded-lg border border-slate-200 bg-white p-4 shadow-sm md:grid-cols-4">
    <label class="block">
        <span class="text-sm font-medium text-slate-700">Kelas</span>
        <select name="school_class_id" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
            @foreach ($classes as $class)
                <option value="{{ $class->id }}" @selected($selectedClass?->id === $class->id)>
                    {{ $class->name }}
                </option>
            @endforeach
        </select>
    </label>
    <label class="block">
        <span class="text-sm font-medium text-slate-700">Tahun Ajaran</span>
        <select name="academic_year_id" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
            @foreach ($academicYears as $academicYear)
                <option value="{{ $academicYear->id }}" @selected($selectedAcademicYear?->id === $academicYear->id)>
                    {{ $academicYear->year }}
                </option>
            @endforeach
        </select>
    </label>
    <label class="block">
        <span class="text-sm font-medium text-slate-700">Semester</span>
        <select name="semester_id" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
            @foreach ($semesters as $semester)
                <option value="{{ $semester->id }}" @selected($selectedSemester?->id === $semester->id)>
                    {{ $semester->name }} {{ $semester->academicYear?->year ? '- '.$semester->academicYear->year : '' }}
                </option>
            @endforeach
        </select>
    </label>
    <div class="flex items-end">
        <button class="w-full rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">Tampilkan</button>
    </div>
</form>

@if ($selectedClass)
    <div class="mb-4 grid gap-4 md:grid-cols-4">
        <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <div class="text-sm text-slate-500">Kelas</div>
            <div class="mt-1 font-semibold">{{ $selectedClass->name }}</div>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <div class="text-sm text-slate-500">Wali Kelas</div>
            <div class="mt-1 font-semibold">{{ $selectedClass->homeroomTeacher?->name ?: '-' }}</div>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <div class="text-sm text-slate-500">Tahun Ajaran</div>
            <div class="mt-1 font-semibold">{{ $selectedAcademicYear?->year ?: '-' }}</div>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <div class="text-sm text-slate-500">Semester</div>
            <div class="mt-1 font-semibold">{{ $selectedSemester?->name ?: '-' }}</div>
        </div>
    </div>
@endif

<div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-100 text-left text-xs font-semibold uppercase text-slate-600">
            <tr>
                <th class="px-4 py-3">Siswa</th>
                <th class="px-4 py-3">Mapel Dinilai</th>
                <th class="px-4 py-3">Rata-rata</th>
                <th class="px-4 py-3">Predikat</th>
                <th class="px-4 py-3">Hadir</th>
                <th class="px-4 py-3">Izin</th>
                <th class="px-4 py-3">Sakit</th>
                <th class="px-4 py-3">Alpha</th>
                <th class="px-4 py-3">Terlambat</th>
                <th class="px-4 py-3">Raport</th>
            </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
            @forelse ($rows as $row)
                <tr class="hover:bg-slate-50">
                    <td class="px-4 py-3">
                        <div class="font-medium">{{ $row['student']->name }}</div>
                        <div class="text-xs text-slate-500">{{ $row['student']->nis }}</div>
                    </td>
                    <td class="px-4 py-3">{{ $row['subject_count'] }}</td>
                    <td class="px-4 py-3">{{ $row['average_score'] !== null ? number_format($row['average_score'], 2) : '-' }}</td>
                    <td class="px-4 py-3">
                        <span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-700">{{ $row['predicate'] }}</span>
                    </td>
                    @foreach (['hadir', 'izin', 'sakit', 'alpha', 'terlambat'] as $status)
                        <td class="px-4 py-3">{{ $row['attendance'][$status] }}</td>
                    @endforeach
                    <td class="px-4 py-3">
                        @if ($row['report_card'])
                            <a href="{{ route('report-cards.show', $row['report_card']) }}" class="rounded-md border border-slate-300 px-2 py-1 text-xs font-medium hover:bg-slate-100">
                                {{ $row['report_card']->is_validated ? 'Tervalidasi' : 'Draft' }}
                            </a>
                        @else
                            <span class="text-slate-500">Belum ada</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="10" class="px-4 py-8 text-center text-slate-500">Tidak ada data siswa untuk filter ini.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
