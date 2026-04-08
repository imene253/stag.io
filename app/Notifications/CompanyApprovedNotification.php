<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class CompanyApprovedNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected User $company
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'company_approved',
            'company_id' => $this->company->id,
            'company_name' => $this->company->name,
            'message' => 'Your company account has been approved by the admin. You can now publish internships.',
        ];
    }
}
