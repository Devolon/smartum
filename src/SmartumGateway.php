<?php

namespace Devolon\Smartum;

use Devolon\Payment\Contracts\HasUpdateTransactionData;
use Devolon\Payment\Contracts\PaymentGatewayInterface;
use Devolon\Payment\DTOs\PurchaseResultDTO;
use Devolon\Payment\DTOs\RedirectDTO;
use Devolon\Payment\Models\Transaction;
use Devolon\Payment\Services\GenerateCallbackURLService;
use Devolon\Payment\Services\SetGatewayResultService;
use Devolon\Smartum\DTOs\PurchaseRequestDTO;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Validation\RequiredConstraintsViolated;
use Solinor\Smartum\FormBuilder;
use Solinor\Smartum\PaymentApi;

class SmartumGateway implements PaymentGatewayInterface, HasUpdateTransactionData
{
    public const NAME = 'smartum';

    public function __construct(
        private SmartumClient $smartumClient,
        private GenerateCallbackURLService $generateCallbackURLService,
        private SetGatewayResultService $setGatewayResultService,
    ) {
    }

    public function purchase(Transaction $transaction): PurchaseResultDTO
    {
        $successUrl = ($this->generateCallbackURLService)($transaction, Transaction::STATUS_DONE);
        $cancelUrl = ($this->generateCallbackURLService)($transaction, Transaction::STATUS_FAILED);

        $purchaseRequestDTO = PurchaseRequestDTO::fromArray([
            'amount' => $transaction->money_amount * 100,
            'product_name' => $transaction->product_type,
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'nonce' => $transaction->id,
            'benefit' => config('smartum.benefit'),
        ]);

        $purchaseResponseDTO = $this->smartumClient->purchase($purchaseRequestDTO);

        return PurchaseResultDTO::fromArray([
            'should_redirect' => true,
            'redirect_to' => RedirectDTO::fromArray([
                'redirect_url' => $purchaseResponseDTO->url,
                'redirect_method' => 'GET',
                'redirect_data' => null,
            ]),
        ]);
    }

    public function verify(Transaction $transaction, array $data): bool
    {
        $configuration = Configuration::forAsymmetricSigner(
            \Lcobucci\JWT\Signer\Ecdsa\Sha512::create(),
            InMemory::file(config('smartum.jwt_public_key_path')),
            InMemory::base64Encoded(\base64_encode(config('smartum.jwt_public_key_id')))
        );
        $token = $configuration->parser()->parse($data['jwt']);
        try {
            $configuration->validator()->assert($token, new \Lcobucci\JWT\Validation\Constraint\SignedWith(
                \Lcobucci\JWT\Signer\Ecdsa\Sha512::create(),
                InMemory::file(config('smartum.jwt_public_key_path'))
            ));
        } catch (RequiredConstraintsViolated $e) {
            return false;
        }

        if ($token->headers()->get('kid') !== config('smartum.jwt_public_key_id')) {
            return false;
        }

        ($this->setGatewayResultService)($transaction, 'commit', $data);
        return true;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function updateTransactionDataRules(string $newStatus): array
    {
        if ($newStatus !== Transaction::STATUS_DONE) {
            return [];
        }

        return [
            'jwt' => [
                'required',
                'string'
            ]
        ];
    }
}
