<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class StudentRegistrationPendingApprovalNotification extends Notification
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
            'kind' => 'student_registration_pending_approval',
            'student_id' => $this->student->id,
            'student_name' => $this->student->name,
            'student_email' => $this->student->email,
            'message' => 'A new student registered and is waiting for admin approval.',
        ];
    }
}
