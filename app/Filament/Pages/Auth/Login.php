<?php

namespace App\Filament\Pages\Auth;

use App\Models\AuthenticationLog;
use Filament\Auth\Http\Responses\Contracts\LoginResponse as FilamentLoginResponse;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;

class Login extends BaseLogin
{
    protected ?int $n1 = null;

    protected ?int $n2 = null;

    protected function failureKey(): string
    {
        return 'login.failures:'.(request()->ip() ?? 'unknown');
    }

    protected function shouldShowCaptcha(): bool
    {
        return RateLimiter::tooManyAttempts($this->failureKey(), 5);
    }

    public function mount(): void
    {
        parent::mount();
        if ($this->shouldShowCaptcha()) {
            $this->generateCaptcha();
        }
    }

    protected function generateCaptcha(): void
    {
        $this->n1 = random_int(1, 9);
        $this->n2 = random_int(1, 9);
        session(['login_captcha' => [$this->n1, $this->n2]]);
    }

    public function form(Schema $form): Schema
    {
        $form = parent::form($form);

        // Ensure a captcha pair exists in session when needed
        if ($this->shouldShowCaptcha() && ! session('login_captcha')) {
            $this->generateCaptcha();
        }

        // Always register the component so Livewire tracks its state
        $schema = $form->getComponents();
        $schema[] = TextInput::make('captcha')
            ->label(fn () => $this->shouldShowCaptcha() ? ('Resuelve: '.$this->getCaptchaSumText()) : 'Captcha')
            ->numeric()
            ->rule('integer')
            ->required(fn () => $this->shouldShowCaptcha())
            ->hidden(fn () => ! $this->shouldShowCaptcha());
        $form->schema($schema);

        return $form;
    }

    protected function getCaptchaSumText(): string
    {
        $nums = session('login_captcha');
        if (! $nums || ! is_array($nums) || count($nums) !== 2) {
            if ($this->shouldShowCaptcha()) {
                $this->generateCaptcha();
                $nums = session('login_captcha');
            } else {
                return '';
            }
        }

        return $nums[0].' + '.$nums[1];
    }

    public function authenticate(): ?FilamentLoginResponse
    {
        // Validate CAPTCHA first if required
        if ($this->shouldShowCaptcha()) {
            $nums = session('login_captcha') ?? [null, null];
            $expected = is_array($nums) ? ((int) ($nums[0]) + (int) ($nums[1])) : null;
            $state = $this->form->getState();
            $input = null;
            if (is_array($state) && array_key_exists('captcha', $state)) {
                $input = (int) $state['captcha'];
            } else {
                // Fallback in case of hidden/visibility nuance
                $raw = method_exists($this->form, 'getRawState') ? $this->form->getRawState() : [];
                if (is_array($raw) && array_key_exists('captcha', $raw)) {
                    $input = (int) $raw['captcha'];
                }
            }
            if ($expected === null || $input !== $expected) {
                Notification::make()->title('Captcha incorrecto')->danger()->send();
                RateLimiter::hit($this->failureKey(), 900);
                $this->generateCaptcha();

                return null;
            }
        }

        $response = parent::authenticate();

        if (Auth::check()) {
            $user = Auth::user();

            if ($user !== null) {
                AuthenticationLog::query()->create([
                    'authenticatable_type' => $user::class,
                    'authenticatable_id' => (int) $user->getAuthIdentifier(),
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                    'login_at' => now(),
                    'login_successful' => true,
                    'cleared_by_user' => false,
                ]);
            }

            RateLimiter::clear($this->failureKey());
            session()->forget('login_captcha');
        } else {
            RateLimiter::hit($this->failureKey(), 900);
        }

        return $response;
    }
}
