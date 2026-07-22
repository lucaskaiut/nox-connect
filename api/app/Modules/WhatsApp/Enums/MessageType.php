<?php

namespace App\Modules\WhatsApp\Enums;

enum MessageType: string
{
    case Text = 'text';
    case Image = 'image';
    case Document = 'document';
    case Audio = 'audio';
    case Video = 'video';
    case Location = 'location';
    case Contacts = 'contacts';
    case Interactive = 'interactive';
    case Button = 'button';
    case Unknown = 'unknown';
}
