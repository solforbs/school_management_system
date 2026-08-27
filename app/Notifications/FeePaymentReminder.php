<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;
use Modules\FeeManagement\Models\Fee;

class FeePaymentReminder extends Notification
{
    use Queueable;

    /**
     * @param  Collection<int, Fee>  $fees  This parent's outstanding fees (any institution/student mix)
     */
    public function __construct(private readonly Collection $fees) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $total = $this->fees->sum('balance');

        $mail = (new MailMessage)
            ->subject('Fee Payment Reminder')
            ->greeting('Hello '.$notifiable->name.',')
            ->line('This is a friendly reminder that the following fee balance(s) are outstanding:');

        foreach ($this->fees as $fee) {
            $studentName = $fee->student?->name ?? 'Student';
            $due = $fee->due_date ? ' (due '.$fee->due_date->format('M j, Y').')' : '';
            $mail->line("- {$studentName} — {$fee->title}: KES ".number_format($fee->balance, 2).$due);
        }

        return $mail
            ->line('Total outstanding: KES '.number_format($total, 2))
            // A week, because that is exactly when the next reminder goes
            // out (Monday, see routes/console.php). Asking for action by
            // then makes the two agree instead of nagging someone who was
            // never given a deadline to miss.
            ->line('Kindly settle this balance within the next seven days. If you have already paid, please disregard this message.');
    }

    public function toSms(): string
    {
        $total = number_format($this->fees->sum('balance'), 2);
        $names = $this->fees->pluck('student.name')->filter()->unique()->implode(', ');

        return "Fee reminder: KES {$total} is outstanding for {$names}. Kindly settle within the next 7 days. If you have already paid, please ignore this message.";
    }
}
