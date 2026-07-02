<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserRequest;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $items = User::query()
            ->with(['roles', 'student.schoolClass', 'teacher', 'children.schoolClass'])
            ->when($request->search, fn ($query, $search) => $query->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")))
            ->when($request->role, fn ($query, $role) => $query->role($role))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('resources.index', [
            'title' => 'Manajemen User',
            'route' => 'users',
            'items' => $items,
            'filters' => [
                ['name' => 'search', 'label' => 'Cari nama/email', 'type' => 'text'],
                ['name' => 'role', 'label' => 'Role', 'type' => 'select', 'options' => Role::orderBy('name')->pluck('name', 'name')],
            ],
            'columns' => [
                ['label' => 'Nama', 'key' => 'name'],
                ['label' => 'Email', 'key' => 'email'],
                ['label' => 'Role', 'key' => 'role_names_text', 'badge' => true],
                ['label' => 'Profil Terkait', 'key' => 'linked_profile_text'],
            ],
        ]);
    }

    public function create(): View
    {
        return view('resources.form', $this->formData('Tambah User', new User()));
    }

    public function store(UserRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        $this->syncRelations($user, $data);

        return redirect()->route('users.index')->with('status', 'User berhasil ditambahkan.');
    }

    public function show(User $user): View
    {
        $user->load(['roles', 'student.schoolClass', 'teacher', 'children.schoolClass']);

        return view('resources.show', [
            'title' => 'Detail User',
            'route' => 'users',
            'item' => $user,
            'columns' => [
                ['label' => 'Nama', 'key' => 'name'],
                ['label' => 'Email', 'key' => 'email'],
                ['label' => 'Role', 'key' => 'role_names_text'],
                ['label' => 'Profil Terkait', 'key' => 'linked_profile_text'],
                ['label' => 'Anak', 'key' => 'children_names_text'],
            ],
        ]);
    }

    public function edit(User $user): View
    {
        $user->load(['roles', 'student', 'teacher', 'children']);

        return view('resources.form', $this->formData('Edit User', $user));
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();
        $payload = [
            'name' => $data['name'],
            'email' => $data['email'],
        ];

        if (! empty($data['password'])) {
            $payload['password'] = Hash::make($data['password']);
        }

        $user->update($payload);
        $this->syncRelations($user, $data);

        return redirect()->route('users.index')->with('status', 'User berhasil diperbarui.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        abort_if($request->user()->is($user), 422, 'User yang sedang login tidak dapat dihapus.');

        $user->student()->update(['user_id' => null]);
        $user->teacher()->update(['user_id' => null]);
        $user->children()->detach();
        $user->delete();

        return back()->with('status', 'User berhasil dihapus.');
    }

    private function syncRelations(User $user, array $data): void
    {
        $user->syncRoles($data['role_names']);

        Student::where('user_id', $user->id)->update(['user_id' => null]);
        Teacher::where('user_id', $user->id)->update(['user_id' => null]);

        if (! empty($data['student_id'])) {
            Student::whereKey($data['student_id'])->update(['user_id' => $user->id]);
        }

        if (! empty($data['teacher_id'])) {
            Teacher::whereKey($data['teacher_id'])->update(['user_id' => $user->id]);
        }

        $user->children()->sync($data['child_ids'] ?? []);
    }

    private function formData(string $title, User $item): array
    {
        return [
            'title' => $title,
            'route' => 'users',
            'item' => $item,
            'fields' => [
                ['name' => 'name', 'label' => 'Nama', 'type' => 'text'],
                ['name' => 'email', 'label' => 'Email', 'type' => 'email'],
                ['name' => 'password', 'label' => $item->exists ? 'Password Baru' : 'Password', 'type' => 'password', 'value' => ''],
                ['name' => 'password_confirmation', 'label' => 'Konfirmasi Password', 'type' => 'password', 'value' => ''],
                ['name' => 'role_names', 'label' => 'Role', 'type' => 'multi_select', 'options' => Role::orderBy('name')->pluck('name', 'name')],
                ['name' => 'student_id', 'label' => 'Akun Siswa', 'type' => 'select', 'options' => Student::orderBy('name')->pluck('name', 'id'), 'value' => $item->student?->id],
                ['name' => 'teacher_id', 'label' => 'Akun Guru', 'type' => 'select', 'options' => Teacher::orderBy('name')->pluck('name', 'id'), 'value' => $item->teacher?->id],
                ['name' => 'child_ids', 'label' => 'Anak untuk Orang Tua', 'type' => 'multi_select', 'options' => Student::orderBy('name')->pluck('name', 'id')],
            ],
        ];
    }
}
