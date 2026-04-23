<?php

namespace App\Notifications;

use App\Models\Application;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class StudentFinalChoiceNeedsAdminValidationNotification extends Notification
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
            'kind' => 'student_final_choice_needs_admin_validation',
            'application_id' => $this->application->id,
            'offer_id' => $this->application->offer_id,
            'offer_title' => $this->application->offer?->title,
            'student_id' => $this->application->student?->id,
            'student_name' => $this->application->student?->name,
            'closed_applications_count' => $this->closedApplicationsCount,
            'message' => 'A student finalized their internship choice. Admin validation is required.',
        ];
    }
}
