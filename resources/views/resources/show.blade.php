@extends('layouts.app')

@section('content')
<div class="mb-5 flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-semibold">{{ $title }}</h1>
        <p class="text-sm text-slate-500">Detail data terpilih.</p>
    </div>
    <a href="{{ route($route.'.index') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium hover:bg-slate-100">Kembali</a>
</div>

<div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
    <dl class="grid gap-4 md:grid-cols-2">
        @foreach ($columns as $column)
            @php
                $value = data_get($item, $column['key']);
                if ($value instanceof \Carbon\CarbonInterface) {
                    $value = $value->format('Y-m-d H:i');
                }
            @endphp
            <div>
                <dt class="text-sm font-medium text-slate-500">{{ $column['label'] }}</dt>
                <dd class="mt-1 whitespace-pre-line text-slate-900">{{ $value ?: '-' }}</dd>
            </div>
        @endforeach
    </dl>
</div>
@endsection
