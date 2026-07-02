@extends('layouts.app')

@section('content')
<div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
    <div>
        <h1 class="text-2xl font-semibold">{{ $title }}</h1>
        <p class="text-sm text-slate-500">Pilih jadwal, lalu simpan absensi seluruh siswa dalam satu halaman.</p>
    </div>
    <a href="{{ route('attendances.index') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium hover:bg-slate-100">Kembali</a>
</div>

@if ($errors->any())
    <div class="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
@endif

<form method="GET" action="{{ route('attendances.bulk.create') }}" class="mb-5 grid gap-3 rounded-lg border border-slate-200 bg-white p-4 shadow-sm md:grid-cols-[1fr_220px_auto]">
    <label class="block">
        <span class="text-sm font-medium text-slate-700">Jadwal</span>
        <select name="schedule_id" required class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
            <option value="">Pilih jadwal</option>
            @foreach ($schedules as $schedule)
                <option value="{{ $schedule->id }}" @selected($selectedSchedule?->id === $schedule->id)>
                    {{ $schedule->day }} {{ $schedule->time_range }} - {{ $schedule->schoolClass->name }} - {{ $schedule->subject->name }}
                </option>
            @endforeach
        </select>
    </label>
    <label class="block">
        <span class="text-sm font-medium text-slate-700">Tanggal</span>
        <input type="date" name="date" value="{{ $date }}" required class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
    </label>
    <div class="flex items-end">
        <button class="w-full rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">Tampilkan</button>
    </div>
</form>

@if ($selectedSchedule)
    <form method="POST" action="{{ route('attendances.bulk.store') }}" class="rounded-lg border border-slate-200 bg-white shadow-sm">
        @csrf
        <input type="hidden" name="date" value="{{ $date }}">
        <input type="hidden" name="schedule_id" value="{{ $selectedSchedule->id }}">

        <div class="border-b border-slate-200 p-5">
            <div class="text-sm text-slate-500">Jadwal terpilih</div>
            <div class="mt-1 font-semibold">
                {{ $selectedSchedule->schoolClass->name }} - {{ $selectedSchedule->subject->name }}
            </div>
            <div class="text-sm text-slate-500">
                {{ $selectedSchedule->day }} {{ $selectedSchedule->time_range }} oleh {{ $selectedSchedule->teacher->name }}
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-100 text-left text-xs font-semibold uppercase text-slate-600">
                <tr>
                    <th class="px-4 py-3">NIS</th>
                    <th class="px-4 py-3">Nama Siswa</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">Catatan</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                @forelse ($selectedSchedule->schoolClass->students as $index => $student)
                    @php
                        $existing = $existingAttendances->get($student->id);
                        $selectedStatus = old("attendances.$index.status", $existing?->status ?? 'hadir');
                    @endphp
                    <tr>
                        <td class="px-4 py-3">{{ $student->nis }}</td>
                        <td class="px-4 py-3 font-medium">{{ $student->name }}</td>
                        <td class="px-4 py-3">
                            <input type="hidden" name="attendances[{{ $index }}][student_id]" value="{{ $student->id }}">
                            <select name="attendances[{{ $index }}][status]" class="w-full min-w-36 rounded-md border border-slate-300 px-3 py-2 text-sm">
                                @foreach ($statuses as $value => $label)
                                    <option value="{{ $value }}" @selected($selectedStatus === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td class="px-4 py-3">
                            <input name="attendances[{{ $index }}][note]" value="{{ old("attendances.$index.note", $existing?->note) }}" class="w-full min-w-56 rounded-md border border-slate-300 px-3 py-2 text-sm" placeholder="Opsional">
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-8 text-center text-slate-500">Kelas ini belum memiliki siswa.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="flex justify-end border-t border-slate-200 p-4">
            <button class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700" @disabled($selectedSchedule->schoolClass->students->isEmpty())>
                Simpan Absensi
            </button>
        </div>
    </form>
@endif
@endsection
