<?php

namespace App\Console\Commands;

use App\Mail\WatchClosingMail;
use App\Mail\WatchDropMail;
use App\Models\PriceWatch;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Hourly sweep over active price watches. Two passes, both idempotent by
 * stamp (safe to run any number of times):
 *
 *  1. Drop alerts — purchase_price minus the current tracked price beats
 *     max(min_drop_abs, purchase_price * min_drop_pct) → one WatchDropMail,
 *     stamp notified_at (active() excludes it forever after).
 *  2. Closing-window courtesy — expires within closing_notice_days and never
 *     alerted → one WatchClosingMail, stamp closing_mail_sent_at. Runs after
 *     the drop pass, so a watch alerted this very run is already excluded.
 *
 * No price fetching happens here — this only reads products.price as
 * refreshed by prices:refresh / the /admin/prices manual pass.
 */
class CheckPriceWatches extends Command
{
    protected $signature = 'watches:check';

    protected $description = 'Send return-window drop alerts (and closing-window summaries) for active price watches.';

    public function handle(): int
    {
        $scanned = 0;
        $triggered = 0;

        $minAbs = (float) config('watch.min_drop_abs', 5.00);
        $minPct = (float) config('watch.min_drop_pct', 0.03);

        // chunkById stays correct while rows are stamped mid-iteration (it
        // pages by id, not offset — the documented safe way to mutate in-loop).
        PriceWatch::active()
            ->whereHas('product', fn ($q) => $q->whereNotNull('price'))
            ->with('product')
            ->chunkById(100, function ($watches) use (&$scanned, &$triggered, $minAbs, $minPct) {
                foreach ($watches as $watch) {
                    $scanned++;

                    $paid = (float) $watch->purchase_price;
                    $current = (float) $watch->product->price;
                    $threshold = max($minAbs, $paid * $minPct);

                    if ($paid - $current < $threshold) {
                        continue;
                    }

                    Mail::to($watch->email)->send(new WatchDropMail($watch));
                    $watch->forceFill(['notified_at' => now()])->save();
                    $triggered++;

                    $this->line("  drop alert: {$watch->email} — {$watch->product->name} \${$current} (paid \${$paid})");
                }
            });

        $closing = 0;
        $noticeDays = (int) config('watch.closing_notice_days', 3);

        PriceWatch::active()
            ->whereNull('closing_mail_sent_at')
            ->whereDate('expires_at', '<=', today()->addDays($noticeDays))
            ->with('product')
            ->chunkById(100, function ($watches) use (&$closing) {
                foreach ($watches as $watch) {
                    Mail::to($watch->email)->send(new WatchClosingMail($watch));
                    $watch->forceFill(['closing_mail_sent_at' => now()])->save();
                    $closing++;

                    $this->line("  closing summary: {$watch->email} — {$watch->product->name}");
                }
            });

        // Greppable by /gd-health: one line per run with the counts.
        Log::info("watches:check scanned={$scanned} triggered={$triggered} closing={$closing}");
        $this->info("Scanned {$scanned} active watch(es): {$triggered} drop alert(s), {$closing} closing summar(ies).");

        return self::SUCCESS;
    }
}
