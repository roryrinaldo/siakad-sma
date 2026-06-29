<?php

namespace App\Http\Controllers;

use App\Http\Requests\AcademicYearRequest;
use App\Models\AcademicYear;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AcademicYearController extends Controller
{
    public function index(): View
    {
        return view('resources.index', [
            'title' => 'Tahun Ajaran',
            'route' => 'academic-years',
            'items' => AcademicYear::latest()->paginate(10),
            'columns' => [
                ['label' => 'Tahun Ajaran', 'key' => 'year'],
                ['label' => 'Status', 'key' => 'is_active', 'boolean' => true],
            ],
        ]);
    }

    public function create(): View
    {
        return view('resources.form', $this->formData('Tambah Tahun Ajaran', new AcademicYear()));
    }

    public function show(AcademicYear $academicYear): View
    {
        return view('resources.show', [
            'title' => 'Detail Tahun Ajaran',
            'route' => 'academic-years',
            'item' => $academicYear,
            'columns' => [
                ['label' => 'Tahun Ajaran', 'key' => 'year'],
                ['label' => 'Aktif', 'key' => 'is_active'],
            ],
        ]);
    }

    public function store(AcademicYearRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active');
        $this->deactivateOthers($data['is_active']);
        AcademicYear::create($data);

        return redirect()->route('academic-years.index')->with('status', 'Tahun ajaran berhasil ditambahkan.');
    }

    public function edit(AcademicYear $academicYear): View
    {
        return view('resources.form', $this->formData('Edit Tahun Ajaran', $academicYear));
    }

    public function update(AcademicYearRequest $request, AcademicYear $academicYear): RedirectResponse
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active');
        $this->deactivateOthers($data['is_active'], $academicYear->id);
        $academicYear->update($data);

        return redirect()->route('academic-years.index')->with('status', 'Tahun ajaran berhasil diperbarui.');
    }

    public function destroy(AcademicYear $academicYear): RedirectResponse
    {
        $academicYear->delete();

        return back()->with('status', 'Tahun ajaran berhasil dihapus.');
    }

    private function formData(string $title, AcademicYear $item): array
    {
        return [
            'title' => $title,
            'route' => 'academic-years',
            'item' => $item,
            'fields' => [
                ['name' => 'year', 'label' => 'Tahun Ajaran', 'type' => 'text'],
                ['name' => 'is_active', 'label' => 'Aktif', 'type' => 'checkbox'],
            ],
        ];
    }

    private function deactivateOthers(bool $active, ?int $except = null): void
    {
        if ($active) {
            AcademicYear::when($except, fn ($query) => $query->whereKeyNot($except))->update(['is_active' => false]);
        }
    }
}
