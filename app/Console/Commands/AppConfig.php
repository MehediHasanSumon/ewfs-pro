<?php

namespace App\Console\Commands;

use Database\Seeders\CategorySeeder;
use Database\Seeders\SystemVoucherCategorySeeder;
use Database\Seeders\SystemVoucherTransactionTypeSeeder;
use Illuminate\Console\Command;
use Illuminate\Database\Seeder;
use Throwable;

class AppConfig extends Command
{
    protected $signature = 'app:config';

    protected $description = 'Synchronize ERP constant master data from application configuration';

    /**
     * @var list<class-string<Seeder>>
     */
    private array $seeders = [
        CategorySeeder::class,
        SystemVoucherCategorySeeder::class,
        SystemVoucherTransactionTypeSeeder::class,
    ];

    public function handle(): int
    {
        $this->components->info('Synchronizing ERP constant master data...');

        try {
            foreach ($this->seeders as $seederClass) {
                $this->components->task(
                    class_basename($seederClass),
                    function () use ($seederClass): void {
                        $seeder = app($seederClass);
                        $seeder->setContainer(app());
                        $seeder->setCommand($this);
                        $seeder->__invoke();
                    }
                );
            }
        } catch (Throwable $exception) {
            report($exception);
            $this->components->error(
                "ERP constant data synchronization failed: {$exception->getMessage()}"
            );

            return self::FAILURE;
        }

        $this->components->info('ERP constant master data synchronized successfully.');

        return self::SUCCESS;
    }
}
