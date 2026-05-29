<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\PasswordExpirationNotice;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:notify-password-expiration')]
#[Description('Notify users whose passwords are about to expire')]
class NotifyPasswordExpiration extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $maxDays = config('auth.password_expires_days', 90);
        $notifyDaysBefore = config('auth.password_notify_days_before', 7);

        $targetDays = $maxDays - $notifyDaysBefore;

        $targetDate = now()->subDays($targetDays)->startOfDay();

        $users = User::whereDate('password_changed_at', $targetDate)
            ->orWhere(function ($query) use ($targetDate) {
                $query->whereNull('password_changed_at')
                    ->whereDate('created_at', $targetDate);
            })
            ->get();

        foreach ($users as $user) {
            $user->notify(new PasswordExpirationNotice($notifyDaysBefore));
            $this->info("Notified user: {$user->email}");
        }

        $this->info('Password expiration notifications sent successfully.');
    }
}
