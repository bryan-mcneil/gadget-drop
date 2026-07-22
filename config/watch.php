<?php

/*
 | Post-purchase price watch (docs/plans/03-post-purchase-watch.md).
 | Single source for the return-window math — PriceWatch::booted() computes
 | expires_at from window_days, and watches:check applies the drop threshold
 | max(min_drop_abs, purchase_price * min_drop_pct). Tunable without code.
 */
return [

    // Amazon's standard return window, in days from the purchase date.
    'window_days' => (int) env('WATCH_WINDOW_DAYS', 30),

    // A drop only triggers an alert when it beats BOTH floors: at least this
    // many dollars…
    'min_drop_abs' => (float) env('WATCH_MIN_DROP_ABS', 5.00),

    // …or this fraction of the purchase price, whichever is larger. Keeps
    // trivial $5 drops on a $900 laptop from firing a "return & rebuy" mail.
    'min_drop_pct' => (float) env('WATCH_MIN_DROP_PCT', 0.03),

    // Days before expiry that the closing-window courtesy mail goes out.
    'closing_notice_days' => (int) env('WATCH_CLOSING_NOTICE_DAYS', 3),

];
