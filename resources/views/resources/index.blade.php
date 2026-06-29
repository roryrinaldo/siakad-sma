@extends('layouts.app')

@section('content')
@php
    $canCreate = Route::has($route.'.create') && auth()->user()->hasAnyRole(['Admin', 'Guru', 'Wali Kelas']);
@endphp
<div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
    <div>
        <h1 class="text-2xl font-semibold">{{ $title }}</h1>
        <p class="text-sm text-slate-500">Kelola dan pantau data {{ strtolower($title) }}.</p>
    </div>
    @if ($canCreate)
        <a href="{{ route($route.'.create') }}" class="inline-flex items-center justify-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Tambah</a>
    @endif
</div>

@if (! empty($filters))
    <form method="GET" class="mb-4 grid gap-3 rounded-lg border border-slate-200 bg-white p-4 sm:grid-cols-3 lg:grid-cols-4">
        @foreach ($filters as $filter)
            <label class="block">
                <span class="text-sm font-medium text-slate-700">{{ $filter['label'] }}</span>
                @if (($filter['type'] ?? 'text') === 'select')
                    <select name="{{ $filter['name'] }}" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        <option value="">Semua</option>
                        @foreach ($filter['options'] as $key => $label)
                            <option value="{{ $key }}" @selected(request($filter['name']) == $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                @else
                    <input type="{{ $filter['type'] ?? 'text' }}" name="{{ $filter['name'] }}" value="{{ request($filter['name']) }}" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                @endif
            </label>
        @endforeach
        <div class="flex items-end gap-2">
            <button class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white">Filter</button>
            <a href="{{ route($route.'.index') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium">Reset</a>
        </div>
    </form>
@endif

<div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-100 text-left text-xs font-semibold uppercase text-slate-600">
            <tr>
                @foreach ($columns as $column)
                    <th class="px-4 py-3">{{ $column['label'] }}</th>
                @endforeach
                <th class="px-4 py-3 text-right">Aksi</th>
            </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
            @forelse ($items as $item)
                <tr class="hover:bg-slate-50">
                    @foreach ($columns as $column)
                        @php
                            $value = data_get($item, $column['key']);
                            if ($value instanceof \Carbon\CarbonInterface) {
                                $value = $value->format('Y-m-d');
                            }
                            if (($column['boolean'] ?? false) === true) {
                                $value = $value ? 'Aktif' : 'Tidak';
                            }
                        @endphp
                        <td class="px-4 py-3">
                            @if (($column['badge'] ?? false) || ($column['boolean'] ?? false))
                                <span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-700">{{ $value ?: '-' }}</span>
                            @else
                                {{ $value ?: '-' }}
                            @endif
                        </td>
                    @endforeach
                    <td class="px-4 py-3 text-right">
                        <div class="flex justify-end gap-2">
                            @if (Route::has($route.'.show'))
                                <a href="{{ route($route.'.show', $item) }}" class="rounded-md border border-slate-300 px-2 py-1 text-xs font-medium hover:bg-slate-100">Detail</a>
                            @endif
                            @if (Route::has($route.'.edit') && auth()->user()->hasAnyRole(['Admin', 'Guru', 'Wali Kelas']))
                                <a href="{{ route($route.'.edit', $item) }}" class="rounded-md border border-slate-300 px-2 py-1 text-xs font-medium hover:bg-slate-100">Edit</a>
                            @endif
                            @if (Route::has($route.'.destroy') && auth()->user()->hasAnyRole(['Admin', 'Guru', 'Wali Kelas']))
                                <form method="POST" action="{{ route($route.'.destroy', $item) }}" onsubmit="return confirm('Hapus data ini?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="rounded-md border border-red-200 px-2 py-1 text-xs font-medium text-red-700 hover:bg-red-50">Hapus</button>
                                </form>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($columns) + 1 }}" class="px-4 py-8 text-center text-slate-500">Data belum tersedia.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if (method_exists($items, 'links'))
        <div class="border-t border-slate-200 px-4 py-3">{{ $items->links() }}</div>
    @endif
</div>
@endsection
