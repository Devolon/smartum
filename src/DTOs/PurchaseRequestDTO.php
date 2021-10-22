<?php

namespace Devolon\Smartum\DTOs;

use Devolon\Common\Bases\DTO;

class PurchaseRequestDTO extends DTO
{
    public function __construct(
        public int $amount,
        public string $product_name,
        public string $success_url,
        public string $cancel_url,
        public string $nonce,
        public string $benefit,
    ) {
    }
}
