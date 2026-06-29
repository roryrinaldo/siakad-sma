<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Sistem Informasi Akademik SMA' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-900">
@auth
    @php
        $user = auth()->user();
        $menus = [
            ['label' => 'Dashboard', 'route' => 'dashboard', 'roles' => ['Admin','Kepala Sekolah','Guru','Wali Kelas','Siswa','Orang Tua']],
            ['label' => 'Siswa', 'route' => 'students.index', 'roles' => ['Admin']],
            ['label' => 'Guru', 'route' => 'teachers.index', 'roles' => ['Admin']],
            ['label' => 'Kelas', 'route' => 'school-classes.index', 'roles' => ['Admin']],
            ['label' => 'Mata Pelajaran', 'route' => 'subjects.index', 'roles' => ['Admin']],
            ['label' => 'Tahun Ajaran', 'route' => 'academic-years.index', 'roles' => ['Admin']],
            ['label' => 'Semester', 'route' => 'semesters.index', 'roles' => ['Admin']],
            ['label' => 'Jadwal', 'route' => 'schedules.index', 'roles' => ['Admin','Kepala Sekolah','Guru','Wali Kelas','Siswa','Orang Tua']],
            ['label' => 'Absensi', 'route' => 'attendances.index', 'roles' => ['Admin','Kepala Sekolah','Guru','Wali Kelas','Siswa','Orang Tua']],
            ['label' => 'Nilai', 'route' => 'grades.index', 'roles' => ['Admin','Kepala Sekolah','Guru','Wali Kelas','Siswa','Orang Tua']],
            ['label' => 'Raport', 'route' => 'report-cards.index', 'roles' => ['Admin','Kepala Sekolah','Wali Kelas','Siswa','Orang Tua']],
            ['label' => 'Pengumuman', 'route' => 'announcements.index', 'roles' => ['Admin','Kepala Sekolah','Guru','Wali Kelas','Siswa','Orang Tua']],
            ['label' => 'Laporan', 'route' => 'reports.index', 'roles' => ['Admin','Kepala Sekolah','Guru','Wali Kelas']],
        ];
    @endphp
    <div class="min-h-screen lg:grid lg:grid-cols-[260px_1fr]">
        <aside class="border-b border-slate-200 bg-white lg:min-h-screen lg:border-b-0 lg:border-r">
            <div class="px-5 py-5">
                <div class="text-lg font-semibold tracking-normal">SIA SMA</div>
                <div class="mt-1 text-sm text-slate-500">Sistem Informasi Akademik</div>
            </div>
            <nav class="flex gap-2 overflow-x-auto px-3 pb-4 lg:block lg:space-y-1 lg:overflow-visible">
                @foreach ($menus as $menu)
                    @if ($user->hasAnyRole($menu['roles']))
                        <a href="{{ route($menu['route']) }}" class="block whitespace-nowrap rounded-md px-3 py-2 text-sm font-medium {{ request()->routeIs(str($menu['route'])->before('.')->append('*')->toString()) ? 'bg-indigo-50 text-indigo-700' : 'text-slate-700 hover:bg-slate-100' }}">
                            {{ $menu['label'] }}
                        </a>
                    @endif
                @endforeach
            </nav>
        </aside>
        <div>
            <header class="sticky top-0 z-20 border-b border-slate-200 bg-white/90 backdrop-blur">
                <div class="flex items-center justify-between gap-4 px-5 py-4">
                    <div>
                        <div class="text-sm text-slate-500">Masuk sebagai</div>
                        <div class="font-semibold">{{ $user->name }} <span class="font-normal text-slate-500">({{ $user->roles->pluck('name')->join(', ') }})</span></div>
                    </div>
                    <div class="flex items-center gap-2">
                        <a href="{{ route('profile.edit') }}" class="rounded-md border border-slate-200 px-3 py-2 text-sm font-medium hover:bg-slate-100">Profil</a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button class="rounded-md bg-slate-900 px-3 py-2 text-sm font-medium text-white hover:bg-slate-700">Keluar</button>
                        </form>
                    </div>
                </div>
            </header>
            <main class="px-5 py-6">
                @if (session('status'))
                    <div class="mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
                @endif
                @yield('content')
            </main>
        </div>
    </div>
@else
    @yield('content')
@endauth
</body>
</html>
