<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TeacherRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['Admin']) ?? false;
    }

    public function rules(): array
    {
        $teacherId = $this->route('teacher')?->id;

        return [
            'nip' => ['nullable', 'string', 'max:50', Rule::unique('teachers', 'nip')->ignore($teacherId)],
            'nuptk' => ['nullable', 'string', 'max:50', Rule::unique('teachers', 'nuptk')->ignore($teacherId)],
            'name' => ['required', 'string', 'max:255'],
            'gender' => ['required', Rule::in(['L', 'P'])],
            'address' => ['nullable', 'string'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
            'subject_ids' => ['array'],
            'subject_ids.*' => ['exists:subjects,id'],
        ];
    }
}
