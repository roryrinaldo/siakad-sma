<?php

namespace App\Http\Requests;

use App\Models\SchoolClass;
use Illuminate\Foundation\Http\FormRequest;

class GenerateReportCardsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user?->hasAnyRole(['Admin', 'Wali Kelas'])) {
            return false;
        }

        if ($user->hasRole('Admin')) {
            return true;
        }

        return SchoolClass::whereKey($this->input('school_class_id'))
            ->where('homeroom_teacher_id', $user->teacher?->id)
            ->exists();
    }

    public function rules(): array
    {
        return [
            'school_class_id' => ['required', 'exists:school_classes,id'],
            'academic_year_id' => ['required', 'exists:academic_years,id'],
            'semester_id' => ['required', 'exists:semesters,id'],
            'homeroom_note' => ['nullable', 'string'],
            'is_validated' => ['nullable', 'boolean'],
        ];
    }
}
