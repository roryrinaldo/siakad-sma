<?php

namespace App\Http\Controllers;

use App\Http\Requests\SchoolClassRequest;
use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Teacher;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SchoolClassController extends Controller
{
    public function index(): View
    {
        $items = SchoolClass::with(['academicYear', 'homeroomTeacher'])->latest()->paginate(10);

        return view('resources.index', $this->viewData($items));
    }

    public function create(): View
    {
        return view('resources.form', $this->formData('Tambah Kelas', new SchoolClass()));
    }

    public function store(SchoolClassRequest $request): RedirectResponse
    {
        SchoolClass::create($request->validated());

        return redirect()->route('school-classes.index')->with('status', 'Kelas berhasil ditambahkan.');
    }

    public function show(SchoolClass $schoolClass): View
    {
        $schoolClass->load(['students', 'homeroomTeacher', 'schedules.subject', 'schedules.teacher']);

        return view('resources.show', $this->viewData(collect([$schoolClass])) + ['item' => $schoolClass, 'title' => 'Detail Kelas']);
    }

    public function edit(SchoolClass $schoolClass): View
    {
        return view('resources.form', $this->formData('Edit Kelas', $schoolClass));
    }

    public function update(SchoolClassRequest $request, SchoolClass $schoolClass): RedirectResponse
    {
        $schoolClass->update($request->validated());

        return redirect()->route('school-classes.index')->with('status', 'Kelas berhasil diperbarui.');
    }

    public function destroy(SchoolClass $schoolClass): RedirectResponse
    {
        $schoolClass->delete();

        return back()->with('status', 'Kelas berhasil dihapus.');
    }

    private function viewData($items): array
    {
        return [
            'title' => 'Data Kelas',
            'route' => 'school-classes',
            'items' => $items,
            'columns' => [
                ['label' => 'Nama', 'key' => 'name'],
                ['label' => 'Tingkat', 'key' => 'level'],
                ['label' => 'Jurusan', 'key' => 'major'],
                ['label' => 'Wali Kelas', 'key' => 'homeroomTeacher.name'],
            ],
        ];
    }

    private function formData(string $title, SchoolClass $item): array
    {
        return [
            'title' => $title,
            'route' => 'school-classes',
            'item' => $item,
            'fields' => [
                ['name' => 'name', 'label' => 'Nama Kelas', 'type' => 'text'],
                ['name' => 'level', 'label' => 'Tingkat', 'type' => 'select', 'options' => collect(['X' => 'X', 'XI' => 'XI', 'XII' => 'XII'])],
                ['name' => 'major', 'label' => 'Jurusan', 'type' => 'select', 'options' => collect(['IPA' => 'IPA', 'IPS' => 'IPS', 'Bahasa' => 'Bahasa', 'Umum' => 'Umum'])],
                ['name' => 'academic_year_id', 'label' => 'Tahun Ajaran', 'type' => 'select', 'options' => AcademicYear::orderByDesc('year')->pluck('year', 'id')],
                ['name' => 'homeroom_teacher_id', 'label' => 'Wali Kelas', 'type' => 'select', 'options' => Teacher::orderBy('name')->pluck('name', 'id')],
            ],
        ];
    }
}
