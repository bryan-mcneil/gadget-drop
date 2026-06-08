<?php

namespace App\Livewire;

use App\Models\Subscriber;
use Illuminate\Support\Str;
use Livewire\Attributes\Validate;
use Livewire\Component;

class JoinTheDrop extends Component
{
    #[Validate('required|email|max:255')]
    public string $email = '';

    /** null | 'success' | 'duplicate' | 'error' */
    public ?string $status = null;

    public function subscribe(): void
    {
        $this->status = null;
        $this->validate();

        if (Subscriber::where('email', $this->email)->exists()) {
            $this->status = 'duplicate';

            return;
        }

        try {
            Subscriber::create([
                'email'      => $this->email,
                'token'      => (string) Str::uuid(),
                'ip_address' => request()->ip(),
            ]);

            $this->status = 'success';
            $this->email  = '';
        } catch (\Throwable $e) {
            $this->status = 'error';
        }
    }

    public function render()
    {
        return view('livewire.join-the-drop');
    }
}
