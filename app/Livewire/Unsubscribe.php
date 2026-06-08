<?php

namespace App\Livewire;

use App\Models\Subscriber;
use Livewire\Attributes\Validate;
use Livewire\Component;

class Unsubscribe extends Component
{
    #[Validate('required|email|max:255')]
    public string $email = '';

    /** null | 'success' | 'not_found' | 'error' */
    public ?string $result = null;

    public function mount(?string $status = null): void
    {
        $this->result = $status;
    }

    public function remove(): void
    {
        $this->result = null;
        $this->validate();

        try {
            $deleted = Subscriber::where('email', $this->email)->delete();

            if ($deleted) {
                $this->result = 'success';
                $this->email  = '';
            } else {
                $this->result = 'not_found';
            }
        } catch (\Throwable $e) {
            $this->result = 'error';
        }
    }

    public function render()
    {
        return view('livewire.unsubscribe');
    }
}
