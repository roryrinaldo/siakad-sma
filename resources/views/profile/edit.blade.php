@extends('layouts.app')

@section('content')
<div class="mb-5">
    <h1 class="text-2xl font-semibold">Profil</h1>
    <p class="text-sm text-slate-500">Perbarui identitas akun dan password.</p>
</div>

@if ($errors->any())
    <div class="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
@endif

<div class="grid gap-5 xl:grid-cols-2">
    <form method="POST" action="{{ route('profile.update') }}" class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
        @csrf
        @method('PATCH')
        <h2 class="mb-4 text-lg font-semibold">Data Akun</h2>
        <label class="block">
            <span class="text-sm font-medium">Nama</span>
            <input name="name" value="{{ old('name', $user->name) }}" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
        </label>
        <label class="mt-4 block">
            <span class="text-sm font-medium">Email</span>
            <input name="email" type="email" value="{{ old('email', $user->email) }}" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
        </label>
        <button class="mt-5 rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white">Simpan Profil</button>
    </form>

    <form method="POST" action="{{ route('profile.password') }}" class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
        @csrf
        @method('PUT')
        <h2 class="mb-4 text-lg font-semibold">Password</h2>
        <label class="block">
            <span class="text-sm font-medium">Password Saat Ini</span>
            <input name="current_password" type="password" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
        </label>
        <label class="mt-4 block">
            <span class="text-sm font-medium">Password Baru</span>
            <input name="password" type="password" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
        </label>
        <label class="mt-4 block">
            <span class="text-sm font-medium">Konfirmasi Password</span>
            <input name="password_confirmation" type="password" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
        </label>
        <button class="mt-5 rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white">Ubah Password</button>
    </form>
</div>
@endsection
