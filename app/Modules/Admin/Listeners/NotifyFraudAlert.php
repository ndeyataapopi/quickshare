<?php

namespace App\Modules\Admin\Listeners;

use App\Modules\Admin\Events\FraudAlert;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class NotifyFraudAlert
{
    public function handle(FraudAlert $event): void
    {
        $flag = $event->fraudFlag;
        $subject = $event->subject;

        Log::warning('Fraud Alert', [
            'flag_id' => $flag->id,
            'flag_type' => $flag->flag_type,
            'severity' => $flag->severity,
            'subject_type' => $flag->subject_type,
            'subject_id' => $flag->subject_id,
            'subject_email' => $subject->email,
            'alert_type' => $event->alertType,
            'risk_score' => $flag->risk_score,
        ]);

        // In production, send email to compliance team
        // Mail::to(config('fraud.compliance_email'))->send(new FraudAlertMail($flag));

        // Send Slack/Teams notification for critical alerts
        if ($flag->isCritical()) {
            $this->sendUrgentNotification($flag, $subject);
        }
    }

    protected function sendUrgentNotification($flag, $subject): void
    {
        // Integration with Slack, Teams, PagerDuty, etc.
        Log::critical('URGENT: Critical fraud flag detected', [
            'flag_id' => $flag->id,
            'subject_email' => $subject->email,
            'description' => $flag->description,
        ]);
    }
}
