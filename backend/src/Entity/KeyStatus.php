<?php
declare(strict_types=1);

namespace App\Entity;

enum KeyStatus: string
{
    case Available = 'available';
    case Reserved = 'reserved';
    case Sold = 'sold';
}
