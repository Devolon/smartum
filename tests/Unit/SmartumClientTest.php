<?php

namespace Devolon\Smartum\Tests\Unit;

use Devolon\Smartum\DTOs\PurchaseRequestDTO;
use Devolon\Smartum\DTOs\PurchaseResponseDTO;
use Devolon\Smartum\SmartumClient;
use Devolon\Smartum\Tests\SmartumTestCase;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\WithFaker;

class SmartumClientTest extends SmartumTestCase
{
    use WithFaker;

    public function testPurchase()
    {
        // Arrange
        $container = [];
        $requestData = $this->mockedRequestData();
        $responseData = $this->mockedResponseData();
        $mock = new MockHandler([
            new Response(200, ['X-Foo' => 'Bar'], \json_encode(['data' => $responseData])),
        ]);
        $history = Middleware::history($container);
        $handlerStack = HandlerStack::create($mock);
        $handlerStack->push($history);
        $mockedCliwnt = new Client(['handler' => $handlerStack]);
        $url = $this->faker->url;
        $venue = $this->faker->word();

        // Act
        $client = new SmartumClient($venue, $url, $mockedCliwnt);
        $result = $client->purchase(PurchaseRequestDTO::fromArray($requestData));

        // Assert
        $this->assertEquals(1, count($container));
        /** @var Request $request */
        $request = $container[0]['request'];
        $this->assertEquals('POST', $request->getMethod());
        $this->assertEquals($url, $request->getUri());
        $this->assertEquals($request->getBody()->getContents(), \json_encode(
            \array_merge($requestData, compact('venue')),
        ));
        $this->assertEquals(PurchaseResponseDTO::fromArray($responseData), $result);
    }

    private function mockedRequestData(): array
    {
        return [
            'amount' => $this->faker->numberBetween(),
            'product_name' => $this->faker->word(),
            'success_url' => $this->faker->url(),
            'cancel_url' => $this->faker->url(),
            'nonce' => $this->faker->word(),
            'benefit' => $this->faker->word(),
        ];
    }

    private function mockedResponseData(): array
    {
        return [
            'url' => $this->faker->url(),
        ];
    }
}
