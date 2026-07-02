@extends('layouts.app')

@section('content')
<div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
    <div>
        <h1 class="text-2xl font-semibold">{{ $title }}</h1>
        <p class="text-sm text-slate-500">Isi nilai seluruh siswa berdasarkan jadwal kelas dan mata pelajaran.</p>
    </div>
    <a href="{{ route('grades.index') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium hover:bg-slate-100">Kembali</a>
</div>

@if ($errors->any())
    <div class="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
@endif

<form method="GET" action="{{ route('grades.bulk.create') }}" class="mb-5 grid gap-3 rounded-lg border border-slate-200 bg-white p-4 shadow-sm md:grid-cols-[1fr_auto]">
    <label class="block">
        <span class="text-sm font-medium text-slate-700">Jadwal</span>
        <select name="schedule_id" required class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
            <option value="">Pilih jadwal</option>
            @foreach ($schedules as $schedule)
                <option value="{{ $schedule->id }}" @selected($selectedSchedule?->id === $schedule->id)>
                    {{ $schedule->schoolClass->name }} - {{ $schedule->subject->name }} - {{ $schedule->teacher->name }} - {{ $schedule->academicYear->year }} {{ $schedule->semester->name }}
                </option>
            @endforeach
        </select>
    </label>
    <div class="flex items-end">
        <button class="w-full rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">Tampilkan</button>
    </div>
</form>

@if ($selectedSchedule)
    <form method="POST" action="{{ route('grades.bulk.store') }}" class="rounded-lg border border-slate-200 bg-white shadow-sm">
        @csrf
        <input type="hidden" name="schedule_id" value="{{ $selectedSchedule->id }}">

        <div class="border-b border-slate-200 p-5">
            <div class="text-sm text-slate-500">Jadwal terpilih</div>
            <div class="mt-1 font-semibold">
                {{ $selectedSchedule->schoolClass->name }} - {{ $selectedSchedule->subject->name }}
            </div>
            <div class="text-sm text-slate-500">
                {{ $selectedSchedule->academicYear->year }} {{ $selectedSchedule->semester->name }} oleh {{ $selectedSchedule->teacher->name }}
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-100 text-left text-xs font-semibold uppercase text-slate-600">
                <tr>
                    <th class="px-4 py-3">Siswa</th>
                    <th class="px-4 py-3">Tugas</th>
                    <th class="px-4 py-3">UH</th>
                    <th class="px-4 py-3">UTS</th>
                    <th class="px-4 py-3">UAS</th>
                    <th class="px-4 py-3">Praktik</th>
                    <th class="px-4 py-3">Sikap</th>
                    <th class="px-4 py-3">Catatan</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                @forelse ($selectedSchedule->schoolClass->students as $index => $student)
                    @php
                        $existing = $existingGrades->get($student->id);
                    @endphp
                    <tr>
                        <td class="px-4 py-3">
                            <input type="hidden" name="grades[{{ $index }}][student_id]" value="{{ $student->id }}">
                            <div class="font-medium">{{ $student->name }}</div>
                            <div class="text-xs text-slate-500">{{ $student->nis }}</div>
                        </td>
                        @foreach ([
                            'assignment_score' => 'Tugas',
                            'daily_test_score' => 'UH',
                            'midterm_score' => 'UTS',
                            'final_exam_score' => 'UAS',
                            'practice_score' => 'Praktik',
                        ] as $field => $label)
                            <td class="px-4 py-3">
                                <input type="number" min="0" max="100" step="0.01" required
                                       name="grades[{{ $index }}][{{ $field }}]"
                                       value="{{ old("grades.$index.$field", data_get($existing, $field, 0)) }}"
                                       aria-label="{{ $label }} {{ $student->name }}"
                                       class="w-24 rounded-md border border-slate-300 px-3 py-2 text-sm">
                            </td>
                        @endforeach
                        <td class="px-4 py-3">
                            <input name="grades[{{ $index }}][attitude_score]"
                                   value="{{ old("grades.$index.attitude_score", $existing?->attitude_score) }}"
                                   class="w-28 rounded-md border border-slate-300 px-3 py-2 text-sm"
                                   placeholder="Baik">
                        </td>
                        <td class="px-4 py-3">
                            <input name="grades[{{ $index }}][note]"
                                   value="{{ old("grades.$index.note", $existing?->note) }}"
                                   class="w-56 rounded-md border border-slate-300 px-3 py-2 text-sm"
                                   placeholder="Opsional">
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-8 text-center text-slate-500">Kelas ini belum memiliki siswa.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="flex justify-end border-t border-slate-200 p-4">
            <button class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700" @disabled($selectedSchedule->schoolClass->students->isEmpty())>
                Simpan Nilai
            </button>
        </div>
    </form>
@endif
@endsection
