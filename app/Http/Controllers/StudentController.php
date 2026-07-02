<?php

namespace App\Http\Controllers;

use App\Http\Requests\StudentRequest;
use App\Models\SchoolClass;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
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

    public function importCreate(): View
    {
        return view('resources.import', [
            'title' => 'Import Data Siswa',
            'route' => 'students.import.store',
            'backRoute' => 'students.index',
            'headers' => ['nis', 'nisn', 'name', 'gender', 'school_class_id', 'status', 'entry_year', 'email', 'phone'],
            'notes' => [
                'gender gunakan L atau P.',
                'school_class_id isi dengan ID kelas dari menu Kelas.',
                'status gunakan aktif, lulus, pindah, atau keluar.',
            ],
        ]);
    }

    public function importStore(Request $request): RedirectResponse
    {
        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt']]);

        [$imported, $skipped, $errors] = $this->importCsv($request->file('file')->getRealPath());

        return redirect()->route('students.index')->with(
            'status',
            "Import siswa selesai: {$imported} berhasil, {$skipped} dilewati".($errors ? ' ('.implode('; ', array_slice($errors, 0, 3)).')' : '.')
        );
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

    private function importCsv(string $path): array
    {
        $handle = fopen($path, 'r');
        if (! $handle) {
            return [0, 0, ['File tidak dapat dibaca.']];
        }

        $headers = fgetcsv($handle) ?: [];
        $headers = array_map(fn ($header) => trim(strtolower((string) $header)), $headers);
        $imported = 0;
        $skipped = 0;
        $errors = [];

        while (($row = fgetcsv($handle)) !== false) {
            $data = array_combine($headers, array_pad($row, count($headers), null));
            if (! $data || blank($data['nis'] ?? null)) {
                $skipped++;
                continue;
            }

            $payload = [
                'school_class_id' => ($data['school_class_id'] ?? null) ?: null,
                'nis' => $data['nis'] ?? null,
                'nisn' => ($data['nisn'] ?? null) ?: null,
                'name' => $data['name'] ?? null,
                'gender' => strtoupper($data['gender'] ?? 'L'),
                'birth_place' => $data['birth_place'] ?? null,
                'birth_date' => ($data['birth_date'] ?? null) ?: null,
                'religion' => $data['religion'] ?? null,
                'address' => $data['address'] ?? null,
                'phone' => $data['phone'] ?? null,
                'email' => ($data['email'] ?? null) ?: null,
                'entry_year' => ($data['entry_year'] ?? null) ?: null,
                'status' => ($data['status'] ?? null) ?: 'aktif',
            ];

            $validator = Validator::make($payload, [
                'school_class_id' => ['nullable', 'exists:school_classes,id'],
                'nis' => ['required', 'string', 'max:50'],
                'nisn' => ['nullable', 'string', 'max:50'],
                'name' => ['required', 'string', 'max:255'],
                'gender' => ['required', 'in:L,P'],
                'birth_date' => ['nullable', 'date'],
                'email' => ['nullable', 'email', 'max:255'],
                'entry_year' => ['nullable', 'integer', 'between:2000,2100'],
                'status' => ['required', 'in:aktif,lulus,pindah,keluar'],
            ]);

            if ($validator->fails()) {
                $skipped++;
                $errors[] = 'NIS '.($payload['nis'] ?: '-').': '.$validator->errors()->first();
                continue;
            }

            Student::updateOrCreate(['nis' => $payload['nis']], $payload);
            $imported++;
        }

        fclose($handle);

        return [$imported, $skipped, $errors];
    }
}
