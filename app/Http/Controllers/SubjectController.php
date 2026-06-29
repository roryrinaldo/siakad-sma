<?php

namespace App\Http\Controllers;

use App\Http\Requests\SubjectRequest;
use App\Models\Subject;
use App\Models\Teacher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SubjectController extends Controller
{
    public function index(Request $request): View
    {
        $items = Subject::query()
            ->with('teachers')
            ->when($request->search, fn ($query, $search) => $query->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('code', 'like', "%{$search}%")))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('resources.index', [
            'title' => 'Mata Pelajaran',
            'route' => 'subjects',
            'items' => $items,
            'filters' => [['name' => 'search', 'label' => 'Cari kode/nama', 'type' => 'text']],
            'columns' => [
                ['label' => 'Kode', 'key' => 'code'],
                ['label' => 'Nama', 'key' => 'name'],
                ['label' => 'Kelompok', 'key' => 'group'],
                ['label' => 'Status', 'key' => 'is_active', 'boolean' => true],
            ],
        ]);
    }

    public function create(): View
    {
        return view('resources.form', $this->formData('Tambah Mata Pelajaran', new Subject()));
    }

    public function store(SubjectRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $teachers = $data['teacher_ids'] ?? [];
        unset($data['teacher_ids']);
        $data['is_active'] = $request->boolean('is_active');

        $subject = Subject::create($data);
        $subject->teachers()->sync($teachers);

        return redirect()->route('subjects.index')->with('status', 'Mata pelajaran berhasil ditambahkan.');
    }

    public function show(Subject $subject): View
    {
        $subject->load('teachers');

        return view('resources.show', [
            'title' => 'Detail Mata Pelajaran',
            'route' => 'subjects',
            'item' => $subject,
            'columns' => [
                ['label' => 'Kode', 'key' => 'code'],
                ['label' => 'Nama', 'key' => 'name'],
                ['label' => 'Kelompok', 'key' => 'group'],
            ],
        ]);
    }

    public function edit(Subject $subject): View
    {
        $subject->load('teachers');

        return view('resources.form', $this->formData('Edit Mata Pelajaran', $subject));
    }

    public function update(SubjectRequest $request, Subject $subject): RedirectResponse
    {
        $data = $request->validated();
        $teachers = $data['teacher_ids'] ?? [];
        unset($data['teacher_ids']);
        $data['is_active'] = $request->boolean('is_active');

        $subject->update($data);
        $subject->teachers()->sync($teachers);

        return redirect()->route('subjects.index')->with('status', 'Mata pelajaran berhasil diperbarui.');
    }

    public function destroy(Subject $subject): RedirectResponse
    {
        $subject->delete();

        return back()->with('status', 'Mata pelajaran berhasil dihapus.');
    }

    private function formData(string $title, Subject $item): array
    {
        return [
            'title' => $title,
            'route' => 'subjects',
            'item' => $item,
            'fields' => [
                ['name' => 'code', 'label' => 'Kode', 'type' => 'text'],
                ['name' => 'name', 'label' => 'Nama', 'type' => 'text'],
                ['name' => 'group', 'label' => 'Kelompok', 'type' => 'text'],
                ['name' => 'is_active', 'label' => 'Aktif', 'type' => 'checkbox'],
                ['name' => 'teacher_ids', 'label' => 'Guru Pengampu', 'type' => 'multi_select', 'options' => Teacher::orderBy('name')->pluck('name', 'id')],
            ],
        ];
    }
}
