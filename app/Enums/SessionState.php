<?php

namespace App\Enums;

enum SessionState: string
{
    case Idle = 'idle';
    case AwaitingAction = 'awaiting_action';
    case AwaitingName = 'awaiting_name';
    case AwaitingProcedure = 'awaiting_procedure';
    case AwaitingDentist = 'awaiting_dentist';
    case AwaitingDate = 'awaiting_date';
    case AwaitingTime = 'awaiting_time';
    case AwaitingConfirmation = 'awaiting_confirmation';
    case AwaitingAppointmentToCancel = 'awaiting_appointment_to_cancel';
    case AwaitingCancellationConfirmation = 'awaiting_cancellation_confirmation';
    case AwaitingAppointmentToReschedule = 'awaiting_appointment_to_reschedule';
}
