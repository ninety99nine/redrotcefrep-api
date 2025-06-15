<?php

namespace App\Console;

use App\Jobs\HideSlowMovingProducts;
use Illuminate\Console\Scheduling\Schedule;
use App\Jobs\AutoBilling\StartAutoBillingSchedules;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        /**
         *  IMPORTANT NOTE:
         *  ---------------
         *
         *  If the job queue appears to dispatch the jobs, but no jobs are being
         *  saved on the database for processing then do the following:
         *
         *  Make sure you have set the "QUEUE_CONNECTION=database" in the .env file.
         *  Remember to clear the cache after changes to the .env file. Consider
         *  running the following commands to reset:
         *
         *  ✅ LOCAL DEVELOPMENT:
         *
         *  stop running the php artisan queue:work
         *  sudo php artisan config:cache
         *  sudo php artisan config:clear
         *  sudo php artisan cache:clear
         *  start running the php artisan queue:work
         *
         *  ✅ PRODUCTION (Supervisor setup):
         *
         *  sudo supervisorctl stop all
         *  sudo php artisan config:cache
         *  sudo php artisan config:clear
         *  sudo php artisan cache:clear
         *  sudo supervisorctl reread
         *  sudo supervisorctl start all
         */
        $schedule->job(new StartAutoBillingSchedules())->everyMinute();

        //  $schedule->job(new HideSlowMovingProducts)->everyMinute()->withoutOverlapping();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
