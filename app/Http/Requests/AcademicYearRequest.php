<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AcademicYearRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['Admin']) ?? false;
    }

    public function rules(): array
    {
        $id = $this->route('academic_year')?->id;

        return [
            'year' => ['required', 'string', 'max:20', Rule::unique('academic_years', 'year')->ignore($id)],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
