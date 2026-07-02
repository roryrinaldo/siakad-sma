@extends('layouts.app')

@section('content')
<div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
    <div>
        <h1 class="text-2xl font-semibold">{{ $title }}</h1>
        <p class="text-sm text-slate-500">Unggah file CSV dengan baris pertama sebagai header.</p>
    </div>
    <a href="{{ route($backRoute) }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium hover:bg-slate-100">Kembali</a>
</div>

@if ($errors->any())
    <div class="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
@endif

<div class="grid gap-5 lg:grid-cols-[1fr_360px]">
    <form method="POST" action="{{ route($route) }}" enctype="multipart/form-data" class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
        @csrf
        <label class="block">
            <span class="text-sm font-medium text-slate-700">File CSV</span>
            <input type="file" name="file" accept=".csv,text/csv,text/plain" required class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
        </label>
        <div class="mt-6 flex gap-2">
            <button class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Import</button>
            <a href="{{ route($backRoute) }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium hover:bg-slate-100">Batal</a>
        </div>
    </form>

    <aside class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="font-semibold">Format Header</h2>
        <code class="mt-3 block whitespace-pre-wrap rounded-md bg-slate-100 p-3 text-xs">{{ implode(',', $headers) }}</code>
        @if (! empty($notes))
            <div class="mt-4 space-y-2 text-sm text-slate-600">
                @foreach ($notes as $note)
                    <p>{{ $note }}</p>
                @endforeach
            </div>
        @endif
    </aside>
</div>
@endsection
