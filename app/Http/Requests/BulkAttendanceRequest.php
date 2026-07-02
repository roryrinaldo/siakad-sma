<?php

namespace App\Http\Requests;

use App\Models\Schedule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkAttendanceRequest extends FormRequest
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
            'date' => ['required', 'date'],
            'schedule_id' => ['required', 'exists:schedules,id'],
            'attendances' => ['required', 'array', 'min:1'],
            'attendances.*.student_id' => ['required', 'exists:students,id'],
            'attendances.*.status' => ['required', Rule::in(['hadir', 'izin', 'sakit', 'alpha', 'terlambat'])],
            'attendances.*.note' => ['nullable', 'string'],
        ];
    }
}
