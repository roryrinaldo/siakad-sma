@extends('layouts.app')

@section('content')
@php
    $query = request()->query();
    $buildUrl = fn (string $route, string $format) => route($route, $format).($query ? '?'.http_build_query($query) : '');
@endphp
<div class="mb-5">
    <h1 class="text-2xl font-semibold">Laporan Akademik</h1>
    <p class="text-sm text-slate-500">Filter laporan, lalu unduh dalam format PDF atau CSV kompatibel Excel.</p>
</div>

<form method="GET" class="mb-5 grid gap-3 rounded-lg border border-slate-200 bg-white p-4 shadow-sm md:grid-cols-3 xl:grid-cols-4">
    <label class="block">
        <span class="text-sm font-medium text-slate-700">Kelas</span>
        <select name="school_class_id" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
            <option value="">Semua</option>
            @foreach ($classes as $id => $label)
                <option value="{{ $id }}" @selected(request('school_class_id') == $id)>{{ $label }}</option>
            @endforeach
        </select>
    </label>
    <label class="block">
        <span class="text-sm font-medium text-slate-700">Siswa</span>
        <select name="student_id" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
            <option value="">Semua</option>
            @foreach ($students as $id => $label)
                <option value="{{ $id }}" @selected(request('student_id') == $id)>{{ $label }}</option>
            @endforeach
        </select>
    </label>
    <label class="block">
        <span class="text-sm font-medium text-slate-700">Mata Pelajaran</span>
        <select name="subject_id" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
            <option value="">Semua</option>
            @foreach ($subjects as $id => $label)
                <option value="{{ $id }}" @selected(request('subject_id') == $id)>{{ $label }}</option>
            @endforeach
        </select>
    </label>
    <label class="block">
        <span class="text-sm font-medium text-slate-700">Tahun Ajaran</span>
        <select name="academic_year_id" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
            <option value="">Semua</option>
            @foreach ($academicYears as $id => $label)
                <option value="{{ $id }}" @selected(request('academic_year_id') == $id)>{{ $label }}</option>
            @endforeach
        </select>
    </label>
    <label class="block">
        <span class="text-sm font-medium text-slate-700">Semester</span>
        <select name="semester_id" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
            <option value="">Semua</option>
            @foreach ($semesters as $id => $label)
                <option value="{{ $id }}" @selected(request('semester_id') == $id)>{{ $label }}</option>
            @endforeach
        </select>
    </label>
    <label class="block">
        <span class="text-sm font-medium text-slate-700">Status Siswa</span>
        <select name="status" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
            <option value="">Semua</option>
            @foreach (['aktif' => 'Aktif', 'lulus' => 'Lulus', 'pindah' => 'Pindah', 'keluar' => 'Keluar'] as $id => $label)
                <option value="{{ $id }}" @selected(request('status') === $id)>{{ $label }}</option>
            @endforeach
        </select>
    </label>
    <label class="block">
        <span class="text-sm font-medium text-slate-700">Dari Tanggal</span>
        <input type="date" name="date_from" value="{{ request('date_from') }}" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
    </label>
    <label class="block">
        <span class="text-sm font-medium text-slate-700">Sampai Tanggal</span>
        <input type="date" name="date_to" value="{{ request('date_to') }}" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
    </label>
    <div class="flex items-end gap-2">
        <button class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white">Terapkan</button>
        <a href="{{ route('reports.index') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium">Reset</a>
    </div>
</form>

<div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
    @foreach ($reports as $report)
        <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="font-semibold">{{ $report['label'] }}</h2>
            <div class="mt-4 flex gap-2">
                <a href="{{ $buildUrl($report['route'], 'pdf') }}" class="rounded-md bg-red-600 px-3 py-2 text-sm font-medium text-white hover:bg-red-700">PDF</a>
                <a href="{{ $buildUrl($report['route'], 'csv') }}" class="rounded-md bg-emerald-600 px-3 py-2 text-sm font-medium text-white hover:bg-emerald-700">CSV kompatibel Excel</a>
            </div>
        </div>
    @endforeach
</div>
@endsection
