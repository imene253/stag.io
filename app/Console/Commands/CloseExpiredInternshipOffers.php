<?php

namespace App\Console\Commands;

use App\Models\InternshipOffer;
use Illuminate\Console\Command;

class CloseExpiredInternshipOffers extends Command
{
    protected $signature = 'offers:close-expired';

    protected $description = 'Close internship offers whose application deadline has passed';

    public function handle(): int
    {
        $closed = InternshipOffer::closeExpired();

        $this->info("Closed {$closed} expired internship offer(s).");

        return self::SUCCESS;
    }
}
