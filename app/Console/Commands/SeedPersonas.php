<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class SeedPersonas extends Command
{
    protected $signature = 'app:seed-personas';
    protected $description = 'Create admin and author persona accounts';

    public function handle(): void
    {
        $users = [
            [
                'email'    => 'admin@gadgetdrop.tech',
                'name'     => 'Admin',
                'bio'      => null,
                'voice'    => null,
            ],
            [
                'email'    => 'jake@gadgetdrop.tech',
                'name'     => 'Jake',
                'bio'      => 'Gear tester, coffee drinker, and firm believer that the right gadget can fix almost anything.',
                'voice'    => 'Casual and witty. Short punchy sentences. Uses rhetorical questions and light sarcasm. Speaks directly to the reader as "you". Avoids corporate-speak. Occasional self-deprecating humor. Never uses filler words like "very" or "quite". Gets to the point fast.',
            ],
            [
                'email'    => 'maya@gadgetdrop.tech',
                'name'     => 'Maya',
                'bio'      => 'Tech journalist and hardware obsessive. If it has a spec sheet, Maya has read it twice.',
                'voice'    => 'Thorough but accessible. Explains technical concepts with real-world analogies. Slightly nerdy enthusiasm — gets genuinely excited about clever engineering. Longer, more structured sentences than Jake but never dry. Uses phrases like "here is the thing" and "worth noting". Trusts the reader to handle detail.',
            ],
        ];

        foreach ($users as $data) {
            $user = User::updateOrCreate(
                ['email' => $data['email']],
                [
                    'name'              => $data['name'],
                    'password'          => Hash::make('password'),
                    'email_verified_at' => now(),
                    'bio'               => $data['bio'],
                    'voice'             => $data['voice'],
                ]
            );
            $this->info("  {$user->name} ({$user->email}) — id={$user->id}");
        }

        $this->info('Done.');
    }
}
