<?php

namespace App\Http\Controllers;

use App\Http\Requests\SemesterRequest;
use App\Models\AcademicYear;
use App\Models\Semester;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SemesterController extends Controller
{
    public function index(): View
    {
        return view('resources.index', [
            'title' => 'Semester',
            'route' => 'semesters',
            'items' => Semester::with('academicYear')->latest()->paginate(10),
            'columns' => [
                ['label' => 'Semester', 'key' => 'name'],
                ['label' => 'Tahun Ajaran', 'key' => 'academicYear.year'],
                ['label' => 'Status', 'key' => 'is_active', 'boolean' => true],
            ],
        ]);
    }

    public function create(): View
    {
        return view('resources.form', $this->formData('Tambah Semester', new Semester()));
    }

    public function show(Semester $semester): View
    {
        $semester->load('academicYear');

        return view('resources.show', [
            'title' => 'Detail Semester',
            'route' => 'semesters',
            'item' => $semester,
            'columns' => [
                ['label' => 'Semester', 'key' => 'name'],
                ['label' => 'Tahun Ajaran', 'key' => 'academicYear.year'],
                ['label' => 'Tanggal Mulai', 'key' => 'starts_on'],
                ['label' => 'Tanggal Selesai', 'key' => 'ends_on'],
            ],
        ]);
    }

    public function store(SemesterRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active');
        $this->deactivateOthers($data['is_active']);
        Semester::create($data);

        return redirect()->route('semesters.index')->with('status', 'Semester berhasil ditambahkan.');
    }

    public function edit(Semester $semester): View
    {
        return view('resources.form', $this->formData('Edit Semester', $semester));
    }

    public function update(SemesterRequest $request, Semester $semester): RedirectResponse
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active');
        $this->deactivateOthers($data['is_active'], $semester->id);
        $semester->update($data);

        return redirect()->route('semesters.index')->with('status', 'Semester berhasil diperbarui.');
    }

    public function destroy(Semester $semester): RedirectResponse
    {
        $semester->delete();

        return back()->with('status', 'Semester berhasil dihapus.');
    }

    private function formData(string $title, Semester $item): array
    {
        return [
            'title' => $title,
            'route' => 'semesters',
            'item' => $item,
            'fields' => [
                ['name' => 'academic_year_id', 'label' => 'Tahun Ajaran', 'type' => 'select', 'options' => AcademicYear::orderByDesc('year')->pluck('year', 'id')],
                ['name' => 'name', 'label' => 'Semester', 'type' => 'select', 'options' => collect(['Ganjil' => 'Ganjil', 'Genap' => 'Genap'])],
                ['name' => 'starts_on', 'label' => 'Tanggal Mulai', 'type' => 'date'],
                ['name' => 'ends_on', 'label' => 'Tanggal Selesai', 'type' => 'date'],
                ['name' => 'is_active', 'label' => 'Aktif', 'type' => 'checkbox'],
            ],
        ];
    }

    private function deactivateOthers(bool $active, ?int $except = null): void
    {
        if ($active) {
            Semester::when($except, fn ($query) => $query->whereKeyNot($except))->update(['is_active' => false]);
        }
    }
}
