<?php

namespace App\Http\Controllers;

use App\Http\Requests\AnnouncementRequest;
use App\Models\Announcement;
use App\Models\Schedule;
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
            ->when(! $request->user()->hasRole('Admin'), fn ($query) => $this->scopeVisibleAnnouncements($query, $request))
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
        $data = $request->validated();
        $this->ensureCanTargetClass($request, $data['target_class_id'] ?? null);

        Announcement::create($data + ['created_by' => $request->user()->id]);

        return redirect()->route('announcements.index')->with('status', 'Pengumuman berhasil ditambahkan.');
    }

    public function show(Announcement $announcement): View
    {
        $this->ensureCanView($announcement, request());

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
        $this->ensureCanManage($announcement, request());

        return view('resources.form', $this->formData('Edit Pengumuman', $announcement));
    }

    public function update(AnnouncementRequest $request, Announcement $announcement): RedirectResponse
    {
        $this->ensureCanManage($announcement, $request);
        $data = $request->validated();
        $this->ensureCanTargetClass($request, $data['target_class_id'] ?? null);
        $announcement->update($data);

        return redirect()->route('announcements.index')->with('status', 'Pengumuman berhasil diperbarui.');
    }

    public function destroy(Announcement $announcement): RedirectResponse
    {
        $this->ensureCanManage($announcement, request());
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
                ['name' => 'target_class_id', 'label' => 'Target Kelas', 'type' => 'select', 'options' => $this->availableTargetClasses(request())->orderBy('name')->pluck('name', 'id')],
                ['name' => 'published_at', 'label' => 'Tanggal Publish', 'type' => 'datetime-local'],
                ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => collect(['draft' => 'Draft', 'publish' => 'Publish'])],
            ],
        ];
    }

    private function scopeVisibleAnnouncements($query, Request $request): void
    {
        $user = $request->user()->loadMissing(['student', 'teacher', 'children']);
        $classIds = collect([$user->student?->school_class_id])
            ->merge($user->children->pluck('school_class_id'))
            ->merge($this->teacherClassIds($request))
            ->filter()
            ->unique()
            ->values();
        $roles = $user->roles->pluck('name');

        $query->where(function ($outer) use ($user, $roles, $classIds): void {
            $outer->where('created_by', $user->id)
                ->orWhere(function ($published) use ($roles, $classIds): void {
                    $published->where('status', 'publish')
                        ->where(function ($visible) use ($roles, $classIds): void {
                            $visible->where(function ($roleQuery) use ($roles): void {
                                $roleQuery->whereNull('target_role')->orWhereIn('target_role', $roles);
                            });

                            if ($classIds->isNotEmpty()) {
                                $visible->orWhereIn('target_class_id', $classIds);
                            }
                        });
                });
        });
    }

    private function ensureCanView(Announcement $announcement, Request $request): void
    {
        if ($request->user()->hasRole('Admin') || $announcement->created_by === $request->user()->id) {
            return;
        }

        abort_if(
            ! Announcement::whereKey($announcement->id)
                ->tap(fn ($query) => $this->scopeVisibleAnnouncements($query, $request))
                ->exists(),
            403
        );
    }

    private function ensureCanManage(Announcement $announcement, Request $request): void
    {
        if ($request->user()->hasRole('Admin')) {
            return;
        }

        abort_if($announcement->created_by !== $request->user()->id, 403);
    }

    private function ensureCanTargetClass(Request $request, ?int $classId): void
    {
        if (! $classId || $request->user()->hasRole('Admin')) {
            return;
        }

        abort_if(! $this->teacherClassIds($request)->contains($classId), 403);
    }

    private function availableTargetClasses(Request $request)
    {
        $query = SchoolClass::query();
        if ($request->user()?->hasRole('Guru') && ! $request->user()->hasRole('Admin')) {
            $query->whereIn('id', $this->teacherClassIds($request));
        }

        return $query;
    }

    private function teacherClassIds(Request $request)
    {
        $teacherId = $request->user()?->teacher?->id;

        return $teacherId
            ? Schedule::where('teacher_id', $teacherId)->pluck('school_class_id')
            : collect();
    }
}
