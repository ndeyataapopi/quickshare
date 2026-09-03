<?php

namespace App\Modules\Payments\Services;

use App\Modules\Payments\Contracts\PaymentProviderInterface;
use App\Modules\Payments\DTOs\OperationConfiguration;
use App\Modules\Payments\DTOs\PaymentInstruction;
use App\Modules\Payments\Exceptions\InvalidPaymentConfigurationException;
use App\Modules\Payments\Providers\PaymentProviderManager;

class PaymentConfigurationResolver
{
    public function __construct(
        protected PaymentProviderManager $manager,
    ) {}

    /**
     * Resolve and validate the configuration for a single operation.
     */
    public function resolve(string $operation): OperationConfiguration
    {
        $this->assertKnownOperation($operation);

        $config = config("payment_providers.operations.{$operation}", []);

        if (! $this->isAutomationEnabled($operation, $config)) {
            return OperationConfiguration::make($operation, PaymentInstruction::METHOD_MANUAL, PaymentInstruction::EXECUTION_MANUAL, 'manual');
        }

        $method = $this->resolveMethod($operation, $config['method'] ?? null);
        $mode = $this->resolveMode($operation, $config['mode'] ?? null);
        $provider = $this->resolveProviderName($operation, $config['provider'] ?? null, $mode, $method);

        $this->assertProviderSupportsOperation($operation, $method, $provider);

        return OperationConfiguration::make($operation, $method, $mode, $provider);
    }

    /**
     * Resolve all four operations.
     *
     * @return array<string, OperationConfiguration>
     */
    public function all(): array
    {
        $operations = [];

        foreach ($this->operationNames() as $operation) {
            $operations[$operation] = $this->resolve($operation);
        }

        return $operations;
    }

    /**
     * Resolve only automated operations.
     *
     * @return array<string, OperationConfiguration>
     */
    public function automated(): array
    {
        return array_filter(
            $this->all(),
            static fn (OperationConfiguration $config) => $config->isAutomated(),
        );
    }

    /**
     * Resolve only manual operations.
     *
     * @return array<string, OperationConfiguration>
     */
    public function manual(): array
    {
        return array_filter(
            $this->all(),
            static fn (OperationConfiguration $config) => $config->isManual(),
        );
    }

    /**
     * @return array<int, string>
     */
    protected function operationNames(): array
    {
        return config('payment_providers.operation_names', PaymentInstruction::operations());
    }

    protected function isAutomationEnabled(string $operation, array $config): bool
    {
        if (! config('payment_providers.automation_enabled', false)) {
            return false;
        }

        return (bool) ($config['enabled'] ?? false);
    }

    protected function assertKnownOperation(string $operation): void
    {
        if (! in_array($operation, $this->operationNames(), true)) {
            throw InvalidPaymentConfigurationException::unknownOperation($operation);
        }
    }

    protected function resolveMethod(string $operation, ?string $method): string
    {
        $method = strtolower($method ?? PaymentInstruction::METHOD_MANUAL);

        if (! in_array($method, PaymentInstruction::methods(), true)) {
            throw InvalidPaymentConfigurationException::unknownMethod($operation, $method);
        }

        return $method;
    }

    protected function resolveMode(string $operation, ?string $mode): string
    {
        $mode = strtolower($mode ?? PaymentInstruction::EXECUTION_MANUAL);

        if (! in_array($mode, PaymentInstruction::executionModes(), true)) {
            throw InvalidPaymentConfigurationException::unknownMode($operation, $mode);
        }

        return $mode;
    }

    protected function resolveProviderName(string $operation, ?string $provider, string $mode, string $method): string
    {
        $provider = strtolower($provider ?? 'manual');

        if ($mode === PaymentInstruction::EXECUTION_MANUAL || $method === PaymentInstruction::METHOD_MANUAL) {
            if ($provider !== 'manual') {
                throw InvalidPaymentConfigurationException::manualRequiresManualProvider($operation);
            }

            return 'manual';
        }

        if ($provider === 'manual' || $provider === '') {
            throw InvalidPaymentConfigurationException::automatedRequiresProvider($operation);
        }

        return $provider;
    }

    protected function assertProviderSupportsOperation(
        string $operation,
        string $method,
        string $providerName,
    ): void {
        if (! $this->manager->configured($providerName)) {
            throw InvalidPaymentConfigurationException::providerNotConfigured($operation, $providerName);
        }

        /** @var PaymentProviderInterface $provider */
        $provider = $this->manager->resolve($providerName);

        if (! $provider->supports($operation, $method)) {
            throw InvalidPaymentConfigurationException::providerDoesNotSupport($operation, $method, $providerName);
        }
    }
}
