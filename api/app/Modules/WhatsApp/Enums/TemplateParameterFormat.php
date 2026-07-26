<?php

namespace App\Modules\WhatsApp\Enums;

enum TemplateParameterFormat: string
{
    case Named = 'named';
    case Positional = 'positional';
}
