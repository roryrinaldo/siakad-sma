<?php

namespace App\Http\Controllers;

use App\Http\Requests\TeacherRequest;
use App\Models\Subject;
use App\Models\Teacher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TeacherController extends Controller
{
    public function index(Request $request): View
    {
        $items = Teacher::query()
            ->with('subjects')
            ->when($request->search, fn ($query, $search) => $query->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('nip', 'like', "%{$search}%")))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('resources.index', $this->viewData('Data Guru', 'teachers', $items) + [
            'filters' => [['name' => 'search', 'label' => 'Cari nama/NIP', 'type' => 'text']],
        ]);
    }

    public function create(): View
    {
        return view('resources.form', $this->formData('Tambah Guru', 'teachers', new Teacher()));
    }

    public function store(TeacherRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $subjects = $data['subject_ids'] ?? [];
        unset($data['subject_ids']);
        $data['is_active'] = $request->boolean('is_active');

        $teacher = Teacher::create($data);
        $teacher->subjects()->sync($subjects);

        return redirect()->route('teachers.index')->with('status', 'Guru berhasil ditambahkan.');
    }

    public function show(Teacher $teacher): View
    {
        $teacher->load(['subjects', 'schedules.schoolClass']);

        return view('resources.show', $this->viewData('Detail Guru', 'teachers', collect([$teacher])) + ['item' => $teacher]);
    }

    public function edit(Teacher $teacher): View
    {
        $teacher->load('subjects');

        return view('resources.form', $this->formData('Edit Guru', 'teachers', $teacher));
    }

    public function update(TeacherRequest $request, Teacher $teacher): RedirectResponse
    {
        $data = $request->validated();
        $subjects = $data['subject_ids'] ?? [];
        unset($data['subject_ids']);
        $data['is_active'] = $request->boolean('is_active');

        $teacher->update($data);
        $teacher->subjects()->sync($subjects);

        return redirect()->route('teachers.index')->with('status', 'Guru berhasil diperbarui.');
    }

    public function destroy(Teacher $teacher): RedirectResponse
    {
        $teacher->delete();

        return back()->with('status', 'Guru berhasil dihapus.');
    }

    private function viewData(string $title, string $route, $items): array
    {
        return [
            'title' => $title,
            'route' => $route,
            'items' => $items,
            'columns' => [
                ['label' => 'NIP', 'key' => 'nip'],
                ['label' => 'Nama', 'key' => 'name'],
                ['label' => 'Email', 'key' => 'email'],
                ['label' => 'Status', 'key' => 'is_active', 'boolean' => true],
            ],
        ];
    }

    private function formData(string $title, string $route, Teacher $item): array
    {
        return [
            'title' => $title,
            'route' => $route,
            'item' => $item,
            'fields' => [
                ['name' => 'nip', 'label' => 'NIP', 'type' => 'text'],
                ['name' => 'nuptk', 'label' => 'NUPTK', 'type' => 'text'],
                ['name' => 'name', 'label' => 'Nama', 'type' => 'text'],
                ['name' => 'gender', 'label' => 'Jenis Kelamin', 'type' => 'select', 'options' => collect(['L' => 'Laki-laki', 'P' => 'Perempuan'])],
                ['name' => 'address', 'label' => 'Alamat', 'type' => 'textarea'],
                ['name' => 'phone', 'label' => 'Nomor HP', 'type' => 'text'],
                ['name' => 'email', 'label' => 'Email', 'type' => 'email'],
                ['name' => 'is_active', 'label' => 'Aktif', 'type' => 'checkbox'],
                ['name' => 'subject_ids', 'label' => 'Mata Pelajaran', 'type' => 'multi_select', 'options' => Subject::orderBy('name')->pluck('name', 'id')],
            ],
        ];
    }
}
