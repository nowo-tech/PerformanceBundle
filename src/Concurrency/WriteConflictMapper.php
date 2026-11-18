<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Concurrency;

use Nowo\TransactionContract\TransientStorageFailure;
use Nowo\TransactionDoctrine\Symfony\Conflict\ConflictResponsePolicy;
use Nowo\TransactionDoctrine\TransientFailureClassifier;
use Nowo\TransactionDoctrine\WriteUnit\ConcurrencyConflict;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Throwable;

/**
 * Maps a lock conflict of a dashboard write to the platform answer (REQ-RUNTIME-003 rule 6): 409 (or the status the
 * host configured through nowo-tech/transaction-doctrine's `conflict_response`) with `Retry-After` and
 * `CONFLICT_001` when nothing was committed, or with `CONFLICT_002` and no `Retry-After` when part of the work was
 * already committed. Only lock conflicts are mapped (MySQL 1213 / 1205, MariaDB 1020, PostgreSQL 40001 / 40P01 /
 * 55P03); unique violations, lost connections and every other error are not.
 *
 * Stateless: safe as a shared service in a long-running worker.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final readonly class WriteConflictMapper
{
    private TransientFailureClassifier $classifier;

    /**
     * @param ConflictResponsePolicy|null $policy The host's shared policy (only when NowoTransactionDoctrineBundle is registered)
     */
    public function __construct(
        #[Autowire(service: 'nowo_transaction_doctrine.conflict_response_policy')]
        private ?ConflictResponsePolicy $policy = null,
    ) {
        $this->classifier = new TransientFailureClassifier();
    }

    /**
     * Whether the failure (or anything in its previous chain) is a lock conflict.
     */
    public function isConflict(Throwable $failure): bool
    {
        return $failure instanceof TransientStorageFailure || $this->classifier->isTransient($failure);
    }

    /**
     * The HTTP exception answering a lock conflict.
     */
    public function toHttpException(Throwable $failure): WriteConflictException
    {
        $policy    = $this->policy ?? new ConflictResponsePolicy();
        $committed = false;
        $hint      = null;

        for ($current = $failure; $current instanceof Throwable; $current = $current->getPrevious()) {
            if ($current instanceof ConcurrencyConflict && $current->committed) {
                $committed = true;
            }
            if ($hint === null && $current instanceof TransientStorageFailure) {
                $hint = $current->retryAfterSeconds();
            }
        }

        if ($committed) {
            return new WriteConflictException(WriteConflictException::CODE_PARTIALLY_SAVED, $policy->status, [
                'Cache-Control' => 'no-store, private',
            ], $failure);
        }

        return new WriteConflictException(WriteConflictException::CODE_RETRY_SAFE, $policy->status, $policy->headers($hint) + [
            'Cache-Control' => 'no-store, private',
        ], $failure);
    }
}
