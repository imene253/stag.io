<?php

namespace App\Notifications;

use App\Models\Application;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class StudentFinalChoiceConfirmedNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected Application $application,
        protected int $closedApplicationsCount = 0
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'student_final_choice_confirmed',
            'application_id' => $this->application->id,
            'offer_id' => $this->application->offer_id,
            'offer_title' => $this->application->offer?->title,
            'company_id' => $this->application->offer?->company?->id,
            'company_name' => $this->application->offer?->company?->name,
            'internship_starts_at' => optional($this->application->internship_starts_at)->toDateString(),
            'internship_ends_at' => optional($this->application->internship_ends_at)->toDateString(),
            'closed_applications_count' => $this->closedApplicationsCount,
            'message' => 'Your final internship choice has been saved and sent for admin validation.',
        ];
    }
}
