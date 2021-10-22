<?php

namespace Devolon\Smartum\DTOs;

use Devolon\Common\Bases\DTO;

class PurchaseResponseDTO extends DTO
{
    public function __construct(public string $url)
    {
    }
}
