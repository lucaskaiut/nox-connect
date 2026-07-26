<?php

namespace App\Modules\WhatsApp\Enums;

enum TemplateCategory: string
{
    case Authentication = 'authentication';
    case Marketing = 'marketing';
    case Utility = 'utility';
}
