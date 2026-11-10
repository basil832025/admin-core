<?php

namespace Database\Seeders;

use App\Services\Catalog\PerfumeExcelImportService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DuxiAromaFamilySeeder extends Seeder
{
    public function run(PerfumeExcelImportService $import): void
    {
        $connection = DB::connection();

        if (config('project.name') !== 'duxi'
            || $connection->getDriverName() !== 'mysql'
            || $connection->getDatabaseName() !== 'duxi') {
            throw new RuntimeException('This seeder requires the DUXI project and the duxi MySQL database.');
        }

        $import->normalizeAromaFamily();
    }
}
