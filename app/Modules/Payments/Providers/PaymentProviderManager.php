<?php

namespace App\Modules\Payments\Providers;

use App\Modules\Payments\Contracts\PaymentProviderInterface;
use App\Modules\Payments\DTOs\PaymentInstruction;
use InvalidArgumentException;

class PaymentProviderManager
{
    /** @var array<string, PaymentProviderInterface> */
    protected array $providers = [];

    public function __construct(
        protected string $defaultProvider,
    ) {
    }

    public function register(string $name, PaymentProviderInterface $provider): self
    {
        $this->providers[$name] = $provider;

        return $this;
    }

    public function resolve(string $name): PaymentProviderInterface
    {
        if (! isset($this->providers[$name])) {
            throw new InvalidArgumentException("Payment provider [{$name}] is not registered.");
        }

        return $this->providers[$name];
    }

    public function default(): PaymentProviderInterface
    {
        return $this->resolve($this->defaultProvider);
    }

    public function configured(string $name): bool
    {
        return isset($this->providers[$name]) && $this->providers[$name]->isConfigured();
    }

    /**
     * Resolve the provider that should handle a given instruction.
     */
    public function forInstruction(PaymentInstruction $instruction): PaymentProviderInterface
    {
        if ($instruction->isManual()) {
            return $this->resolve('manual');
        }

        $provider = $this->default();

        if (! $provider->supports($instruction->operation, $instruction->paymentMethod)) {
            throw new InvalidArgumentException(
                "Provider [{$provider->getName()}] does not support operation [{$instruction->operation}] with method [{$instruction->paymentMethod}]."
            );
        }

        return $provider;
    }

    /**
     * @return array<string, PaymentProviderInterface>
     */
    public function all(): array
    {
        return $this->providers;
    }

    public function names(): array
    {
        return array_keys($this->providers);
    }
}
