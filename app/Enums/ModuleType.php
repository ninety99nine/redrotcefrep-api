<?php

namespace App\Enums;

enum ModuleType: string
{
    case MAP = 'map';
    case TEXT = 'text';
    case LINK = 'link';
    case IMAGE = 'image';
    case VIDEO = 'video';
    case CONTACT = 'contact';
    case PRODUCTS= 'products';
    case COUNTDOWN = 'countdown';
}
