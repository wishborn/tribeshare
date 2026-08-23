<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;

/**
 * Generate the VAPID key pair web push needs.
 *
 * Push is skipped entirely while the keys are absent, so this is only needed
 * by a deployment that actually wants it.
 */
class GeneratePushKeysCommand extends Command
{
    protected $signature = 'tribeshare:push-keys';

    protected $description = 'Generate a VAPID key pair for web push';

    public function handle(): int
    {
        $keys = VAPID::createVapidKeys();

        $this->info('Add these to your environment:');
        $this->newLine();
        $this->line('VAPID_PUBLIC_KEY='.$keys['publicKey']);
        $this->line('VAPID_PRIVATE_KEY='.$keys['privateKey']);
        $this->newLine();
        $this->comment('The public key also goes to the browser when it subscribes.');

        return self::SUCCESS;
    }
}
