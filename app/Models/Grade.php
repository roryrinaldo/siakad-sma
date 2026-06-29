<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['student_id', 'school_class_id', 'subject_id', 'teacher_id', 'academic_year_id', 'semester_id', 'assignment_score', 'daily_test_score', 'midterm_score', 'final_exam_score', 'practice_score', 'attitude_score', 'final_score', 'predicate', 'note'])]
class Grade extends Model
{
    protected static function booted(): void
    {
        static::saving(function (Grade $grade): void {
            $grade->final_score = round(
                ($grade->assignment_score * 0.20)
                + ($grade->daily_test_score * 0.20)
                + ($grade->midterm_score * 0.25)
                + ($grade->final_exam_score * 0.25)
                + ($grade->practice_score * 0.10),
                2
            );
            $grade->predicate = match (true) {
                $grade->final_score >= 90 => 'A',
                $grade->final_score >= 80 => 'B',
                $grade->final_score >= 70 => 'C',
                default => 'D',
            };
        });
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }
}
