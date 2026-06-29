<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AnnouncementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['Admin', 'Guru']) ?? false;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'target_role' => ['nullable', Rule::in(['Admin', 'Kepala Sekolah', 'Guru', 'Wali Kelas', 'Siswa', 'Orang Tua'])],
            'target_class_id' => ['nullable', 'exists:school_classes,id'],
            'published_at' => ['nullable', 'date'],
            'status' => ['required', Rule::in(['draft', 'publish'])],
        ];
    }
}
