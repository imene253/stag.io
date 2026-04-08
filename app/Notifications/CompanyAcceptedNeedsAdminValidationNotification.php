<?php

namespace App\Notifications;

use App\Models\Application;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class CompanyAcceptedNeedsAdminValidationNotification extends Notification
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
            'kind' => 'admin_validation_required',
            'application_id' => $this->application->id,
            'offer_id' => $this->application->offer_id,
            'offer_title' => $this->application->offer?->title,
            'student_id' => $this->application->student?->id,
            'student_name' => $this->application->student?->name,
            'message' => 'A company accepted an application. Admin validation is required.',
        ];
    }
}
