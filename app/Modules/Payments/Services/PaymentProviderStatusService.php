<?php

namespace App\Modules\Payments\Services;

use App\Modules\Payments\DTOs\PaymentInstruction;
use App\Modules\Payments\Providers\PaymentProviderManager;

class PaymentProviderStatusService
{
    public function __construct(
        protected PaymentProviderManager $manager,
    ) {}

    /**
     * Return a read-only status snapshot for every registered provider.
     *
     * @return array<string, array<string, mixed>>
     */
    public function providerStatuses(): array
    {
        $statuses = [];

        foreach ($this->manager->all() as $name => $provider) {
            $supported = [];

            foreach (PaymentInstruction::operations() as $operation) {
                foreach (PaymentInstruction::methods() as $method) {
                    if ($provider->supports($operation, $method)) {
                        $supported[$operation][] = $method;
                    }
                }
            }

            $statuses[$name] = [
                'name' => $name,
                'configured' => $provider->isConfigured(),
                'healthy' => $provider->isHealthy(),
                'supported_methods' => $supported,
            ];
        }

        return $statuses;
    }

    /**
     * Return the status for a single registered provider.
     */
    public function status(string $providerName): ?array
    {
        if (! in_array($providerName, $this->manager->names(), true)) {
            return null;
        }

        $provider = $this->manager->resolve($providerName);
        $supported = [];

        foreach (PaymentInstruction::operations() as $operation) {
            foreach (PaymentInstruction::methods() as $method) {
                if ($provider->supports($operation, $method)) {
                    $supported[$operation][] = $method;
                }
            }
        }

        return [
            'name' => $providerName,
            'configured' => $provider->isConfigured(),
            'healthy' => $provider->isHealthy(),
            'supported_methods' => $supported,
        ];
    }

    /**
     * Return the global automation state and per-operation enablement.
     */
    public function automationStatus(): array
    {
        $global = config('payment_providers.automation_enabled', false);
        $operations = [];

        foreach (PaymentInstruction::operations() as $operation) {
            $config = config("payment_providers.operations.{$operation}", []);
            $operations[$operation] = [
                'enabled' => (bool) ($config['enabled'] ?? false),
                'method' => $config['method'] ?? PaymentInstruction::METHOD_MANUAL,
                'mode' => $config['mode'] ?? PaymentInstruction::EXECUTION_MANUAL,
                'provider' => $config['provider'] ?? 'manual',
                'automation_active' => $global && ($config['enabled'] ?? false),
            ];
        }

        return [
            'global_automation_enabled' => $global,
            'operations' => $operations,
        ];
    }
}
