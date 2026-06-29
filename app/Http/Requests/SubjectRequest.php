<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['Admin']) ?? false;
    }

    public function rules(): array
    {
        $subjectId = $this->route('subject')?->id;

        return [
            'code' => ['required', 'string', 'max:50', Rule::unique('subjects', 'code')->ignore($subjectId)],
            'name' => ['required', 'string', 'max:255'],
            'group' => ['nullable', 'string', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
            'teacher_ids' => ['array'],
            'teacher_ids.*' => ['exists:teachers,id'],
        ];
    }
}
