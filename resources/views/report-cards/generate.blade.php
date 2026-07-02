@extends('layouts.app')

@section('content')
<div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
    <div>
        <h1 class="text-2xl font-semibold">{{ $title }}</h1>
        <p class="text-sm text-slate-500">Buat atau perbarui raport seluruh siswa dalam satu kelas.</p>
    </div>
    <a href="{{ route('report-cards.index') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium hover:bg-slate-100">Kembali</a>
</div>

@if ($errors->any())
    <div class="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
@endif

<form method="POST" action="{{ route('report-cards.generate.store') }}" class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
    @csrf
    <div class="grid gap-4 md:grid-cols-2">
        <label class="block">
            <span class="text-sm font-medium text-slate-700">Kelas</span>
            <select name="school_class_id" required class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                <option value="">Pilih kelas</option>
                @foreach ($classes as $class)
                    <option value="{{ $class->id }}" @selected(old('school_class_id') == $class->id)>
                        {{ $class->name }} ({{ $class->students_count }} siswa)
                    </option>
                @endforeach
            </select>
        </label>
        <label class="block">
            <span class="text-sm font-medium text-slate-700">Tahun Ajaran</span>
            <select name="academic_year_id" required class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                <option value="">Pilih tahun ajaran</option>
                @foreach ($academicYears as $id => $label)
                    <option value="{{ $id }}" @selected((string) old('academic_year_id', $activeAcademicYear?->id) === (string) $id)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <label class="block">
            <span class="text-sm font-medium text-slate-700">Semester</span>
            <select name="semester_id" required class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                <option value="">Pilih semester</option>
                @foreach ($semesters as $id => $label)
                    <option value="{{ $id }}" @selected((string) old('semester_id', $activeSemester?->id) === (string) $id)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <label class="block">
            <span class="text-sm font-medium text-slate-700">Validasi Wali Kelas</span>
            <input type="hidden" name="is_validated" value="0">
            <label class="mt-3 flex items-center gap-2 text-sm text-slate-600">
                <input type="checkbox" name="is_validated" value="1" @checked(old('is_validated')) class="rounded border-slate-300">
                Tandai langsung tervalidasi
            </label>
        </label>
        <label class="block md:col-span-2">
            <span class="text-sm font-medium text-slate-700">Catatan Wali Kelas</span>
            <textarea name="homeroom_note" rows="4" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm" placeholder="Opsional">{{ old('homeroom_note') }}</textarea>
        </label>
    </div>

    <div class="mt-6 flex gap-2">
        <button class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Generate Raport</button>
        <a href="{{ route('report-cards.index') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium hover:bg-slate-100">Batal</a>
    </div>
</form>
@endsection
