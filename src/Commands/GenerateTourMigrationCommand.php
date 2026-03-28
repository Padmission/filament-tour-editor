<?php

namespace Padmission\FilamentTourEditor\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Padmission\FilamentTourEditor\Models\Tour;

class GenerateTourMigrationCommand extends Command
{
    protected $signature = 'tour:generate-migration
        {--mode=add : Import mode: "add" (updateOrCreate) or "replace" (truncate + insert)}
        {--active-only : Only include active tours}';
    protected $description = 'Generate a migration file from the tours currently in the database';

    public function handle(): int
    {
        $mode = $this->option('mode');

        if (! in_array($mode, ['add', 'replace'])) {
            $this->error("Invalid mode \"{$mode}\". Use \"add\" or \"replace\".");

            return self::FAILURE;
        }

        $query = Tour::query()->orderBy('sort_order');

        if ($this->option('active-only')) {
            $query->active();
        }

        $tours = $query->get();

        if ($tours->isEmpty()) {
            $this->warn('No tours found in the database.');

            return self::SUCCESS;
        }

        $migrationContent = $this->buildMigrationContent($tours, $mode);

        $timestamp = Carbon::now()->format('Y_m_d_His');
        $filename = "{$timestamp}_seed_tours.php";
        $path = database_path("migrations/{$filename}");

        File::put($path, $migrationContent);

        $this->info("Migration generated: database/migrations/{$filename}");
        $this->info("Mode: {$mode} | Tours: {$tours->count()}");

        return self::SUCCESS;
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Collection<int, Tour>  $tours
     */
    protected function buildMigrationContent($tours, string $mode): string
    {
        $tourArrays = $tours->map(fn (Tour $tour): array => [
            'name' => $tour->name,
            'description' => $tour->description,
            'route' => $tour->route,
            'json_config' => $tour->json_config,
            'is_active' => $tour->is_active,
            'sort_order' => $tour->sort_order,
        ])->all();

        $toursExport = $this->exportArray($tourArrays);

        if ($mode === 'replace') {
            return $this->replaceTemplate($toursExport);
        }

        return $this->addTemplate($toursExport);
    }

    protected function addTemplate(string $toursExport): string
    {
        return <<<PHP
        <?php

        use Illuminate\Database\Migrations\Migration;
        use Illuminate\Support\Facades\DB;

        return new class extends Migration
        {
            public function up(): void
            {
                \$tours = {$toursExport};

                \$now = now();

                foreach (\$tours as \$tour) {
                    DB::table('tours')->updateOrInsert(
                        ['name' => \$tour['name'], 'route' => \$tour['route']],
                        array_merge(\$tour, [
                            'json_config' => json_encode(\$tour['json_config']),
                            'updated_at' => \$now,
                        ]),
                    );
                }
            }
        };

        PHP;
    }

    protected function replaceTemplate(string $toursExport): string
    {
        return <<<PHP
        <?php

        use Illuminate\Database\Migrations\Migration;
        use Illuminate\Support\Facades\DB;

        return new class extends Migration
        {
            public function up(): void
            {
                DB::table('tours')->truncate();

                \$tours = {$toursExport};

                \$now = now();

                foreach (\$tours as \$tour) {
                    DB::table('tours')->insert(array_merge(\$tour, [
                        'json_config' => json_encode(\$tour['json_config']),
                        'created_at' => \$now,
                        'updated_at' => \$now,
                    ]));
                }
            }
        };

        PHP;
    }

    /**
     * Export an array as a formatted PHP array string.
     *
     * @param  array<int, array<string, mixed>>  $data
     */
    protected function exportArray(array $data): string
    {
        return $this->varExportFormatted($data, 2);
    }

    /**
     * @param  mixed  $value
     */
    protected function varExportFormatted($value, int $indent = 0): string
    {
        $indentStr = str_repeat('    ', $indent);
        $innerIndent = str_repeat('    ', $indent + 1);

        if (is_array($value)) {
            if ($value === []) {
                return '[]';
            }

            $isSequential = array_keys($value) === range(0, count($value) - 1);
            $lines = [];

            foreach ($value as $key => $item) {
                $exportedValue = $this->varExportFormatted($item, $indent + 1);

                if ($isSequential) {
                    $lines[] = "{$innerIndent}{$exportedValue}";
                } else {
                    $exportedKey = var_export($key, true);
                    $lines[] = "{$innerIndent}{$exportedKey} => {$exportedValue}";
                }
            }

            return "[\n" . implode(",\n", $lines) . ",\n{$indentStr}]";
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_null($value)) {
            return 'null';
        }

        return var_export($value, true);
    }
}
