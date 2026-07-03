<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

#[Fillable(['name', 'email', 'password', 'avatar_url', 'bio', 'voice', 'slug'])]
#[Hidden(['password', 'remember_token', 'voice'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    protected static function booted(): void
    {
        static::creating(fn ($user) => $user->slug ??= Str::slug($user->name));
        static::updating(fn ($user) => $user->slug ??= Str::slug($user->name));
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    /**
     * The single real site author (config site.author). All new content is
     * attributed to this account; falls back to the authenticated user so a
     * misconfigured slug can't block imports.
     */
    public static function siteAuthor(): ?User
    {
        return static::where('slug', config('site.author.slug'))->first()
            ?? auth()->user();
    }
}
