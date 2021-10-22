<?php

namespace Devolon\Smartum\Tests\Unit;

use DateTimeImmutable;
use Devolon\Payment\Contracts\HasUpdateTransactionData;
use Devolon\Payment\Contracts\PaymentGatewayInterface;
use Devolon\Payment\DTOs\PurchaseResultDTO;
use Devolon\Payment\DTOs\RedirectDTO;
use Devolon\Payment\Models\Transaction;
use Devolon\Payment\Services\GenerateCallbackURLService;
use Devolon\Payment\Services\PaymentGatewayDiscoveryService;
use Devolon\Payment\Services\SetGatewayResultService;
use Devolon\Smartum\DTOs\PurchaseRequestDTO;
use Devolon\Smartum\DTOs\PurchaseResponseDTO;
use Devolon\Smartum\SmartumClient;
use Devolon\Smartum\SmartumGateway;
use Devolon\Smartum\Tests\SmartumTestCase;
use Hamcrest\Core\IsEqual;
use Httpful\Response;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Storage;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Key\InMemory;
use Mockery;
use Mockery\MockInterface;
use stdClass;

class SmartumGatewayTest extends SmartumTestCase
{
    use WithFaker;

    public function testGetName()
    {
        // Arrange
        $gateway = $this->resolveGateway();

        // Act
        $result = $gateway->getName();

        // Assert
        $this->assertEquals('smartum', $result);
    }

    public function testItRegisteredAsGateway()
    {
        // Arrange
        $paymentGatewayDiscoveryService = $this->resolvePaymentGatewayDiscoveryService();

        // Act
        $result = $paymentGatewayDiscoveryService->get('smartum');

        // Assert
        $this->assertInstanceOf(SmartumGateway::class, $result);
        $this->assertInstanceOf(HasUpdateTransactionData::class, $result);
    }

    public function testPurchase()
    {
        // Arrange
        $generateCallbackUrlService = $this->mockGenerateCallbackUrlService();
        $smartumClient = $this->mockSmartumClient();
        $gateway = $this->discoverGateway();
        $transaction = Transaction::factory()->create();

        $successUrl = $this->faker->unique->url;
        $cancelUrl = $this->faker->unique->url;
        $redirectUrl = $this->faker->unique->url;

        $purchaseRequestDTO = PurchaseRequestDTO::fromArray([
            'amount' => $transaction->money_amount * 100,
            'product_name' => $transaction->product_type,
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'nonce' => $transaction->id,
            'benefit' => 'culture',
        ]);

        $purchaseResponseDTO = PurchaseResponseDTO::fromArray([
            'url' => $redirectUrl,
        ]);

        $expectedRedirectDTO = RedirectDTO::fromArray([
            'redirect_url' => $purchaseResponseDTO->url,
            'redirect_method' => 'GET',
            'redirect_data' => null,
        ]);

        $expectedPurchaseResultDTO = PurchaseResultDTO::fromArray([
            'should_redirect' => true,
            'redirect_to' => $expectedRedirectDTO,
        ]);

        // Expect
        $generateCallbackUrlService
            ->shouldReceive('__invoke')
            ->with($transaction, Transaction::STATUS_DONE)
            ->once()
            ->andReturn($successUrl);
        $generateCallbackUrlService
            ->shouldReceive('__invoke')
            ->with($transaction, Transaction::STATUS_FAILED)
            ->once()
            ->andReturn($cancelUrl);

        $smartumClient
            ->shouldReceive('purchase')
            ->with(IsEqual::equalTo($purchaseRequestDTO))
            ->once()
            ->andReturn($purchaseResponseDTO);

        // Act
        $result = $gateway->purchase($transaction);

        // Assert
        $this->assertEquals($expectedPurchaseResultDTO, $result);
    }

    public function testVerifySuccessfully()
    {
        // Arrange
        $path = __DIR__ . '/../test-public.pem';
        config(['smartum.jwt_public_key_path' => $path]);
        $transaction = Transaction::factory()->create();
        $data = ['jwt' => $this->issueToken()];
        $setGatewayResultService = $this->mockSetGatewayResultService();
        $gateway = $this->discoverGateway();

        // Expect
        $setGatewayResultService
            ->shouldReceive('__invoke')
            ->with($transaction, 'commit', $data)
            ->once();

        // Act
        $result = $gateway->verify($transaction, $data);

        // Assert
        $this->assertTrue($result);
        $transaction->refresh();
    }

    public function testVerifyFailed()
    {
        // Arrange
        $path = __DIR__ . '/../test-public.pem';
        config(['smartum.jwt_public_key_path' => $path]);
        $transaction = Transaction::factory()->create();
        $setGatewayResultService = $this->mockSetGatewayResultService();
        $data = ['jwt' => $this->issueWrongToken()];
        $gateway = $this->discoverGateway();

        // Expect
        $setGatewayResultService
            ->shouldNotReceive('__invoke');

        // Act
        $result = $gateway->verify($transaction, $data);

        // Assert
        $this->assertFalse($result);
        $transaction->refresh();
    }


    public function testUpdateTransactionDataRulesWithDoneStatus()
    {
        // Arrange
        $gateway = $this->resolveGateway();
        $expected = [
            'jwt' => [
                'required',
                'string'
            ]
        ];

        // Act
        $result = $gateway->updateTransactionDataRules('done');

        // Assert
        $this->assertEquals($expected, $result);
    }

    public function testUpdateTransactionDataRulesWithFailedStatus()
    {
        // Arrange
        $gateway = $this->resolveGateway();
        $expected = [];

        // Act
        $result = $gateway->updateTransactionDataRules('failed');

        // Assert
        $this->assertEquals($expected, $result);
    }

    private function resolveGateway(): SmartumGateway
    {
        return resolve(SmartumGateway::class);
    }

    private function resolvePaymentGatewayDiscoveryService(): PaymentGatewayDiscoveryService
    {
        return resolve(PaymentGatewayDiscoveryService::class);
    }


    private function mockSmartumClient(): MockInterface
    {
        return $this->mock(SmartumClient::class);
    }

    private function discoverGateway(): PaymentGatewayInterface
    {
        $paymentDiscoveryService = $this->resolvePaymentGatewayDiscoveryService();

        return $paymentDiscoveryService->get('smartum');
    }

    private function mockSetGatewayResultService(): MockInterface
    {
        return $this->mock(SetGatewayResultService::class);
    }

    private function mockGenerateCallbackUrlService(): MockInterface
    {
        return $this->mock(GenerateCallbackURLService::class);
    }

    private function issueToken(): string
    {
        Storage::fake('test')->put('test.txt', 'tetet');
        $configuration = Configuration::forAsymmetricSigner(
            \Lcobucci\JWT\Signer\Ecdsa\Sha512::create(),
            InMemory::file(__DIR__ . '/../test-private.pem'),
            InMemory::file(__DIR__ . '/../test-public.pem'),
        );
        $now   = new DateTimeImmutable();

        $jwt = $configuration->builder()
            ->issuedBy('http://example.com')
            ->permittedFor('http://example.org')
            ->identifiedBy('4f1g23a12aa')
            ->issuedAt($now)
            ->canOnlyBeUsedAfter($now->modify('+1 minute'))
            ->expiresAt($now->modify('+1 hour'))
            ->withClaim('uid', 1)
            ->withHeader('kid', config('smartum.jwt_public_key_id'))
            ->getToken($configuration->signer(), $configuration->signingKey())
            ->toString();


        return $jwt;
    }
    private function issueWrongToken(): string
    {
        Storage::fake('test')->put('test.txt', 'tetet');
        $configuration = Configuration::forAsymmetricSigner(
            \Lcobucci\JWT\Signer\Ecdsa\Sha512::create(),
            InMemory::file(__DIR__ . '/../wrong-test-private.pem'),
            InMemory::file(__DIR__ . '/../wrong-test-public.pem'),
        );
        $now   = new DateTimeImmutable();

        $jwt = $configuration->builder()
            ->issuedBy('http://example.com')
            ->permittedFor('http://example.org')
            ->identifiedBy('4f1g23a12aa')
            ->issuedAt($now)
            ->canOnlyBeUsedAfter($now->modify('+1 minute'))
            ->expiresAt($now->modify('+1 hour'))
            ->withClaim('uid', 1)
            ->withHeader('kid', config('smartum.jwt_public_key_id'))
            ->getToken($configuration->signer(), $configuration->signingKey())
            ->toString();


        return $jwt;
    }
}
