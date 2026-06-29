<?php

namespace App\Http\Controllers;

use App\Http\Requests\AnnouncementRequest;
use App\Models\Announcement;
use App\Models\SchoolClass;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AnnouncementController extends Controller
{
    public function index(Request $request): View
    {
        $items = Announcement::query()
            ->with(['creator', 'targetClass'])
            ->when(! $request->user()->hasAnyRole(['Admin', 'Guru', 'Kepala Sekolah']), function ($query) use ($request): void {
                $user = $request->user()->loadMissing(['student', 'children']);
                $classIds = collect([$user->student?->school_class_id])->merge($user->children->pluck('school_class_id'))->filter();
                $role = $user->roles->pluck('name')->first();

                $query->where('status', 'publish')
                    ->where(fn ($q) => $q->whereNull('target_role')->orWhere('target_role', $role))
                    ->when($classIds->isNotEmpty(), fn ($q) => $q->orWhereIn('target_class_id', $classIds));
            })
            ->latest('published_at')
            ->paginate(10);

        return view('resources.index', [
            'title' => 'Pengumuman',
            'route' => 'announcements',
            'items' => $items,
            'columns' => [
                ['label' => 'Judul', 'key' => 'title'],
                ['label' => 'Target Role', 'key' => 'target_role'],
                ['label' => 'Target Kelas', 'key' => 'targetClass.name'],
                ['label' => 'Status', 'key' => 'status', 'badge' => true],
            ],
        ]);
    }

    public function create(): View
    {
        return view('resources.form', $this->formData('Tambah Pengumuman', new Announcement()));
    }

    public function store(AnnouncementRequest $request): RedirectResponse
    {
        Announcement::create($request->validated() + ['created_by' => $request->user()->id]);

        return redirect()->route('announcements.index')->with('status', 'Pengumuman berhasil ditambahkan.');
    }

    public function show(Announcement $announcement): View
    {
        return view('resources.show', [
            'title' => 'Detail Pengumuman',
            'route' => 'announcements',
            'item' => $announcement->load(['creator', 'targetClass']),
            'columns' => [
                ['label' => 'Judul', 'key' => 'title'],
                ['label' => 'Isi', 'key' => 'body'],
                ['label' => 'Target Role', 'key' => 'target_role'],
                ['label' => 'Status', 'key' => 'status'],
            ],
        ]);
    }

    public function edit(Announcement $announcement): View
    {
        return view('resources.form', $this->formData('Edit Pengumuman', $announcement));
    }

    public function update(AnnouncementRequest $request, Announcement $announcement): RedirectResponse
    {
        $announcement->update($request->validated());

        return redirect()->route('announcements.index')->with('status', 'Pengumuman berhasil diperbarui.');
    }

    public function destroy(Announcement $announcement): RedirectResponse
    {
        $announcement->delete();

        return back()->with('status', 'Pengumuman berhasil dihapus.');
    }

    private function formData(string $title, Announcement $item): array
    {
        return [
            'title' => $title,
            'route' => 'announcements',
            'item' => $item,
            'fields' => [
                ['name' => 'title', 'label' => 'Judul', 'type' => 'text'],
                ['name' => 'body', 'label' => 'Isi', 'type' => 'textarea'],
                ['name' => 'target_role', 'label' => 'Target Role', 'type' => 'select', 'options' => collect(['Admin' => 'Admin', 'Kepala Sekolah' => 'Kepala Sekolah', 'Guru' => 'Guru', 'Wali Kelas' => 'Wali Kelas', 'Siswa' => 'Siswa', 'Orang Tua' => 'Orang Tua'])],
                ['name' => 'target_class_id', 'label' => 'Target Kelas', 'type' => 'select', 'options' => SchoolClass::orderBy('name')->pluck('name', 'id')],
                ['name' => 'published_at', 'label' => 'Tanggal Publish', 'type' => 'datetime-local'],
                ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => collect(['draft' => 'Draft', 'publish' => 'Publish'])],
            ],
        ];
    }
}
