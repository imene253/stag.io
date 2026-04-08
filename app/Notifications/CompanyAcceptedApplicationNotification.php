<?php

namespace App\Notifications;

use App\Models\Application;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class CompanyAcceptedApplicationNotification extends Notification
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
            'kind' => 'company_application_accepted',
            'application_id' => $this->application->id,
            'offer_id' => $this->application->offer_id,
            'offer_title' => $this->application->offer?->title,
            'company_id' => $this->application->offer?->company?->id,
            'company_name' => $this->application->offer?->company?->name,
            'message' => 'Your application has been accepted by the company and is waiting for admin validation.',
        ];
    }
}
