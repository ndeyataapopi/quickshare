<?php

namespace App\Enums;

enum UserRole: string
{
    case CLIENT = 'client';
    case ADMIN = 'admin';
    case COMPLIANCE_OFFICER = 'compliance_officer';
    case FINANCE_OFFICER = 'finance_officer';
}
