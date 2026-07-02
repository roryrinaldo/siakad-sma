<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Attendance;
use App\Models\Grade;
use App\Models\ReportCard;
use App\Models\Schedule;
use App\Models\Student;
use App\Models\Teacher;
use Illuminate\Http\UploadedFile;

class SiakadSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_login_and_open_core_pages(): void
    {
        $this->withoutVite();
        $this->seed();

        $response = $this->post('/login', [
            'email' => 'admin@sia.test',
            'password' => 'password',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticated();

        foreach (['/dashboard', '/users', '/students', '/teachers', '/school-classes', '/subjects', '/schedules', '/class-recaps', '/reports'] as $uri) {
            $this->get($uri)->assertOk();
        }

        $this->post('/users', [
            'name' => 'Operator Kepala Sekolah',
            'email' => 'operator.kepsek@sia.test',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role_names' => ['Kepala Sekolah'],
            'child_ids' => [],
        ])->assertRedirect('/users');

        $this->assertTrue(User::where('email', 'operator.kepsek@sia.test')->first()?->hasRole('Kepala Sekolah'));

        $schedule = Schedule::with('schoolClass.students')->firstOrFail();
        $studentCsv = UploadedFile::fake()->createWithContent(
            'students.csv',
            "nis,nisn,name,gender,school_class_id,status,entry_year,email,phone\n260099,0099999999,Rina Import,P,{$schedule->school_class_id},aktif,2026,rina.import@sia.test,0812999000\n"
        );
        $this->post('/students/import', ['file' => $studentCsv])->assertRedirect('/students');
        $this->assertDatabaseHas('students', ['nis' => '260099', 'name' => 'Rina Import']);

        $teacherCsv = UploadedFile::fake()->createWithContent(
            'teachers.csv',
            "nip,nuptk,name,gender,email,phone,is_active\n199001012020011009,88990011,Guru Import,L,guru.import@sia.test,0812888000,1\n"
        );
        $this->post('/teachers/import', ['file' => $teacherCsv])->assertRedirect('/teachers');
        $this->assertDatabaseHas('teachers', ['nip' => '199001012020011009', 'name' => 'Guru Import']);
        $schedule->load('schoolClass.students');

        $this->get('/attendances/bulk/create?schedule_id='.$schedule->id.'&date=2026-09-01')->assertOk();

        $payload = [
            'date' => '2026-09-01',
            'schedule_id' => $schedule->id,
            'attendances' => $schedule->schoolClass->students->values()->map(fn ($student) => [
                'student_id' => $student->id,
                'status' => 'hadir',
                'note' => null,
            ])->all(),
        ];
        $payload['attendances'][0]['status'] = 'terlambat';
        $payload['attendances'][0]['note'] = 'Datang setelah bel masuk.';

        $this->post('/attendances/bulk', $payload)
            ->assertRedirect('/attendances?date=2026-09-01&school_class_id='.$schedule->school_class_id);

        $this->assertSame(
            'terlambat',
            Attendance::where('schedule_id', $schedule->id)
                ->whereDate('date', '2026-09-01')
                ->where('student_id', $schedule->schoolClass->students->first()->id)
                ->value('status')
        );

        $this->get('/grades/bulk/create?schedule_id='.$schedule->id)->assertOk();

        $gradePayload = [
            'schedule_id' => $schedule->id,
            'grades' => $schedule->schoolClass->students->values()->map(fn ($student) => [
                'student_id' => $student->id,
                'assignment_score' => 90,
                'daily_test_score' => 80,
                'midterm_score' => 70,
                'final_exam_score' => 60,
                'practice_score' => 100,
                'attitude_score' => 'Baik',
                'note' => 'Nilai bulk test.',
            ])->all(),
        ];

        $this->post('/grades/bulk', $gradePayload)
            ->assertRedirect('/grades?school_class_id='.$schedule->school_class_id.'&subject_id='.$schedule->subject_id);

        $bulkGrade = Grade::where('student_id', $schedule->schoolClass->students->first()->id)
            ->where('subject_id', $schedule->subject_id)
            ->where('academic_year_id', $schedule->academic_year_id)
            ->where('semester_id', $schedule->semester_id)
            ->firstOrFail();

        $this->assertEquals(76.5, (float) $bulkGrade->final_score);
        $this->assertSame('C', $bulkGrade->predicate);

        $this->get('/report-cards/generate/create')->assertOk();
        $this->post('/report-cards/generate', [
            'school_class_id' => $schedule->school_class_id,
            'academic_year_id' => $schedule->academic_year_id,
            'semester_id' => $schedule->semester_id,
            'homeroom_note' => 'Catatan raport massal.',
            'is_validated' => '1',
        ])->assertRedirect('/report-cards');

        $this->assertSame(
            $schedule->schoolClass->students->count(),
            ReportCard::where('school_class_id', $schedule->school_class_id)
                ->where('academic_year_id', $schedule->academic_year_id)
                ->where('semester_id', $schedule->semester_id)
                ->where('homeroom_note', 'Catatan raport massal.')
                ->where('is_validated', true)
                ->count()
        );

        $reportCard = ReportCard::where('school_class_id', $schedule->school_class_id)->firstOrFail();
        $this->get('/report-cards/'.$reportCard->id.'/pdf')->assertOk();
        $this->assertNotNull($reportCard->fresh()->printed_at);

        $this->get('/class-recaps?school_class_id='.$schedule->school_class_id.'&academic_year_id='.$schedule->academic_year_id.'&semester_id='.$schedule->semester_id)
            ->assertOk()
            ->assertSee('Tervalidasi');
        $this->get('/class-recaps/export/csv?school_class_id='.$schedule->school_class_id.'&academic_year_id='.$schedule->academic_year_id.'&semester_id='.$schedule->semester_id)
            ->assertOk()
            ->assertSee('Tervalidasi');
        $this->get('/class-recaps/export/pdf?school_class_id='.$schedule->school_class_id.'&academic_year_id='.$schedule->academic_year_id.'&semester_id='.$schedule->semester_id)
            ->assertOk();

        $this->get('/reports/students/csv')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $this->get('/reports/students/csv?school_class_id='.$schedule->school_class_id.'&status=aktif')
            ->assertOk()
            ->assertSee('Rina Import');

        $this->get('/reports/grades/csv?school_class_id='.$schedule->school_class_id.'&subject_id='.$schedule->subject_id)
            ->assertOk()
            ->assertSee('Nilai bulk test.');

        $this->get('/reports/students/pdf')->assertOk();
    }

    public function test_role_access_and_data_isolation_are_enforced(): void
    {
        $this->withoutVite();
        $this->seed();

        foreach (['admin@sia.test', 'kepsek@sia.test', 'guru@sia.test', 'wali@sia.test', 'siswa@sia.test', 'ortu@sia.test'] as $email) {
            $this->actingAs(User::where('email', $email)->firstOrFail())
                ->get('/dashboard')
                ->assertOk();
        }

        $principal = User::where('email', 'kepsek@sia.test')->firstOrFail();
        $this->actingAs($principal);
        $this->get('/dashboard')->assertOk();
        $this->get('/reports')->assertOk();
        foreach (['/students', '/schedules', '/attendances', '/grades', '/report-cards', '/announcements'] as $uri) {
            $this->get($uri)->assertForbidden();
        }

        $studentUser = User::where('email', 'siswa@sia.test')->firstOrFail();
        $ownStudentId = $studentUser->student->id;
        $otherReportCard = ReportCard::where('student_id', '!=', $ownStudentId)->firstOrFail();
        $this->actingAs($studentUser);
        $this->get('/grades')->assertOk()->assertDontSee($otherReportCard->student->name);
        $this->get('/report-cards/'.$otherReportCard->id)->assertForbidden();
        $this->get('/report-cards/'.$otherReportCard->id.'/pdf')->assertForbidden();

        $parentUser = User::where('email', 'ortu@sia.test')->firstOrFail();
        $this->actingAs($parentUser);
        $this->get('/report-cards/'.$otherReportCard->id)->assertForbidden();

        $teacherUser = User::where('email', 'guru@sia.test')->firstOrFail();
        $otherTeacherSchedule = Schedule::with('schoolClass.students')->where('teacher_id', '!=', $teacherUser->teacher->id)->firstOrFail();
        $this->actingAs($teacherUser);
        $this->post('/attendances/bulk', [
            'date' => '2026-09-02',
            'schedule_id' => $otherTeacherSchedule->id,
            'attendances' => $otherTeacherSchedule->schoolClass->students->values()->map(fn ($student) => [
                'student_id' => $student->id,
                'status' => 'hadir',
            ])->all(),
        ])->assertForbidden();

        $this->post('/grades/bulk', [
            'schedule_id' => $otherTeacherSchedule->id,
            'grades' => $otherTeacherSchedule->schoolClass->students->values()->map(fn ($student) => [
                'student_id' => $student->id,
                'assignment_score' => 80,
                'daily_test_score' => 80,
                'midterm_score' => 80,
                'final_exam_score' => 80,
                'practice_score' => 80,
            ])->all(),
        ])->assertForbidden();

        $waliUser = User::where('email', 'wali@sia.test')->firstOrFail();
        $foreignClass = \App\Models\SchoolClass::where('homeroom_teacher_id', '!=', $waliUser->teacher->id)
            ->orWhereNull('homeroom_teacher_id')
            ->firstOrFail();
        $schedule = Schedule::firstOrFail();
        $this->actingAs($waliUser);
        $this->post('/report-cards/generate', [
            'school_class_id' => $foreignClass->id,
            'academic_year_id' => $schedule->academic_year_id,
            'semester_id' => $schedule->semester_id,
            'homeroom_note' => 'Tidak boleh masuk.',
        ])->assertForbidden();
    }
}
