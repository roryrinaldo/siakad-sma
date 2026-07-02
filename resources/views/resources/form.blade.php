@extends('layouts.app')

@section('content')
@php
    $isEdit = $item->exists;
    $action = $isEdit ? route($route.'.update', $item) : route($route.'.store');
@endphp
<div class="mb-5">
    <h1 class="text-2xl font-semibold">{{ $title }}</h1>
    <p class="text-sm text-slate-500">Isi data dengan lengkap dan benar.</p>
</div>

@if ($errors->any())
    <div class="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
        {{ $errors->first() }}
    </div>
@endif

<form method="POST" action="{{ $action }}" class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif

    <div class="grid gap-4 md:grid-cols-2">
        @foreach ($fields as $field)
            @php
                $name = $field['name'];
                $type = $field['type'] ?? 'text';
                $value = old($name, $field['value'] ?? data_get($item, $name));
                if ($value instanceof \Carbon\CarbonInterface) {
                    $value = str_contains($type, 'datetime') ? $value->format('Y-m-d\TH:i') : $value->format('Y-m-d');
                }
                $selectedValues = collect(old($name, []));
                if ($type === 'multi_select' && $selectedValues->isEmpty()) {
                    $relation = match ($name) {
                        'subject_ids' => 'subjects',
                        'teacher_ids' => 'teachers',
                        'role_names' => 'roles',
                        'child_ids' => 'children',
                        default => null,
                    };
                    $selectedValues = $relation && $item->relationLoaded($relation)
                        ? ($name === 'role_names' ? $item->{$relation}->pluck('name') : $item->{$relation}->pluck('id'))
                        : collect();
                }
            @endphp
            <label class="{{ $type === 'textarea' ? 'md:col-span-2' : '' }} block">
                <span class="text-sm font-medium text-slate-700">{{ $field['label'] }}</span>
                @if ($type === 'textarea')
                    <textarea name="{{ $name }}" rows="4" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100">{{ $value }}</textarea>
                @elseif ($type === 'select')
                    <select name="{{ $name }}" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100">
                        <option value="">Pilih</option>
                        @foreach ($field['options'] as $key => $label)
                            <option value="{{ $key }}" @selected((string) $value === (string) $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                @elseif ($type === 'multi_select')
                    <select name="{{ $name }}[]" multiple class="mt-1 h-32 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100">
                        @foreach ($field['options'] as $key => $label)
                            <option value="{{ $key }}" @selected($selectedValues->contains($key))>{{ $label }}</option>
                        @endforeach
                    </select>
                @elseif ($type === 'checkbox')
                    <input type="hidden" name="{{ $name }}" value="0">
                    <label class="mt-2 flex items-center gap-2 text-sm text-slate-600">
                        <input type="checkbox" name="{{ $name }}" value="1" @checked((bool) $value) class="rounded border-slate-300">
                        Ya
                    </label>
                @else
                    <input type="{{ $type }}" step="{{ $field['step'] ?? '' }}" name="{{ $name }}" value="{{ $value }}" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100">
                @endif
                @error($name) <span class="mt-1 block text-sm text-red-600">{{ $message }}</span> @enderror
            </label>
        @endforeach
    </div>
    <div class="mt-6 flex gap-2">
        <button class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Simpan</button>
        <a href="{{ route($route.'.index') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium hover:bg-slate-100">Batal</a>
    </div>
</form>
@endsection
