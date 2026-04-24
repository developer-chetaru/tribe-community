<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('
                DELETE h1 FROM happy_indexes h1
                INNER JOIN happy_indexes h2
                WHERE h1.id > h2.id
                  AND h1.user_id = h2.user_id
                  AND DATE(h1.created_at) = DATE(h2.created_at)
            ');

            if (! Schema::hasColumn('happy_indexes', 'submission_date')) {
                DB::statement('
                    ALTER TABLE happy_indexes
                    ADD COLUMN submission_date DATE
                    GENERATED ALWAYS AS (DATE(created_at)) VIRTUAL
                ');
            }

            $hasIndex = DB::selectOne(
                "SELECT 1 AS ok FROM information_schema.statistics
                 WHERE table_schema = DATABASE()
                   AND table_name = 'happy_indexes'
                   AND index_name = 'unique_user_daily_submission'
                 LIMIT 1"
            );

            if (! $hasIndex) {
                Schema::table('happy_indexes', function (Blueprint $table) {
                    $table->unique(['user_id', 'submission_date'], 'unique_user_daily_submission');
                });
            }
        } elseif ($driver === 'sqlite') {
            $seen = [];
            $toDelete = [];
            foreach (DB::table('happy_indexes')->orderBy('id')->get(['id', 'user_id', 'created_at']) as $row) {
                $day = \Carbon\Carbon::parse($row->created_at)->format('Y-m-d');
                $key = $row->user_id.'|'.$day;
                if (isset($seen[$key])) {
                    $toDelete[] = $row->id;
                } else {
                    $seen[$key] = true;
                }
            }
            foreach (array_chunk($toDelete, 500) as $chunk) {
                if ($chunk !== []) {
                    DB::table('happy_indexes')->whereIn('id', $chunk)->delete();
                }
            }

            $indexes = DB::select("SELECT name FROM sqlite_master WHERE type='index' AND name='unique_user_daily_submission'");
            if ($indexes === []) {
                DB::statement('CREATE UNIQUE INDEX unique_user_daily_submission ON happy_indexes (user_id, date(created_at))');
            }
        }
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            $hasIndex = DB::selectOne(
                "SELECT 1 AS ok FROM information_schema.statistics
                 WHERE table_schema = DATABASE()
                   AND table_name = 'happy_indexes'
                   AND index_name = 'unique_user_daily_submission'
                 LIMIT 1"
            );
            if ($hasIndex) {
                Schema::table('happy_indexes', function (Blueprint $table) {
                    $table->dropUnique('unique_user_daily_submission');
                });
            }
            if (Schema::hasColumn('happy_indexes', 'submission_date')) {
                Schema::table('happy_indexes', function (Blueprint $table) {
                    $table->dropColumn('submission_date');
                });
            }
        } elseif ($driver === 'sqlite') {
            DB::statement('DROP INDEX IF EXISTS unique_user_daily_submission');
        }
    }
};
