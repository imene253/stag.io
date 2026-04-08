<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class CompanyRegistrationPendingApprovalNotification extends Notification
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
            'kind' => 'company_registration_pending_approval',
            'company_id' => $this->company->id,
            'company_name' => $this->company->name,
            'company_email' => $this->company->email,
            'message' => 'A new company registered and is waiting for admin approval.',
        ];
    }
}
