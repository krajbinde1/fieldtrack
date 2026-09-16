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

        foreach (['centers', 'leave_requests', 'admission_targets', 'admissions'] as $table) {
            $this->dropForeignKeysOnColumn($table, 'project_id');
        }

        $this->dropIndexIfExists('centers', 'centers_project_id_code_unique', ['project_id']);
        $this->dropIndexIfExists('leave_requests', 'leave_requests_project_id_center_id_index', ['project_id']);
        $this->dropIndexIfExists('admissions', 'admissions_project_id_center_id_index', ['project_id']);
        $this->dropIndexIfExists('admission_targets', 'admission_targets_center_id_project_id_index', ['project_id']);

        if (Schema::hasColumn('centers', 'scheme_id') && ! Schema::hasIndex('centers', 'centers_scheme_id_code_unique')) {
            Schema::table('centers', function (Blueprint $table) {
                $table->unique(['scheme_id', 'code']);
            });
        }

        $this->ensureForeignKey('centers', 'scheme_id', 'schemes');
        $this->ensureForeignKey('leave_requests', 'scheme_id', 'schemes');
        $this->ensureForeignKey('admission_targets', 'scheme_id', 'schemes');

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

        $this->dropForeignKeysOnColumn('centers', 'scheme_id');
        $this->dropIndexIfExists('centers', 'centers_scheme_id_code_unique', ['scheme_id']);

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
        if (! Schema::hasColumn($table, 'scheme_id')) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->foreignId('scheme_id')->nullable()->constrained('schemes')->restrictOnDelete();
            });

            return;
        }

        $this->ensureForeignKey($table, 'scheme_id', 'schemes');
    }

    private function addProjectIdColumn(string $table): void
    {
        if (Schema::hasColumn($table, 'project_id')) {
            $this->ensureForeignKey($table, 'project_id', 'projects');

            return;
        }

        Schema::table($table, function (Blueprint $blueprint) {
            $blueprint->foreignId('project_id')->nullable()->constrained('projects')->restrictOnDelete();
        });
    }

    private function dropProjectId(string $table): void
    {
        $this->dropForeignKeysOnColumn($table, 'project_id');
        $this->dropIndexesContainingColumn($table, 'project_id', ['project_id']);
        $this->dropColumnIfExists($table, 'project_id');
    }

    private function dropSchemeId(string $table): void
    {
        $this->dropForeignKeysOnColumn($table, 'scheme_id');
        $this->dropIndexesContainingColumn($table, 'scheme_id', ['scheme_id']);
        $this->dropColumnIfExists($table, 'scheme_id');
    }

    private function dropColumnIfExists(string $table, string $column): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($column) {
            $blueprint->dropColumn($column);
        });
    }

    private function ensureForeignKey(string $table, string $column, string $referencedTable): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column) || $this->hasForeignKeyOnColumn($table, $column)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($column, $referencedTable) {
            $blueprint->foreign($column)->references('id')->on($referencedTable)->restrictOnDelete();
        });
    }

    /**
     * @param  list<string>  $skipRestoreColumns
     */
    private function dropIndexIfExists(string $table, string $index, array $skipRestoreColumns = []): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasIndex($table, $index)) {
            return;
        }

        $indexColumns = $this->indexColumns($table, $index);
        $droppedForeignKeys = [];

        foreach (Schema::getForeignKeys($table) as $foreignKey) {
            $foreignColumns = array_map('trim', $foreignKey['columns'] ?? []);
            if ($this->indexSupportsForeignKey($indexColumns, $foreignColumns)) {
                $this->dropForeignKey($table, $foreignKey);
                $droppedForeignKeys[] = $foreignKey;
            }
        }

        Schema::table($table, function (Blueprint $blueprint) use ($index) {
            if (str_ends_with($index, '_unique')) {
                $blueprint->dropUnique($index);

                return;
            }

            $blueprint->dropIndex($index);
        });

        foreach ($droppedForeignKeys as $foreignKey) {
            $foreignColumns = array_map('trim', $foreignKey['columns'] ?? []);
            if (array_intersect($foreignColumns, $skipRestoreColumns) !== []) {
                continue;
            }

            $this->restoreForeignKey($table, $foreignKey);
        }
    }

    /**
     * @param  list<string>  $skipRestoreColumns
     */
    private function dropIndexesContainingColumn(string $table, string $column, array $skipRestoreColumns = []): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        foreach (Schema::getIndexes($table) as $index) {
            if (($index['primary'] ?? false) === true) {
                continue;
            }

            $columns = array_map('trim', $index['columns'] ?? []);
            $name = $index['name'] ?? null;
            if (! filled($name) || ! in_array($column, $columns, true)) {
                continue;
            }

            $this->dropIndexIfExists($table, $name, $skipRestoreColumns);
        }
    }

    private function dropForeignKeysOnColumn(string $table, string $column): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        foreach ($this->foreignKeysOnColumn($table, $column) as $foreignKey) {
            $this->dropForeignKey($table, $foreignKey);
        }
    }

    /**
     * @param  array<string, mixed>  $foreignKey
     */
    private function dropForeignKey(string $table, array $foreignKey): void
    {
        $columns = array_map('trim', $foreignKey['columns'] ?? []);
        $name = $foreignKey['name'] ?? null;
        $driver = Schema::getConnection()->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            if (! filled($name)) {
                return;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($name) {
                $blueprint->dropForeign($name);
            });

            return;
        }

        if ($columns === []) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($columns) {
            $blueprint->dropForeign($columns);
        });
    }

    /**
     * @param  array<string, mixed>  $foreignKey
     */
    private function restoreForeignKey(string $table, array $foreignKey): void
    {
        $columns = array_map('trim', $foreignKey['columns'] ?? []);
        $foreignColumns = array_map('trim', $foreignKey['foreign_columns'] ?? []);
        $foreignTable = $foreignKey['foreign_table'] ?? null;

        if ($columns === [] || $foreignColumns === [] || ! filled($foreignTable)) {
            return;
        }

        foreach ($columns as $column) {
            if (! Schema::hasColumn($table, $column)) {
                return;
            }
        }

        if ($this->hasForeignKeyOnColumn($table, $columns[0])) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($foreignKey, $columns, $foreignColumns, $foreignTable) {
            $fk = $blueprint->foreign($columns)->references($foreignColumns)->on($foreignTable);

            match (strtolower((string) ($foreignKey['on_delete'] ?? ''))) {
                'cascade' => $fk->cascadeOnDelete(),
                'set null' => $fk->nullOnDelete(),
                'restrict' => $fk->restrictOnDelete(),
                default => null,
            };

            match (strtolower((string) ($foreignKey['on_update'] ?? ''))) {
                'cascade' => $fk->cascadeOnUpdate(),
                'set null' => $fk->nullOnUpdate(),
                'restrict' => $fk->restrictOnUpdate(),
                default => null,
            };
        });
    }

    private function hasForeignKeyOnColumn(string $table, string $column): bool
    {
        return $this->foreignKeysOnColumn($table, $column) !== [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function foreignKeysOnColumn(string $table, string $column): array
    {
        if (! Schema::hasTable($table)) {
            return [];
        }

        $matches = [];

        foreach (Schema::getForeignKeys($table) as $foreignKey) {
            $columns = array_map('trim', $foreignKey['columns'] ?? []);
            if (in_array($column, $columns, true)) {
                $matches[] = $foreignKey;
            }
        }

        return $matches;
    }

    /**
     * @return list<string>
     */
    private function indexColumns(string $table, string $index): array
    {
        foreach (Schema::getIndexes($table) as $item) {
            $name = $item['name'] ?? null;
            if ($name === $index || strtolower((string) $name) === strtolower($index)) {
                return array_map('trim', $item['columns'] ?? []);
            }
        }

        return [];
    }

    /**
     * @param  list<string>  $indexColumns
     * @param  list<string>  $foreignColumns
     */
    private function indexSupportsForeignKey(array $indexColumns, array $foreignColumns): bool
    {
        if ($indexColumns === [] || $foreignColumns === [] || count($foreignColumns) > count($indexColumns)) {
            return false;
        }

        foreach ($foreignColumns as $position => $column) {
            if (($indexColumns[$position] ?? null) !== $column) {
                return false;
            }
        }

        return true;
    }
};
