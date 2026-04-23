<?php

namespace App\Notifications;

use App\Models\Application;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class CompanyApplicationClosedByStudentChoiceNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected Application $closedApplication,
        protected Application $selectedApplication
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'company_application_closed_by_student_choice',
            'application_id' => $this->closedApplication->id,
            'offer_id' => $this->closedApplication->offer_id,
            'offer_title' => $this->closedApplication->offer?->title,
            'student_id' => $this->closedApplication->student?->id,
            'student_name' => $this->closedApplication->student?->name,
            'selected_application_id' => $this->selectedApplication->id,
            'selected_offer_id' => $this->selectedApplication->offer_id,
            'selected_offer_title' => $this->selectedApplication->offer?->title,
            'message' => 'This application was closed because the student finalized another internship choice.',
        ];
    }
}
