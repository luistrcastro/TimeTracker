<?php

namespace App\Enums;

enum InvoiceStatus: string
{
    case Draft    = 'draft';
    case Sent     = 'sent';
    case Approved = 'approved';
    case Paid     = 'paid';
    case Void     = 'void';
}
