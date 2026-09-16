<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $map = [];

        if (Schema::hasTable('projects')) {
            foreach (DB::table('projects')->orderBy('id')->get() as $project) {
                $existing = null;
                if (filled($project->code)) {
                    $existing = DB::table('schemes')->where('code', $project->code)->first();
                }
                if ($existing === null) {
                    $existing = DB::table('schemes')->where('name', $project->name)->first();
                }

                if ($existing !== null) {
                    $map[(int) $project->id] = (int) $existing->id;

                    continue;
                }

                $map[(int) $project->id] = (int) DB::table('schemes')->insertGetId([
                    'name' => $project->name,
                    'code' => $project->code,
                    'description' => $project->description,
                    'is_active' => (bool) $project->is_active,
                    'created_at' => $project->created_at,
                    'updated_at' => $project->updated_at,
                ]);
            }
        }

        $this->addSchemeIdColumn('centers');
        $this->addSchemeIdColumn('leave_requests');
        $this->addSchemeIdColumn('admission_targets');

        $this->backfillFromProject('centers', $map);
        $this->backfillFromProject('leave_requests', $map);
        $this->backfillFromProject('admission_targets', $map);

        if (Schema::hasColumn('admissions', 'project_id')) {
            foreach (DB::table('admissions')->whereNull('scheme_id')->get() as $row) {
                $schemeId = $map[(int) $row->project_id] ?? null;
                if ($schemeId !== null) {
                    DB::table('admissions')->where('id', $row->id)->update(['scheme_id' => $schemeId]);
                }
            }
        }

        $this->dropIndexIfExists('centers', 'centers_project_id_code_unique');
        $this->dropIndexIfExists('leave_requests', 'leave_requests_project_id_center_id_index');
        $this->dropIndexIfExists('admissions', 'admissions_project_id_center_id_index');
        $this->dropIndexIfExists('admission_targets', 'admission_targets_center_id_project_id_index');

        if (Schema::hasColumn('centers', 'scheme_id') && ! Schema::hasIndex('centers', 'centers_scheme_id_code_unique')) {
            Schema::table('centers', function (Blueprint $table) {
                $table->unique(['scheme_id', 'code']);
            });
        }

        $this->dropProjectId('centers');
        $this->dropProjectId('leave_requests');
        $this->dropProjectId('admission_targets');
        $this->dropProjectId('admissions');
    }

    public function down(): void
    {
        $this->addProjectIdColumn('centers');
        $this->addProjectIdColumn('leave_requests');
        $this->addProjectIdColumn('admission_targets');
        $this->addProjectIdColumn('admissions');

        $this->dropIndexIfExists('centers', 'centers_scheme_id_code_unique');

        if (Schema::hasColumn('centers', 'project_id') && ! Schema::hasIndex('centers', 'centers_project_id_code_unique')) {
            Schema::table('centers', function (Blueprint $table) {
                $table->unique(['project_id', 'code']);
            });
        }

        $this->dropSchemeId('centers');
        $this->dropSchemeId('leave_requests');
        $this->dropSchemeId('admission_targets');
    }

    /**
     * @param  array<int, int>  $map
     */
    private function backfillFromProject(string $table, array $map): void
    {
        if (! Schema::hasColumn($table, 'project_id') || ! Schema::hasColumn($table, 'scheme_id')) {
            return;
        }

        foreach (DB::table($table)->get() as $row) {
            $schemeId = $map[(int) $row->project_id] ?? null;
            if ($schemeId !== null) {
                DB::table($table)->where('id', $row->id)->update(['scheme_id' => $schemeId]);
            }
        }
    }

    private function addSchemeIdColumn(string $table): void
    {
        if (Schema::hasColumn($table, 'scheme_id')) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) {
            $blueprint->foreignId('scheme_id')->nullable()->constrained('schemes')->restrictOnDelete();
        });
    }

    private function addProjectIdColumn(string $table): void
    {
        if (Schema::hasColumn($table, 'project_id')) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) {
            $blueprint->foreignId('project_id')->nullable()->constrained('projects')->restrictOnDelete();
        });
    }

    private function dropProjectId(string $table): void
    {
        if (! Schema::hasColumn($table, 'project_id')) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) {
            $blueprint->dropConstrainedForeignId('project_id');
        });
    }

    private function dropSchemeId(string $table): void
    {
        if (! Schema::hasColumn($table, 'scheme_id')) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) {
            $blueprint->dropConstrainedForeignId('scheme_id');
        });
    }

    private function dropIndexIfExists(string $table, string $index): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasIndex($table, $index)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($index) {
            if (str_ends_with($index, '_unique')) {
                $blueprint->dropUnique($index);

                return;
            }

            $blueprint->dropIndex($index);
        });
    }
};
