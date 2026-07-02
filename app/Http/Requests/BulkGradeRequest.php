<?php

namespace App\Http\Requests;

use App\Models\Schedule;
use Illuminate\Foundation\Http\FormRequest;

class BulkGradeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user?->hasAnyRole(['Admin', 'Guru'])) {
            return false;
        }

        if ($user->hasRole('Admin')) {
            return true;
        }

        return Schedule::whereKey($this->input('schedule_id'))
            ->where('teacher_id', $user->teacher?->id)
            ->exists();
    }

    public function rules(): array
    {
        return [
            'schedule_id' => ['required', 'exists:schedules,id'],
            'grades' => ['required', 'array', 'min:1'],
            'grades.*.student_id' => ['required', 'exists:students,id'],
            'grades.*.assignment_score' => ['required', 'numeric', 'between:0,100'],
            'grades.*.daily_test_score' => ['required', 'numeric', 'between:0,100'],
            'grades.*.midterm_score' => ['required', 'numeric', 'between:0,100'],
            'grades.*.final_exam_score' => ['required', 'numeric', 'between:0,100'],
            'grades.*.practice_score' => ['required', 'numeric', 'between:0,100'],
            'grades.*.attitude_score' => ['nullable', 'string', 'max:50'],
            'grades.*.note' => ['nullable', 'string'],
        ];
    }
}
