<?php

namespace App\Exceptions;

use App\Enums\CampaignStatus;
use RuntimeException;

class InvalidCampaignTransition extends RuntimeException
{
    public static function between(CampaignStatus $from, CampaignStatus $to): self
    {
        return new self(sprintf(
            'Transition interdite : %s → %s.',
            $from->label(),
            $to->label(),
        ));
    }

    public static function because(string $reason): self
    {
        return new self('Activation impossible : '.$reason);
    }
}
