<?php

namespace App\Modules\Payments;

use App\Modules\Funding\Services\FundingService;
use App\Modules\Loans\Services\DisbursementService;
use App\Modules\Payments\Providers\CollexiaPaymentProvider;
use App\Modules\Payments\Providers\FakePaymentProvider;
use App\Modules\Payments\Providers\ManualPaymentProvider;
use App\Modules\Payments\Providers\MobiDebitPaymentProvider;
use App\Modules\Payments\Providers\PaymentProviderManager;
use App\Modules\Payments\Providers\RealPayPaymentProvider;
use App\Modules\Payments\Services\PaymentConfigurationResolver;
use App\Modules\Payments\Services\PaymentExecutionOrchestrator;
use App\Modules\Payments\Services\PaymentExecutionService;
use App\Modules\Payments\Services\PaymentProviderStatusService;
use App\Modules\Payments\Services\PaymentReconciliationService;
use App\Modules\Payments\Services\PaymentWebhookService;
use App\Modules\Repayments\Services\RepaymentService;
use Illuminate\Support\ServiceProvider;

class PaymentsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PaymentProviderManager::class, function ($app) {
            $manager = new PaymentProviderManager(
                config('payment_providers.default_provider', 'manual'),
            );

            $manager->register('manual', new ManualPaymentProvider);

            $fakeOutcome = config('payment_providers.providers.fake.outcome');
            $manager->register('fake', new FakePaymentProvider($fakeOutcome));

            $manager->register('collexia', new CollexiaPaymentProvider(
                config('payment_providers.providers.collexia', [])
            ));

            $manager->register('mobidebit', new MobiDebitPaymentProvider(
                config('payment_providers.providers.mobidebit', [])
            ));

            $manager->register('realpay', new RealPayPaymentProvider(
                config('payment_providers.providers.realpay', [])
            ));

            return $manager;
        });

        $this->app->singleton(PaymentExecutionService::class, function ($app) {
            return new PaymentExecutionService($app->make(PaymentProviderManager::class));
        });

        $this->app->singleton(PaymentProviderStatusService::class, function ($app) {
            return new PaymentProviderStatusService($app->make(PaymentProviderManager::class));
        });

        $this->app->singleton(PaymentReconciliationService::class, function ($app) {
            return new PaymentReconciliationService;
        });

        $this->app->singleton(PaymentConfigurationResolver::class, function ($app) {
            return new PaymentConfigurationResolver($app->make(PaymentProviderManager::class));
        });

        $this->app->singleton(PaymentExecutionOrchestrator::class, function ($app) {
            return new PaymentExecutionOrchestrator(
                $app->make(PaymentConfigurationResolver::class),
                $app->make(PaymentExecutionService::class),
                $app->make(PaymentWebhookService::class),
                $app->make(PaymentReconciliationService::class),
                $app->make(FundingService::class),
                $app->make(DisbursementService::class),
                $app->make(RepaymentService::class),
            );
        });
    }

    public function boot(): void
    {
        //
    }
}
