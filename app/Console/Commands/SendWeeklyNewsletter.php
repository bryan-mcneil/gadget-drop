<?php

namespace App\Console\Commands;

use App\Mail\WeeklyDigest;
use App\Models\Subscriber;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendWeeklyNewsletter extends Command
{
    protected $signature = 'newsletter:send';

    protected $description = 'Send the weekly digest to all subscribers';

    public function handle(): void
    {
        $this->info('Starting process...');

        $subscribers = Subscriber::all();

        if ($subscribers->isEmpty()) {
            $this->info('No subscribers — nothing sent.');

            return;
        }

        $this->info("Sending to {$subscribers->count()} subscribers…");
        $bar = $this->output->createProgressBar($subscribers->count());
        $bar->start();

        foreach ($subscribers as $subscriber) {
            Mail::to($subscriber->email)->send(new WeeklyDigest(
                unsubscribeUrl: url('/unsubscribe/'.$subscriber->token)
            ));
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('Weekly digest sent successfully.');
    }
}
