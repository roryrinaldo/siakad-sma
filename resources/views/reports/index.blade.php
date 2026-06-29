@extends('layouts.app')

@section('content')
@php
    $reports = [
        ['label' => 'Laporan Data Siswa', 'route' => 'reports.students'],
        ['label' => 'Laporan Data Guru', 'route' => 'reports.teachers'],
        ['label' => 'Laporan Absensi Siswa', 'route' => 'reports.attendances'],
        ['label' => 'Laporan Nilai Siswa', 'route' => 'reports.grades'],
        ['label' => 'Laporan Raport Siswa', 'route' => 'reports.report-cards'],
    ];
@endphp
<div class="mb-5">
    <h1 class="text-2xl font-semibold">Laporan Akademik</h1>
    <p class="text-sm text-slate-500">Unduh laporan dasar dalam format PDF atau CSV yang bisa dibuka di Excel.</p>
</div>

<div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
    @foreach ($reports as $report)
        <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="font-semibold">{{ $report['label'] }}</h2>
            <div class="mt-4 flex gap-2">
                <a href="{{ route($report['route'], 'pdf') }}" class="rounded-md bg-red-600 px-3 py-2 text-sm font-medium text-white hover:bg-red-700">PDF</a>
                <a href="{{ route($report['route'], 'csv') }}" class="rounded-md bg-emerald-600 px-3 py-2 text-sm font-medium text-white hover:bg-emerald-700">Excel/CSV</a>
            </div>
        </div>
    @endforeach
</div>
@endsection
