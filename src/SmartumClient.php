<?php

namespace Devolon\Smartum;

use Devolon\Smartum\DTOs\PurchaseRequestDTO;
use Devolon\Smartum\DTOs\PurchaseResponseDTO;
use GuzzleHttp\Client;

class SmartumClient
{
    public function __construct(private string $venue, private string $url, private Client $client)
    {
    }

    public function purchase(PurchaseRequestDTO $purchaseRequestDTO): PurchaseResponseDTO
    {
        $body = array_merge(
            $purchaseRequestDTO->toArray(),
            [
                'venue' => $this->venue,
            ],
        );

        $data = $this->client->request('POST', $this->url, [
            'body' => json_encode($body),
        ])->getBody()->getContents();

        return PurchaseResponseDTO::fromArray(json_decode($data, true)['data']);
    }
}
