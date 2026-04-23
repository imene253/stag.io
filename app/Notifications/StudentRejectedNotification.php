<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class StudentRejectedNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected User $student
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'student_rejected',
            'student_id' => $this->student->id,
            'student_name' => $this->student->name,
            'message' => 'Your student account has not been approved by admin yet. Please contact administration.',
        ];
    }
}
