<?php

use App\Http\Controllers\AcademicYearController;
use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GradeController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportCardController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\SchoolClassController;
use App\Http\Controllers\SemesterController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\SubjectController;
use App\Http\Controllers\TeacherController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('dashboard'));

Route::middleware('guest')->group(function (): void {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store']);
});

Route::middleware('auth')->group(function (): void {
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('profile/password', [ProfileController::class, 'password'])->name('profile.password');

    Route::middleware('role.any:Admin')->group(function (): void {
        Route::resources([
            'students' => StudentController::class,
            'teachers' => TeacherController::class,
            'school-classes' => SchoolClassController::class,
            'subjects' => SubjectController::class,
            'academic-years' => AcademicYearController::class,
            'semesters' => SemesterController::class,
        ]);
        Route::resource('schedules', ScheduleController::class)->except(['index']);
    });

    Route::get('schedules', [ScheduleController::class, 'index'])
        ->name('schedules.index')
        ->middleware('role.any:Admin|Kepala Sekolah|Guru|Wali Kelas|Siswa|Orang Tua');

    Route::resource('attendances', AttendanceController::class)
        ->only(['index'])
        ->middleware('role.any:Admin|Kepala Sekolah|Guru|Wali Kelas|Siswa|Orang Tua');
    Route::resource('attendances', AttendanceController::class)
        ->except(['index', 'show'])
        ->middleware('role.any:Admin|Guru');

    Route::resource('grades', GradeController::class)
        ->only(['index'])
        ->middleware('role.any:Admin|Kepala Sekolah|Guru|Wali Kelas|Siswa|Orang Tua');
    Route::resource('grades', GradeController::class)
        ->except(['index', 'show'])
        ->middleware('role.any:Admin|Guru');

    Route::resource('report-cards', ReportCardController::class)
        ->only(['index', 'show'])
        ->middleware('role.any:Admin|Kepala Sekolah|Wali Kelas|Siswa|Orang Tua');
    Route::resource('report-cards', ReportCardController::class)
        ->except(['index', 'show'])
        ->middleware('role.any:Admin|Wali Kelas');
    Route::patch('report-cards/{report_card}/validate', [ReportCardController::class, 'validateCard'])
        ->name('report-cards.validate')
        ->middleware('role.any:Admin|Wali Kelas');

    Route::resource('announcements', AnnouncementController::class)
        ->only(['index', 'show'])
        ->middleware('role.any:Admin|Kepala Sekolah|Guru|Wali Kelas|Siswa|Orang Tua');
    Route::resource('announcements', AnnouncementController::class)
        ->except(['index', 'show'])
        ->middleware('role.any:Admin|Guru');

    Route::middleware('role.any:Admin|Kepala Sekolah|Wali Kelas|Guru')->group(function (): void {
        Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('reports/students/{format}', [ReportController::class, 'students'])->name('reports.students');
        Route::get('reports/teachers/{format}', [ReportController::class, 'teachers'])->name('reports.teachers');
        Route::get('reports/attendances/{format}', [ReportController::class, 'attendances'])->name('reports.attendances');
        Route::get('reports/grades/{format}', [ReportController::class, 'grades'])->name('reports.grades');
        Route::get('reports/report-cards/{format}', [ReportController::class, 'reportCards'])->name('reports.report-cards');
    });
});
