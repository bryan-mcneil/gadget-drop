<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscriber extends Model
{
    protected $fillable = ['email', 'token', 'ip_address'];

    // Drop Price streak counters (play_streak, win_streak, totals, last_played_on, …)
    // are intentionally NOT fillable — they're updated via explicit update()/increment(),
    // never mass-assigned from client-supplied values.
    protected $casts = [
        'last_played_on' => 'date',
    ];

    public function dropPriceResults(): HasMany
    {
        return $this->hasMany(DropPriceResult::class);
    }
}
