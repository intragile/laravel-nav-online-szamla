<?php

declare(strict_types=1);

namespace IntrAgile\NavOnlineSzamla\Testing;

use Closure;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use IntrAgile\NavOnlineSzamla\Config\Credentials;
use IntrAgile\NavOnlineSzamla\Config\Software;
use IntrAgile\NavOnlineSzamla\Enums\BusinessResultCode;
use IntrAgile\NavOnlineSzamla\Enums\Incorporation;
use IntrAgile\NavOnlineSzamla\Enums\InvoiceStatus;
use IntrAgile\NavOnlineSzamla\Enums\RequestStatus;
use IntrAgile\NavOnlineSzamla\Enums\Source;
use IntrAgile\NavOnlineSzamla\Enums\TaxpayerAddressType;
use IntrAgile\NavOnlineSzamla\Exceptions\ConfigurationException;
use IntrAgile\NavOnlineSzamla\Exceptions\NavApiException;
use IntrAgile\NavOnlineSzamla\Exceptions\NavException;
use IntrAgile\NavOnlineSzamla\Exceptions\TransportException;
use IntrAgile\NavOnlineSzamla\Http\Transport;
use IntrAgile\NavOnlineSzamla\Invoice\InvoiceBuilder;
use IntrAgile\NavOnlineSzamla\Invoice\Model\InvoiceData;
use IntrAgile\NavOnlineSzamla\Models\NavTransaction;
use IntrAgile\NavOnlineSzamla\Models\NavTransactionItem;
use IntrAgile\NavOnlineSzamla\NavClient;
use IntrAgile\NavOnlineSzamla\NavManager;
use IntrAgile\NavOnlineSzamla\Requests\InvoiceNumberQuery;
use IntrAgile\NavOnlineSzamla\Requests\InvoiceSubmission;
use IntrAgile\NavOnlineSzamla\Requests\TechnicalAnnulment;
use IntrAgile\NavOnlineSzamla\Responses\AuditData;
use IntrAgile\NavOnlineSzamla\Responses\BusinessValidationMessage;
use IntrAgile\NavOnlineSzamla\Responses\InvoiceDataResult;
use IntrAgile\NavOnlineSzamla\Responses\ProcessingResult;
use IntrAgile\NavOnlineSzamla\Responses\Taxpayer;
use IntrAgile\NavOnlineSzamla\Responses\TaxpayerAddress;
use IntrAgile\NavOnlineSzamla\Responses\Transaction;
use IntrAgile\NavOnlineSzamla\Responses\TransactionStatus;
use PHPUnit\Framework\Assert as PHPUnit;

/**
 * Replaces NAV with an in-memory implementation for tests: Nav::fake().
 *
 * Accepted invoices are kept, so queryInvoiceCheck, queryInvoiceData, queryTransactionStatus
 * and queryTransactionList answer consistently with what the application sent. Queued
 * submissions run through the real jobs, without delays.
 */
class NavFake extends NavManager
{
    /** @var list<RecordedCall> */
    private array $calls = [];

    /** @var list<SubmittedInvoice> */
    private array $invoices = [];

    /** @var list<array{annulment: TechnicalAnnulment, tenant: string|null}> */
    private array $annulments = [];

    /** @var array<string, list<Closure(mixed...): mixed>> */
    private array $once = [];

    /** @var array<string, Closure(mixed...): mixed> */
    private array $responses = [];

    /** @var array<string, Taxpayer|null> */
    private array $taxpayers = [];

    /** @var array<string, array{status: InvoiceStatus, code: string, message: string}> */
    private array $outcomes = [];

    /** @var array<string, string> */
    private array $taxNumbers = [];

    /** @var array<string, array{taxNumber: string, login: string, at: DateTimeImmutable, annulment: bool, payloads: list<string>, results: list<ProcessingResult>}> */
    private array $transactions = [];

    /** @var array<string, array{xml: string, transactionId: string, index: int, at: DateTimeImmutable, login: string, hash: string|null}> */
    private array $stored = [];

    /** @var array<string, FakeNavClient> */
    private array $fakeClients = [];

    /**
     * Queued submissions run without waiting between steps.
     */
    public function withoutDelays(): static
    {
        $this->config->set('nav-online-szamla.polling.delays', [0]);
        $this->config->set('nav-online-szamla.reconciliation.delay', 0);

        return $this;
    }

    public function client(?string $tenant = null): NavClient
    {
        return $this->fakeClients[$tenant ?? ''] ??= new FakeNavClient(
            $this->fakeCredentials($tenant),
            $this->fakeSoftware(),
            new Transport($this->http, 'https://nav.invalid'),
            $this,
            $tenant,
        );
    }

    public function submit(InvoiceSubmission|InvoiceBuilder|array $invoices, ?Model $subject = null, ?string $tenant = null): NavTransaction
    {
        $transaction = parent::submit($invoices, $subject, $tenant);
        $this->invoices = [...$this->invoices, ...$transaction->items->map(
            static fn (NavTransactionItem $item): SubmittedInvoice => new SubmittedInvoice($item->toSubmission(), $tenant, $transaction),
        )->values()->all()];

        return $transaction;
    }

    public function annul(TechnicalAnnulment|array $annulments, ?Model $subject = null, ?string $tenant = null): NavTransaction
    {
        $transaction = parent::annul($annulments, $subject, $tenant);
        foreach ($transaction->items as $item) {
            $this->annulments[] = ['annulment' => $item->toAnnulment(), 'tenant' => $tenant];
        }

        return $transaction;
    }

    // Configuration

    /**
     * The technical user's tax number for a tenant whose credentials are not configured.
     */
    public function withTaxNumber(string $taxNumber, ?string $tenant = null): static
    {
        $this->taxNumbers[$tenant ?? ''] = self::taxpayerId($taxNumber);
        unset($this->fakeClients[$tenant ?? '']);

        return $this;
    }

    /**
     * The answer of queryTaxpayer: a Taxpayer, a company name to build one from, or null for an unknown tax number.
     */
    public function taxpayer(string $taxNumber, Taxpayer|string|null $taxpayer): static
    {
        $this->taxpayers[self::taxpayerId($taxNumber)] = is_string($taxpayer) ? self::makeTaxpayer($taxpayer, $taxNumber) : $taxpayer;

        return $this;
    }

    /**
     * Have NAV abort the invoice with a blocking error when it is processed.
     */
    public function rejectInvoice(string $invoiceNumber, string $errorCode = 'INVOICE_NUMBER_NOT_UNIQUE', string $message = 'Rejected by Nav::fake().'): static
    {
        $this->outcomes[$invoiceNumber] = ['status' => InvoiceStatus::Aborted, 'code' => $errorCode, 'message' => $message];

        return $this;
    }

    /**
     * Have NAV accept the invoice with a warning.
     */
    public function warnInvoice(string $invoiceNumber, string $errorCode, string $message = 'Warning from Nav::fake().'): static
    {
        $this->outcomes[$invoiceNumber] = ['status' => InvoiceStatus::Done, 'code' => $errorCode, 'message' => $message];

        return $this;
    }

    /**
     * Throw the exception on the next call of the operation.
     */
    public function failNext(string $operation, NavException $exception): static
    {
        $this->once[$operation][] = static fn (): never => throw $exception;

        return $this;
    }

    /**
     * Answer every call of the operation with the callback's result. The callback receives the operation's arguments.
     *
     * @param  Closure(mixed...): mixed  $callback
     */
    public function respond(string $operation, Closure $callback): static
    {
        $this->responses[$operation] = $callback;

        return $this;
    }

    /**
     * A NAV error response as NavApiException, for failNext().
     */
    public static function apiError(string $errorCode, string $operation = 'manageInvoice', string $message = 'Error from Nav::fake().', int $httpStatus = 400): NavApiException
    {
        return new NavApiException("NAV {$operation}: {$errorCode} – {$message}", $operation, 'FAKE', $httpStatus, $errorCode);
    }

    /**
     * A lost connection as TransportException, for failNext().
     */
    public static function connectionFailure(string $operation = 'manageInvoice'): TransportException
    {
        return new TransportException("NAV {$operation}: connection failed (Nav::fake())", $operation, 'FAKE');
    }

    public static function makeTaxpayer(string $name, string $taxNumber = '12345678-2-41', Incorporation $incorporation = Incorporation::Organization): Taxpayer
    {
        $parts = explode('-', $taxNumber);

        return new Taxpayer(
            true,
            new DateTimeImmutable('today'),
            $name,
            null,
            self::taxpayerId($taxNumber),
            $parts[1] ?? null,
            $parts[2] ?? null,
            $incorporation,
            null,
            [new TaxpayerAddress(TaxpayerAddressType::Hq, 'HU', null, '1011', 'Budapest', 'Fő', 'utca', '1.')],
        );
    }

    // Assertions

    /**
     * @param  string|Closure(InvoiceData, SubmittedInvoice): bool|null  $invoice  Invoice number or a filter.
     */
    public function assertInvoiceSubmitted(string|Closure|null $invoice = null, ?string $tenant = null): void
    {
        PHPUnit::assertNotEmpty(
            $this->matchingInvoices($invoice, $tenant),
            'The expected invoice was not submitted to NAV'.(is_string($invoice) ? ": {$invoice}." : '.'),
        );
    }

    /**
     * @param  string|Closure(InvoiceData, SubmittedInvoice): bool|null  $invoice
     */
    public function assertInvoiceNotSubmitted(string|Closure|null $invoice = null, ?string $tenant = null): void
    {
        PHPUnit::assertEmpty(
            $this->matchingInvoices($invoice, $tenant),
            'An unexpected invoice was submitted to NAV'.(is_string($invoice) ? ": {$invoice}." : '.'),
        );
    }

    public function assertNothingSubmitted(): void
    {
        PHPUnit::assertSame([], $this->invoices, 'Invoices were submitted to NAV unexpectedly.');
        PHPUnit::assertSame([], $this->annulments, 'Annulments were submitted to NAV unexpectedly.');
    }

    /**
     * Number of distinct invoices submitted; queuing and then sending the same invoice counts once.
     */
    public function assertSubmittedCount(int $expected): void
    {
        PHPUnit::assertCount($expected, $this->distinctInvoices(), sprintf('Expected %d invoices submitted to NAV, %d were.', $expected, count($this->distinctInvoices())));
    }

    /**
     * @param  string|Closure(TechnicalAnnulment): bool|null  $annulment  Number of the annulled invoice or a filter.
     */
    public function assertAnnulmentSubmitted(string|Closure|null $annulment = null, ?string $tenant = null): void
    {
        $matches = array_filter($this->annulments, static fn (array $a): bool => ($tenant === null || $a['tenant'] === $tenant) && match (true) {
            $annulment === null => true,
            is_string($annulment) => $a['annulment']->invoiceNumber === $annulment,
            default => $annulment($a['annulment']),
        });

        PHPUnit::assertNotEmpty($matches, 'The expected technical annulment was not submitted to NAV.');
    }

    public function assertTaxpayerQueried(?string $taxNumber = null): void
    {
        $this->assertSent('queryTaxpayer', $taxNumber === null ? null : static function (RecordedCall $call) use ($taxNumber): bool {
            $queried = $call->arguments[0] ?? null;

            return is_string($queried) && self::taxpayerId($queried) === self::taxpayerId($taxNumber);
        });
    }

    /**
     * @param  Closure(RecordedCall): bool|null  $callback
     */
    public function assertSent(string $operation, ?Closure $callback = null): void
    {
        PHPUnit::assertNotEmpty($this->recorded($operation, $callback), "The expected NAV {$operation} call was not made.");
    }

    /**
     * @param  Closure(RecordedCall): bool|null  $callback
     */
    public function assertNotSent(string $operation, ?Closure $callback = null): void
    {
        PHPUnit::assertEmpty($this->recorded($operation, $callback), "An unexpected NAV {$operation} call was made.");
    }

    public function assertSentCount(string $operation, int $expected): void
    {
        $count = count($this->recorded($operation));
        PHPUnit::assertSame($expected, $count, "Expected {$expected} NAV {$operation} calls, {$count} were made.");
    }

    public function assertNothingSent(): void
    {
        PHPUnit::assertSame([], $this->calls, 'NAV operations were called unexpectedly: '.implode(', ', array_map(static fn (RecordedCall $c): string => $c->operation, $this->calls)));
    }

    /**
     * @return list<SubmittedInvoice>
     */
    public function submittedInvoices(): array
    {
        return $this->invoices;
    }

    /**
     * @param  Closure(RecordedCall): bool|null  $callback
     * @return list<RecordedCall>
     */
    public function recorded(?string $operation = null, ?Closure $callback = null): array
    {
        return array_values(array_filter(
            $this->calls,
            static fn (RecordedCall $c): bool => ($operation === null || $c->operation === $operation) && ($callback === null || $callback($c)),
        ));
    }

    // Used by FakeNavClient

    /**
     * @internal
     */
    public function recordCall(RecordedCall $call): void
    {
        $this->calls[] = $call;
    }

    /**
     * @internal
     */
    public function hasResponse(string $operation): bool
    {
        return isset($this->responses[$operation]) || ($this->once[$operation] ?? []) !== [];
    }

    /**
     * @internal
     *
     * @param  list<mixed>  $arguments
     */
    public function response(string $operation, array $arguments): mixed
    {
        $callback = ($this->once[$operation] ?? []) !== []
            ? array_shift($this->once[$operation])
            : $this->responses[$operation] ?? null;

        return $callback === null ? null : $callback(...$arguments);
    }

    /**
     * @internal
     *
     * @param  list<InvoiceSubmission>  $invoices
     */
    public function recordSubmittedInvoices(array $invoices, ?string $tenant): void
    {
        foreach ($invoices as $invoice) {
            $this->invoices[] = new SubmittedInvoice($invoice, $tenant);
        }
    }

    /**
     * @internal
     *
     * @param  list<TechnicalAnnulment>  $annulments
     */
    public function recordSubmittedAnnulments(array $annulments, ?string $tenant): void
    {
        foreach ($annulments as $annulment) {
            $this->annulments[] = ['annulment' => $annulment, 'tenant' => $tenant];
        }
    }

    /**
     * @internal
     *
     * @param  list<InvoiceSubmission>  $invoices
     */
    public function storeInvoices(Credentials $credentials, array $invoices): string
    {
        $transactionId = $this->newTransactionId();
        $now = new DateTimeImmutable;
        $results = [];

        foreach ($invoices as $i => $invoice) {
            $number = InvoiceData::fromXmlString($invoice->invoiceXml)->invoiceNumber;
            $outcome = $this->outcomes[$number] ?? null;
            $key = $credentials->taxNumber.'|'.$number;
            $status = $outcome['status'] ?? InvoiceStatus::Done;

            if ($status === InvoiceStatus::Done && isset($this->stored[$key])) {
                $outcome = ['status' => InvoiceStatus::Aborted, 'code' => 'INVOICE_NUMBER_NOT_UNIQUE', 'message' => "Invoice {$number} was already submitted."];
                $status = InvoiceStatus::Aborted;
            }

            if ($status === InvoiceStatus::Done) {
                $this->stored[$key] = ['xml' => $invoice->invoiceXml, 'transactionId' => $transactionId, 'index' => $i + 1, 'at' => $now, 'login' => $credentials->login, 'hash' => $invoice->electronicInvoiceHash];
            }

            $results[] = $this->result($i + 1, $status, $outcome, $invoice->invoiceXml);
        }

        $this->transactions[$transactionId] = [
            'taxNumber' => $credentials->taxNumber, 'login' => $credentials->login, 'at' => $now, 'annulment' => false,
            'payloads' => array_map(static fn (InvoiceSubmission $s): string => $s->invoiceXml, $invoices), 'results' => $results,
        ];

        return $transactionId;
    }

    /**
     * @internal
     *
     * @param  list<TechnicalAnnulment>  $annulments
     */
    public function storeAnnulments(Credentials $credentials, array $annulments): string
    {
        $transactionId = $this->newTransactionId();
        $payloads = array_map(static fn (TechnicalAnnulment $a): string => $a->toXml(), $annulments);

        $this->transactions[$transactionId] = [
            'taxNumber' => $credentials->taxNumber, 'login' => $credentials->login, 'at' => new DateTimeImmutable, 'annulment' => true,
            'payloads' => $payloads,
            'results' => array_map(fn (int $i): ProcessingResult => $this->result($i + 1, InvoiceStatus::Done, null, $payloads[$i]), array_keys($payloads)),
        ];

        return $transactionId;
    }

    /**
     * @internal
     */
    public function transactionStatus(string $transactionId, bool $returnOriginalRequest): TransactionStatus
    {
        $results = $this->transactions[$transactionId]['results'] ?? [];
        if (! $returnOriginalRequest) {
            $results = array_map(static fn (ProcessingResult $r): ProcessingResult => new ProcessingResult(
                $r->index, $r->batchIndex, $r->status, $r->technicalValidationMessages, $r->businessValidationMessages, $r->compressedContent,
            ), $results);
        }

        return new TransactionStatus($transactionId, $results, $results === [] ? null : '3.0');
    }

    /**
     * @internal
     */
    public function invoiceData(Credentials $credentials, InvoiceNumberQuery $query): ?InvoiceDataResult
    {
        $stored = $this->stored[($query->supplierTaxNumber ?? $credentials->taxNumber).'|'.$query->invoiceNumber] ?? null;
        if ($stored === null) {
            return null;
        }

        return new InvoiceDataResult(
            $stored['xml'],
            new AuditData($stored['at'], $stored['login'], Source::Mgm, $stored['transactionId'], $stored['index'], null, '3.0'),
            false,
            $stored['hash'],
        );
    }

    /**
     * @internal
     *
     * @return list<Transaction>
     */
    public function transactionList(Credentials $credentials, DateTimeInterface $from, DateTimeInterface $to, ?RequestStatus $status): array
    {
        if ($status !== null && $status !== RequestStatus::Finished) {
            return [];
        }

        $list = [];
        foreach ($this->transactions as $id => $t) {
            if ($t['taxNumber'] === $credentials->taxNumber && $t['at'] >= $from && $t['at'] <= $to) {
                $list[] = new Transaction($id, $t['at'], $t['login'], Source::Mgm, RequestStatus::Finished, $t['annulment'], '3.0', count($t['payloads']));
            }
        }

        return $list;
    }

    /**
     * @internal
     */
    public function taxpayerFor(string $taxNumber): ?Taxpayer
    {
        $id = self::taxpayerId($taxNumber);
        if (strlen($id) !== 8) {
            throw new NavException('A Hungarian tax number starts with 8 digits.');
        }

        return $this->taxpayers[$id] ?? null;
    }

    /**
     * @param  string|Closure(InvoiceData, SubmittedInvoice): bool|null  $invoice
     * @return list<SubmittedInvoice>
     */
    private function matchingInvoices(string|Closure|null $invoice, ?string $tenant): array
    {
        return array_values(array_filter($this->invoices, static fn (SubmittedInvoice $s): bool => ($tenant === null || $s->tenant === $tenant) && match (true) {
            $invoice === null => true,
            is_string($invoice) => $s->invoiceNumber() === $invoice,
            default => $invoice($s->invoice(), $s),
        }));
    }

    /**
     * @return array<string, SubmittedInvoice>
     */
    private function distinctInvoices(): array
    {
        $distinct = [];
        foreach ($this->invoices as $invoice) {
            $distinct[($invoice->tenant ?? '').'|'.hash('sha256', $invoice->submission->invoiceXml)] = $invoice;
        }

        return $distinct;
    }

    /**
     * @param  array{status: InvoiceStatus, code: string, message: string}|null  $outcome
     */
    private function result(int $index, InvoiceStatus $status, ?array $outcome, string $payload): ProcessingResult
    {
        $messages = $outcome === null ? [] : [new BusinessValidationMessage(
            $status === InvoiceStatus::Aborted ? BusinessResultCode::Error : BusinessResultCode::Warn,
            $outcome['code'],
            $outcome['message'],
        )];

        return new ProcessingResult($index, null, $status, [], $messages, false, $payload);
    }

    private function newTransactionId(): string
    {
        $alphabet = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        do {
            $id = 'FAKE';
            for ($i = 0; $i < 12; $i++) {
                $id .= $alphabet[random_int(0, 35)];
            }
        } while (isset($this->transactions[$id]));

        return $id;
    }

    private function fakeCredentials(?string $tenant): Credentials
    {
        $taxNumber = $this->taxNumbers[$tenant ?? ''] ?? null;
        if ($taxNumber === null) {
            try {
                return $this->credentials->resolve($tenant);
            } catch (ConfigurationException) {
                $taxNumber = '12345678';
            }
        }

        return new Credentials('navfakeuser'.substr(md5($tenant ?? ''), 0, 4), 'fake', $taxNumber, 'fake-sign-key', 'fake-exchangekey');
    }

    private function fakeSoftware(): Software
    {
        try {
            return $this->software();
        } catch (ConfigurationException) {
            return new Software('HU00000000-FAKE000', 'Nav::fake()', 'ONLINE_SERVICE', '1.0', 'Nav::fake()', 'fake@example.com');
        }
    }

    private static function taxpayerId(string $taxNumber): string
    {
        return substr(preg_replace('/\D/', '', $taxNumber) ?? '', 0, 8);
    }
}
