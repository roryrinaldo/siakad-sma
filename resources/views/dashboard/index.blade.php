@extends('layouts.app')

@section('content')
<div class="mb-6 flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
    <div>
        <h1 class="text-2xl font-semibold">Dashboard {{ $role }}</h1>
        <p class="text-sm text-slate-500">Ringkasan data akademik sekolah.</p>
    </div>
</div>

<div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
    @foreach ($stats as $label => $value)
        <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <div class="text-sm text-slate-500">{{ $label }}</div>
            <div class="mt-2 text-2xl font-semibold">{{ $value ?: 0 }}</div>
        </div>
    @endforeach
</div>

<div class="mt-6 grid gap-4 xl:grid-cols-2">
    <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="text-lg font-semibold">Jadwal Hari Ini</h2>
        <div class="mt-4 divide-y divide-slate-100">
            @forelse ($schedules as $schedule)
                <div class="py-3">
                    <div class="font-medium">{{ $schedule->subject->name }} - {{ $schedule->schoolClass->name }}</div>
                    <div class="text-sm text-slate-500">{{ $schedule->time_range }} oleh {{ $schedule->teacher->name }}</div>
                </div>
            @empty
                <div class="py-3 text-sm text-slate-500">Tidak ada jadwal hari ini.</div>
            @endforelse
        </div>
    </section>
    <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="text-lg font-semibold">Pengumuman Terbaru</h2>
        <div class="mt-4 divide-y divide-slate-100">
            @forelse ($announcements as $announcement)
                <a href="{{ route('announcements.show', $announcement) }}" class="block py-3 hover:text-indigo-700">
                    <div class="font-medium">{{ $announcement->title }}</div>
                    <div class="line-clamp-1 text-sm text-slate-500">{{ $announcement->body }}</div>
                </a>
            @empty
                <div class="py-3 text-sm text-slate-500">Belum ada pengumuman.</div>
            @endforelse
        </div>
    </section>
</div>
@endsection
