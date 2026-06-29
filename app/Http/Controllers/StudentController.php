<?php

namespace App\Http\Controllers;

use App\Http\Requests\StudentRequest;
use App\Models\SchoolClass;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StudentController extends Controller
{
    public function index(Request $request): View
    {
        $items = Student::query()
            ->with('schoolClass')
            ->when($request->search, fn ($query, $search) => $query->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('nis', 'like', "%{$search}%")
                ->orWhere('nisn', 'like', "%{$search}%")))
            ->when($request->status, fn ($query, $status) => $query->where('status', $status))
            ->when($request->school_class_id, fn ($query, $classId) => $query->where('school_class_id', $classId))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('resources.index', $this->viewData('Data Siswa', 'students', $items) + [
            'filters' => [
                ['name' => 'search', 'label' => 'Cari nama/NIS', 'type' => 'text'],
                ['name' => 'school_class_id', 'label' => 'Kelas', 'type' => 'select', 'options' => SchoolClass::orderBy('name')->pluck('name', 'id')],
                ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => collect(['aktif' => 'Aktif', 'lulus' => 'Lulus', 'pindah' => 'Pindah', 'keluar' => 'Keluar'])],
            ],
        ]);
    }

    public function create(): View
    {
        return view('resources.form', $this->formData('Tambah Siswa', 'students', new Student()));
    }

    public function store(StudentRequest $request): RedirectResponse
    {
        Student::create($request->validated());

        return redirect()->route('students.index')->with('status', 'Siswa berhasil ditambahkan.');
    }

    public function show(Student $student): View
    {
        $student->load(['schoolClass', 'grades.subject', 'attendances']);

        return view('resources.show', $this->viewData('Detail Siswa', 'students', collect([$student])) + ['item' => $student]);
    }

    public function edit(Student $student): View
    {
        return view('resources.form', $this->formData('Edit Siswa', 'students', $student));
    }

    public function update(StudentRequest $request, Student $student): RedirectResponse
    {
        $student->update($request->validated());

        return redirect()->route('students.index')->with('status', 'Siswa berhasil diperbarui.');
    }

    public function destroy(Student $student): RedirectResponse
    {
        $student->delete();

        return back()->with('status', 'Siswa berhasil dihapus.');
    }

    private function viewData(string $title, string $route, $items): array
    {
        return [
            'title' => $title,
            'route' => $route,
            'items' => $items,
            'columns' => [
                ['label' => 'NIS', 'key' => 'nis'],
                ['label' => 'Nama', 'key' => 'name'],
                ['label' => 'Kelas', 'key' => 'schoolClass.name'],
                ['label' => 'Status', 'key' => 'status', 'badge' => true],
            ],
        ];
    }

    private function formData(string $title, string $route, Student $item): array
    {
        return [
            'title' => $title,
            'route' => $route,
            'item' => $item,
            'fields' => [
                ['name' => 'school_class_id', 'label' => 'Kelas', 'type' => 'select', 'options' => SchoolClass::orderBy('name')->pluck('name', 'id')],
                ['name' => 'nis', 'label' => 'NIS', 'type' => 'text'],
                ['name' => 'nisn', 'label' => 'NISN', 'type' => 'text'],
                ['name' => 'name', 'label' => 'Nama', 'type' => 'text'],
                ['name' => 'gender', 'label' => 'Jenis Kelamin', 'type' => 'select', 'options' => collect(['L' => 'Laki-laki', 'P' => 'Perempuan'])],
                ['name' => 'birth_place', 'label' => 'Tempat Lahir', 'type' => 'text'],
                ['name' => 'birth_date', 'label' => 'Tanggal Lahir', 'type' => 'date'],
                ['name' => 'religion', 'label' => 'Agama', 'type' => 'text'],
                ['name' => 'address', 'label' => 'Alamat', 'type' => 'textarea'],
                ['name' => 'phone', 'label' => 'Nomor HP', 'type' => 'text'],
                ['name' => 'email', 'label' => 'Email', 'type' => 'email'],
                ['name' => 'entry_year', 'label' => 'Tahun Masuk', 'type' => 'number'],
                ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => collect(['aktif' => 'Aktif', 'lulus' => 'Lulus', 'pindah' => 'Pindah', 'keluar' => 'Keluar'])],
            ],
        ];
    }
}
