<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $columnsToAdd = array_values(array_filter([
            ! Schema::hasColumn('applications', 'selected_at') ? 'selected_at' : null,
            ! Schema::hasColumn('applications', 'internship_starts_at') ? 'internship_starts_at' : null,
            ! Schema::hasColumn('applications', 'internship_ends_at') ? 'internship_ends_at' : null,
        ]));

        if (! empty($columnsToAdd)) {
            Schema::table('applications', function (Blueprint $table) use ($columnsToAdd): void {
                if (in_array('selected_at', $columnsToAdd, true)) {
                    $table->timestamp('selected_at')->nullable()->after('admin_note');
                }

                if (in_array('internship_starts_at', $columnsToAdd, true)) {
                    $table->date('internship_starts_at')->nullable()->after('selected_at');
                }

                if (in_array('internship_ends_at', $columnsToAdd, true)) {
                    $table->date('internship_ends_at')->nullable()->after('internship_starts_at');
                }
            });
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement(
                "ALTER TABLE applications MODIFY COLUMN status ENUM('pending','accepted','refused','validated','rejected','selected') NOT NULL DEFAULT 'pending'"
            );
        } elseif ($driver === 'pgsql') {
            $constraints = DB::select(<<<'SQL'
SELECT pc.conname
FROM pg_constraint pc
JOIN pg_class t ON t.oid = pc.conrelid
WHERE t.relname = 'applications'
  AND pc.contype = 'c'
  AND pg_get_constraintdef(pc.oid) ILIKE '%status%'
SQL);

            foreach ($constraints as $constraint) {
                $name = $constraint->conname ?? null;

                if ($name) {
                    DB::statement(sprintf(
                        'ALTER TABLE applications DROP CONSTRAINT IF EXISTS "%s"',
                        str_replace('"', '""', $name)
                    ));
                }
            }

            DB::statement(
                "ALTER TABLE applications ADD CONSTRAINT applications_status_check CHECK (status IN ('pending','accepted','refused','validated','rejected','selected'))"
            );
        }
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement(
                "ALTER TABLE applications MODIFY COLUMN status ENUM('pending','accepted','refused','validated','rejected') NOT NULL DEFAULT 'pending'"
            );
        } elseif ($driver === 'pgsql') {
            $constraints = DB::select(<<<'SQL'
SELECT pc.conname
FROM pg_constraint pc
JOIN pg_class t ON t.oid = pc.conrelid
WHERE t.relname = 'applications'
  AND pc.contype = 'c'
  AND pg_get_constraintdef(pc.oid) ILIKE '%status%'
SQL);

            foreach ($constraints as $constraint) {
                $name = $constraint->conname ?? null;

                if ($name) {
                    DB::statement(sprintf(
                        'ALTER TABLE applications DROP CONSTRAINT IF EXISTS "%s"',
                        str_replace('"', '""', $name)
                    ));
                }
            }

            DB::statement(
                "ALTER TABLE applications ADD CONSTRAINT applications_status_check CHECK (status IN ('pending','accepted','refused','validated','rejected'))"
            );
        }

        $columnsToDrop = array_values(array_filter([
            Schema::hasColumn('applications', 'selected_at') ? 'selected_at' : null,
            Schema::hasColumn('applications', 'internship_starts_at') ? 'internship_starts_at' : null,
            Schema::hasColumn('applications', 'internship_ends_at') ? 'internship_ends_at' : null,
        ]));

        if (! empty($columnsToDrop)) {
            Schema::table('applications', function (Blueprint $table) use ($columnsToDrop): void {
                $table->dropColumn($columnsToDrop);
            });
        }
    }
};
