<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GradeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['Admin', 'Guru']) ?? false;
    }

    public function rules(): array
    {
        return [
            'student_id' => ['required', 'exists:students,id'],
            'school_class_id' => ['required', 'exists:school_classes,id'],
            'subject_id' => ['required', 'exists:subjects,id'],
            'teacher_id' => ['nullable', 'exists:teachers,id'],
            'academic_year_id' => ['required', 'exists:academic_years,id'],
            'semester_id' => ['required', 'exists:semesters,id'],
            'assignment_score' => ['required', 'numeric', 'between:0,100'],
            'daily_test_score' => ['required', 'numeric', 'between:0,100'],
            'midterm_score' => ['required', 'numeric', 'between:0,100'],
            'final_exam_score' => ['required', 'numeric', 'between:0,100'],
            'practice_score' => ['required', 'numeric', 'between:0,100'],
            'attitude_score' => ['nullable', 'string', 'max:50'],
            'note' => ['nullable', 'string'],
        ];
    }
}
