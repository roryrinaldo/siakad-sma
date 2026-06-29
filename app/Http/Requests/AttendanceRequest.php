<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['Admin', 'Guru']) ?? false;
    }

    public function rules(): array
    {
        return [
            'date' => ['required', 'date'],
            'school_class_id' => ['required', 'exists:school_classes,id'],
            'schedule_id' => ['nullable', 'exists:schedules,id'],
            'student_id' => ['required', 'exists:students,id'],
            'teacher_id' => ['nullable', 'exists:teachers,id'],
            'status' => ['required', Rule::in(['hadir', 'izin', 'sakit', 'alpha', 'terlambat'])],
            'note' => ['nullable', 'string'],
        ];
    }
}
