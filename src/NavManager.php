<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Log\LogManager;
use IntrAgile\NavOnlineSzamla\Config\Software;
use IntrAgile\NavOnlineSzamla\Enums\TransactionKind;
use IntrAgile\NavOnlineSzamla\Enums\TransactionState;
use IntrAgile\NavOnlineSzamla\Exceptions\ConfigurationException;
use IntrAgile\NavOnlineSzamla\Exceptions\NavException;
use IntrAgile\NavOnlineSzamla\Http\Transport;
use IntrAgile\NavOnlineSzamla\Invoice\InvoiceBuilder;
use IntrAgile\NavOnlineSzamla\Invoice\Model\InvoiceData;
use IntrAgile\NavOnlineSzamla\Jobs\SubmitTransaction;
use IntrAgile\NavOnlineSzamla\Models\NavTransaction;
use IntrAgile\NavOnlineSzamla\Requests\InvoiceSubmission;
use IntrAgile\NavOnlineSzamla\Requests\TechnicalAnnulment;
use IntrAgile\NavOnlineSzamla\Support\RequestIdGenerator;
use IntrAgile\NavOnlineSzamla\Tenancy\CredentialsResolver;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Entry point of the Laravel integration: clients per tenant and queued submissions.
 *
 * Calls to methods not defined here are forwarded to the default tenant's NavClient.
 *
 * @mixin NavClient
 */
class NavManager
{
    /** @var array<string, NavClient> */
    private array $clients = [];

    private ?Software $software = null;

    public function __construct(
        protected readonly Repository $config,
        protected readonly CredentialsResolver $credentials,
        protected readonly HttpFactory $http,
        private readonly RequestIdGenerator $requestIds,
        private readonly ?LogManager $log = null,
    ) {}

    /**
     * The client of a tenant; the null tenant is the default taxpayer.
     */
    public function client(?string $tenant = null): NavClient
    {
        return $this->clients[$tenant ?? ''] ??= new NavClient(
            $this->credentials->resolve($tenant),
            $this->software(),
            new Transport(
                $this->http,
                $this->baseUrl(),
                $this->int('http.timeout', 30),
                $this->int('http.connect_timeout', 10),
            ),
            $this->requestIds,
            (bool) $this->config->get('nav-online-szamla.validate_xsd', true),
            $this->int('http.retries', 2),
            $this->int('http.retry_delay_ms', 500),
            $this->logger(),
        );
    }

    /**
     * Alias of client() that reads well in multi-taxpayer code: Nav::for('acme')->queryTaxpayer(...).
     */
    public function for(?string $tenant): NavClient
    {
        return $this->client($tenant);
    }

    /**
     * Record invoices in the transaction log and queue them for submission.
     *
     * @param  InvoiceSubmission|InvoiceBuilder|list<InvoiceSubmission|InvoiceBuilder>  $invoices  Up to 100 invoices sent in one request.
     * @param  Model|null  $subject  Application model linked to every logged invoice, e.g. the local invoice record.
     */
    public function submit(InvoiceSubmission|InvoiceBuilder|array $invoices, ?Model $subject = null, ?string $tenant = null): NavTransaction
    {
        $submissions = array_map(
            static fn (InvoiceSubmission|InvoiceBuilder $i): InvoiceSubmission => $i instanceof InvoiceBuilder ? $i->submission() : $i,
            is_array($invoices) ? $invoices : [$invoices],
        );

        $items = array_map(static fn (InvoiceSubmission $s): array => [
            'operation' => $s->operation->value,
            'invoice_number' => InvoiceData::fromXmlString($s->invoiceXml)->invoiceNumber,
            'payload' => $s->invoiceXml,
            'electronic_invoice_hash' => $s->electronicInvoiceHash,
            'electronic_invoice_hash_algorithm' => $s->electronicInvoiceHash === null ? null : $s->electronicInvoiceHashAlgorithm->value,
        ], $submissions);

        return $this->queue(TransactionKind::Invoice, $items, $subject, $tenant);
    }

    /**
     * Record technical annulments in the transaction log and queue them for submission.
     *
     * @param  TechnicalAnnulment|list<TechnicalAnnulment>  $annulments
     */
    public function annul(TechnicalAnnulment|array $annulments, ?Model $subject = null, ?string $tenant = null): NavTransaction
    {
        $items = array_map(static fn (TechnicalAnnulment $a): array => [
            'operation' => 'ANNUL',
            'invoice_number' => $a->invoiceNumber,
            'payload' => $a->toXml(),
        ], is_array($annulments) ? $annulments : [$annulments]);

        return $this->queue(TransactionKind::Annulment, $items, $subject, $tenant);
    }

    /**
     * @param  list<mixed>  $arguments
     */
    public function __call(string $method, array $arguments): mixed
    {
        return $this->client()->{$method}(...$arguments);
    }

    /**
     * @param  list<array{operation: string, invoice_number: string, payload: string, electronic_invoice_hash?: string|null, electronic_invoice_hash_algorithm?: string|null}>  $items
     */
    private function queue(TransactionKind $kind, array $items, ?Model $subject, ?string $tenant): NavTransaction
    {
        if ($items === [] || count($items) > 100) {
            throw new NavException(sprintf('A NAV request carries 1 to 100 documents, %d given.', count($items)));
        }

        // Resolving the client first fails fast on missing credentials, before anything is logged.
        $taxNumber = $this->client($tenant)->credentials->taxNumber;

        /** @var class-string<NavTransaction> $model */
        $model = $this->config->get('nav-online-szamla.transactions.model', NavTransaction::class);

        $transaction = new $model([
            'kind' => $kind,
            'tenant' => $tenant,
            'tax_number' => $taxNumber,
            'state' => TransactionState::Pending,
        ]);

        $transaction->getConnection()->transaction(function () use ($transaction, $items, $subject): void {
            $transaction->save();

            foreach ($items as $i => $item) {
                $record = $transaction->items()->make($item + [
                    'position' => $i + 1,
                    'payload_hash' => hash('sha256', $item['payload']),
                ]);
                if ($subject !== null) {
                    $record->subject()->associate($subject);
                }
                $record->save();
            }
        });

        SubmitTransaction::dispatch($transaction->id)->afterCommit();

        return $transaction;
    }

    protected function software(): Software
    {
        if ($this->software !== null) {
            return $this->software;
        }

        /** @var array<string, mixed> $settings */
        $settings = (array) $this->config->get('nav-online-szamla.software', []);

        return $this->software = Software::fromArray($settings);
    }

    private function baseUrl(): string
    {
        $environment = $this->config->get('nav-online-szamla.environment');
        $url = is_string($environment) ? $this->config->get('nav-online-szamla.urls.'.$environment) : null;

        if (! is_string($url) || $url === '') {
            throw new ConfigurationException(sprintf('No NAV API URL is configured for the environment "%s".', is_scalar($environment) ? $environment : ''));
        }

        return $url;
    }

    private function logger(): LoggerInterface
    {
        $channel = $this->config->get('nav-online-szamla.log_channel');

        return is_string($channel) && $channel !== '' && $this->log !== null ? $this->log->channel($channel) : new NullLogger;
    }

    private function int(string $key, int $default): int
    {
        $value = $this->config->get('nav-online-szamla.'.$key, $default);

        return is_numeric($value) ? (int) $value : $default;
    }
}
