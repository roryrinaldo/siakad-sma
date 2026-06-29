@extends('layouts.app')

@section('content')
<div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
    <div>
        <h1 class="text-2xl font-semibold">Raport {{ $reportCard->student->name }}</h1>
        <p class="text-sm text-slate-500">{{ $reportCard->schoolClass->name }} - {{ $reportCard->academicYear->year }} {{ $reportCard->semester->name }}</p>
    </div>
    <div class="flex gap-2">
        @if (! $reportCard->is_validated && auth()->user()->hasAnyRole(['Admin', 'Wali Kelas']))
            <form method="POST" action="{{ route('report-cards.validate', $reportCard) }}">
                @csrf
                @method('PATCH')
                <button class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white">Validasi</button>
            </form>
        @endif
        <a href="{{ route('report-cards.index') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium">Kembali</a>
    </div>
</div>

<section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
    <h2 class="mb-4 text-lg font-semibold">Nilai Mata Pelajaran</h2>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-100 text-left text-xs font-semibold uppercase text-slate-600">
            <tr>
                <th class="px-4 py-3">Mata Pelajaran</th>
                <th class="px-4 py-3">Nilai Akhir</th>
                <th class="px-4 py-3">Predikat</th>
                <th class="px-4 py-3">Catatan</th>
            </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
            @forelse ($grades as $grade)
                <tr>
                    <td class="px-4 py-3">{{ $grade->subject->name }}</td>
                    <td class="px-4 py-3">{{ $grade->final_score }}</td>
                    <td class="px-4 py-3">{{ $grade->predicate }}</td>
                    <td class="px-4 py-3">{{ $grade->note ?: '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="px-4 py-6 text-center text-slate-500">Nilai belum tersedia.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</section>

<div class="mt-5 grid gap-4 lg:grid-cols-2">
    <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="text-lg font-semibold">Rekap Absensi</h2>
        <div class="mt-4 grid grid-cols-2 gap-3">
            @foreach (['hadir', 'izin', 'sakit', 'alpha', 'terlambat'] as $status)
                <div class="rounded-md bg-slate-100 p-3">
                    <div class="text-sm text-slate-500">{{ ucfirst($status) }}</div>
                    <div class="text-xl font-semibold">{{ $attendanceSummary[$status] ?? 0 }}</div>
                </div>
            @endforeach
        </div>
    </section>
    <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="text-lg font-semibold">Catatan Wali Kelas</h2>
        <p class="mt-3 whitespace-pre-line text-slate-700">{{ $reportCard->homeroom_note ?: '-' }}</p>
        <div class="mt-4 text-sm font-medium {{ $reportCard->is_validated ? 'text-emerald-700' : 'text-amber-700' }}">
            {{ $reportCard->is_validated ? 'Raport tervalidasi' : 'Menunggu validasi wali kelas' }}
        </div>
    </section>
</div>
@endsection
