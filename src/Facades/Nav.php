<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Facades;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Facade;
use IntrAgile\NavOnlineSzamla\Exceptions\NavException;
use IntrAgile\NavOnlineSzamla\NavManager;
use IntrAgile\NavOnlineSzamla\Support\RequestIdGenerator;
use IntrAgile\NavOnlineSzamla\Tenancy\CredentialsResolver;
use IntrAgile\NavOnlineSzamla\Testing\NavFake;

/**
 * @method static \IntrAgile\NavOnlineSzamla\NavClient client(?string $tenant = null)
 * @method static \IntrAgile\NavOnlineSzamla\NavClient for(?string $tenant)
 * @method static \IntrAgile\NavOnlineSzamla\Models\NavTransaction submit(\IntrAgile\NavOnlineSzamla\Requests\InvoiceSubmission|\IntrAgile\NavOnlineSzamla\Invoice\InvoiceBuilder|list<\IntrAgile\NavOnlineSzamla\Requests\InvoiceSubmission|\IntrAgile\NavOnlineSzamla\Invoice\InvoiceBuilder> $invoices, ?\Illuminate\Database\Eloquent\Model $subject = null, ?string $tenant = null)
 * @method static \IntrAgile\NavOnlineSzamla\Models\NavTransaction annul(\IntrAgile\NavOnlineSzamla\Requests\TechnicalAnnulment|list<\IntrAgile\NavOnlineSzamla\Requests\TechnicalAnnulment> $annulments, ?\Illuminate\Database\Eloquent\Model $subject = null, ?string $tenant = null)
 * @method static \IntrAgile\NavOnlineSzamla\Responses\ExchangeToken tokenExchange()
 * @method static string manageInvoice(\IntrAgile\NavOnlineSzamla\Requests\InvoiceSubmission|list<\IntrAgile\NavOnlineSzamla\Requests\InvoiceSubmission> $invoices, ?bool $compress = null)
 * @method static string manageAnnulment(\IntrAgile\NavOnlineSzamla\Requests\TechnicalAnnulment|list<\IntrAgile\NavOnlineSzamla\Requests\TechnicalAnnulment> $annulments)
 * @method static \IntrAgile\NavOnlineSzamla\Responses\TransactionStatus queryTransactionStatus(string $transactionId, bool $returnOriginalRequest = false)
 * @method static \IntrAgile\NavOnlineSzamla\Responses\InvoiceDataResult|null queryInvoiceData(\IntrAgile\NavOnlineSzamla\Requests\InvoiceNumberQuery $query)
 * @method static bool queryInvoiceCheck(\IntrAgile\NavOnlineSzamla\Requests\InvoiceNumberQuery $query)
 * @method static \IntrAgile\NavOnlineSzamla\Responses\Page<\IntrAgile\NavOnlineSzamla\Responses\InvoiceDigest> queryInvoiceDigest(\IntrAgile\NavOnlineSzamla\Requests\InvoiceDigestQuery $query, int $page = 1)
 * @method static \Illuminate\Support\LazyCollection<int, \IntrAgile\NavOnlineSzamla\Responses\InvoiceDigest> invoiceDigests(\IntrAgile\NavOnlineSzamla\Requests\InvoiceDigestQuery $query)
 * @method static \IntrAgile\NavOnlineSzamla\Responses\Page<\IntrAgile\NavOnlineSzamla\Responses\InvoiceChainElement> queryInvoiceChainDigest(\IntrAgile\NavOnlineSzamla\Requests\InvoiceChainQuery $query, int $page = 1)
 * @method static \Illuminate\Support\LazyCollection<int, \IntrAgile\NavOnlineSzamla\Responses\InvoiceChainElement> invoiceChain(\IntrAgile\NavOnlineSzamla\Requests\InvoiceChainQuery $query)
 * @method static \IntrAgile\NavOnlineSzamla\Responses\Page<\IntrAgile\NavOnlineSzamla\Responses\Transaction> queryTransactionList(\DateTimeInterface $from, \DateTimeInterface $to, int $page = 1, ?\IntrAgile\NavOnlineSzamla\Enums\RequestStatus $status = null)
 * @method static \Illuminate\Support\LazyCollection<int, \IntrAgile\NavOnlineSzamla\Responses\Transaction> transactions(\DateTimeInterface $from, \DateTimeInterface $to, ?\IntrAgile\NavOnlineSzamla\Enums\RequestStatus $status = null)
 * @method static \IntrAgile\NavOnlineSzamla\Responses\Taxpayer|null queryTaxpayer(string $taxNumber)
 * @method static void assertInvoiceSubmitted(string|\Closure|null $invoice = null, ?string $tenant = null)
 * @method static void assertInvoiceNotSubmitted(string|\Closure|null $invoice = null, ?string $tenant = null)
 * @method static void assertNothingSubmitted()
 * @method static void assertSubmittedCount(int $expected)
 * @method static void assertAnnulmentSubmitted(string|\Closure|null $annulment = null, ?string $tenant = null)
 * @method static void assertTaxpayerQueried(?string $taxNumber = null)
 * @method static void assertSent(string $operation, ?\Closure $callback = null)
 * @method static void assertNotSent(string $operation, ?\Closure $callback = null)
 * @method static void assertSentCount(string $operation, int $expected)
 * @method static void assertNothingSent()
 *
 * @see NavManager
 * @see NavFake
 */
final class Nav extends Facade
{
    /**
     * Replace NAV with an in-memory fake that records every call.
     */
    public static function fake(): NavFake
    {
        $app = self::getFacadeApplication();
        if ($app === null) {
            throw new NavException('Nav::fake() requires a booted Laravel application.');
        }

        $fake = (new NavFake(
            $app->make(Repository::class),
            $app->make(CredentialsResolver::class),
            $app->make(HttpFactory::class),
            $app->make(RequestIdGenerator::class),
        ))->withoutDelays();

        self::swap($fake);

        return $fake;
    }

    protected static function getFacadeAccessor(): string
    {
        return NavManager::class;
    }
}
