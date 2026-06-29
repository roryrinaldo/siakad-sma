<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Announcement;
use App\Models\Attendance;
use App\Models\Grade;
use App\Models\ReportCard;
use App\Models\Schedule;
use App\Models\SchoolClass;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use Illuminate\Database\Seeder;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $roles = ['Admin', 'Kepala Sekolah', 'Guru', 'Wali Kelas', 'Siswa', 'Orang Tua'];
        $permissions = [
            'manage master data',
            'manage users',
            'manage schedules',
            'manage attendances',
            'manage grades',
            'manage report cards',
            'manage announcements',
            'view reports',
            'export reports',
            'view own academic data',
            'view child academic data',
        ];

        collect($permissions)->each(fn (string $permission) => Permission::firstOrCreate([
            'name' => $permission,
            'guard_name' => 'web',
        ]));

        collect($roles)->each(fn (string $role) => Role::firstOrCreate([
            'name' => $role,
            'guard_name' => 'web',
        ]));

        Role::findByName('Admin')->syncPermissions($permissions);
        Role::findByName('Kepala Sekolah')->syncPermissions(['view reports', 'export reports']);
        Role::findByName('Guru')->syncPermissions(['manage attendances', 'manage grades', 'manage announcements']);
        Role::findByName('Wali Kelas')->syncPermissions(['manage report cards', 'view reports']);
        Role::findByName('Siswa')->syncPermissions(['view own academic data']);
        Role::findByName('Orang Tua')->syncPermissions(['view child academic data']);

        $admin = User::firstOrCreate(
            ['email' => 'admin@sia.test'],
            ['name' => 'Administrator SIA SMA', 'password' => 'password']
        );
        $admin->syncRoles(['Admin']);

        $principal = User::firstOrCreate(
            ['email' => 'kepsek@sia.test'],
            ['name' => 'Kepala Sekolah', 'password' => 'password']
        );
        $principal->syncRoles(['Kepala Sekolah']);

        $academicYear = AcademicYear::updateOrCreate(['year' => '2026/2027'], ['is_active' => true]);
        $semester = Semester::updateOrCreate(
            ['academic_year_id' => $academicYear->id, 'name' => 'Ganjil'],
            ['starts_on' => '2026-07-13', 'ends_on' => '2026-12-18', 'is_active' => true]
        );

        $math = Subject::updateOrCreate(['code' => 'MAT'], ['name' => 'Matematika', 'group' => 'Wajib', 'is_active' => true]);
        $indo = Subject::updateOrCreate(['code' => 'BIN'], ['name' => 'Bahasa Indonesia', 'group' => 'Wajib', 'is_active' => true]);
        $physics = Subject::updateOrCreate(['code' => 'FIS'], ['name' => 'Fisika', 'group' => 'Peminatan', 'is_active' => true]);

        $teacherUser = User::firstOrCreate(
            ['email' => 'guru@sia.test'],
            ['name' => 'Budi Santoso', 'password' => 'password']
        );
        $teacherUser->syncRoles(['Guru']);

        $homeroomUser = User::firstOrCreate(
            ['email' => 'wali@sia.test'],
            ['name' => 'Siti Rahma', 'password' => 'password']
        );
        $homeroomUser->syncRoles(['Guru', 'Wali Kelas']);

        $teacher = Teacher::updateOrCreate(
            ['email' => 'guru@sia.test'],
            ['user_id' => $teacherUser->id, 'nip' => '197901012006041001', 'nuptk' => '1234567890123456', 'name' => 'Budi Santoso', 'gender' => 'L', 'phone' => '081234567001', 'is_active' => true]
        );
        $homeroomTeacher = Teacher::updateOrCreate(
            ['email' => 'wali@sia.test'],
            ['user_id' => $homeroomUser->id, 'nip' => '198305052010012002', 'nuptk' => '2234567890123456', 'name' => 'Siti Rahma', 'gender' => 'P', 'phone' => '081234567002', 'is_active' => true]
        );
        $teacher->subjects()->syncWithoutDetaching([$math->id, $physics->id]);
        $homeroomTeacher->subjects()->syncWithoutDetaching([$indo->id]);

        $class = SchoolClass::updateOrCreate(
            ['name' => 'X IPA 1', 'academic_year_id' => $academicYear->id],
            ['level' => 'X', 'major' => 'IPA', 'homeroom_teacher_id' => $homeroomTeacher->id]
        );
        $classTwo = SchoolClass::updateOrCreate(
            ['name' => 'XI IPS 1', 'academic_year_id' => $academicYear->id],
            ['level' => 'XI', 'major' => 'IPS', 'homeroom_teacher_id' => null]
        );

        $studentUser = User::firstOrCreate(
            ['email' => 'siswa@sia.test'],
            ['name' => 'Andi Pratama', 'password' => 'password']
        );
        $studentUser->syncRoles(['Siswa']);

        $student = Student::updateOrCreate(
            ['nis' => '260001'],
            [
                'user_id' => $studentUser->id,
                'school_class_id' => $class->id,
                'nisn' => '0061234567',
                'name' => 'Andi Pratama',
                'gender' => 'L',
                'birth_place' => 'Bandung',
                'birth_date' => '2010-05-11',
                'religion' => 'Islam',
                'address' => 'Jl. Pendidikan No. 1',
                'phone' => '081234567101',
                'email' => 'siswa@sia.test',
                'entry_year' => 2026,
                'status' => 'aktif',
            ]
        );

        $secondStudent = Student::updateOrCreate(
            ['nis' => '260002'],
            [
                'school_class_id' => $class->id,
                'nisn' => '0061234568',
                'name' => 'Dewi Lestari',
                'gender' => 'P',
                'entry_year' => 2026,
                'status' => 'aktif',
            ]
        );

        $parent = User::firstOrCreate(
            ['email' => 'ortu@sia.test'],
            ['name' => 'Orang Tua Andi', 'password' => 'password']
        );
        $parent->syncRoles(['Orang Tua']);
        $parent->children()->syncWithoutDetaching([$student->id]);

        $schedule = Schedule::updateOrCreate(
            [
                'school_class_id' => $class->id,
                'subject_id' => $math->id,
                'teacher_id' => $teacher->id,
                'day' => 'Senin',
                'starts_at' => '07:00',
            ],
            [
                'academic_year_id' => $academicYear->id,
                'semester_id' => $semester->id,
                'ends_at' => '08:30',
                'room' => 'Ruang 10A',
            ]
        );

        Schedule::updateOrCreate(
            [
                'school_class_id' => $class->id,
                'subject_id' => $indo->id,
                'teacher_id' => $homeroomTeacher->id,
                'day' => 'Selasa',
                'starts_at' => '08:30',
            ],
            [
                'academic_year_id' => $academicYear->id,
                'semester_id' => $semester->id,
                'ends_at' => '10:00',
                'room' => 'Ruang 10A',
            ]
        );

        foreach ([$student, $secondStudent] as $seedStudent) {
            Attendance::updateOrCreate(
                ['date' => now()->toDateString(), 'student_id' => $seedStudent->id, 'schedule_id' => $schedule->id],
                ['school_class_id' => $class->id, 'teacher_id' => $teacher->id, 'status' => 'hadir']
            );

            Grade::updateOrCreate(
                ['student_id' => $seedStudent->id, 'subject_id' => $math->id, 'academic_year_id' => $academicYear->id, 'semester_id' => $semester->id],
                [
                    'school_class_id' => $class->id,
                    'teacher_id' => $teacher->id,
                    'assignment_score' => 85,
                    'daily_test_score' => 82,
                    'midterm_score' => 88,
                    'final_exam_score' => 86,
                    'practice_score' => 90,
                    'attitude_score' => 'Baik',
                ]
            );

            ReportCard::updateOrCreate(
                ['student_id' => $seedStudent->id, 'academic_year_id' => $academicYear->id, 'semester_id' => $semester->id],
                ['school_class_id' => $class->id, 'homeroom_note' => 'Pertahankan kedisiplinan dan semangat belajar.', 'is_validated' => false]
            );
        }

        Announcement::updateOrCreate(
            ['title' => 'Awal Tahun Ajaran 2026/2027'],
            [
                'created_by' => $admin->id,
                'body' => 'Kegiatan belajar mengajar dimulai sesuai jadwal yang telah ditetapkan.',
                'target_role' => null,
                'target_class_id' => null,
                'published_at' => now(),
                'status' => 'publish',
            ]
        );
    }
}
