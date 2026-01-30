<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Promo;

class DeactivateExpiredPromos extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:deactivate-expired-promos';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Deactivate promos that have expired';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $count = Promo::where('is_active', true)
            ->whereDate('berlaku_selesai', '<', now())
            ->update(['is_active' => false]);

        $this->info("Successfully deactivated {$count} expired promo(s).");
    }
}
