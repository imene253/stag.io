<?php

namespace App\Notifications;

use App\Models\Application;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class AdminRejectedApplicationNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected Application $application
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'admin_application_rejected',
            'application_id' => $this->application->id,
            'offer_id' => $this->application->offer_id,
            'offer_title' => $this->application->offer?->title,
            'student_id' => $this->application->student?->id,
            'student_name' => $this->application->student?->name,
            'company_id' => $this->application->offer?->company?->id,
            'company_name' => $this->application->offer?->company?->name,
            'admin_note' => $this->application->admin_note,
            'message' => 'The administration rejected this accepted application.',
        ];
    }
}
