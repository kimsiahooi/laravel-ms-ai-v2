<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\StockMovementReason;
use App\Exceptions\InsufficientStockException;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Tenant;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use App\Services\StockService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

/**
 * Hammer {@see StockService} from several processes at once and check what survived.
 *
 * **Why this exists.** This project has no test suite, by a standing decision. Every
 * other module is checked by driving the real screens in a browser, which works because
 * a person clicks one button at a time — and that is exactly what cannot check a lock.
 * The bugs this class can have are races: two issues of the last unit both succeeding,
 * a ledger that no longer sums to on-hand, two transfers deadlocking. None of them is
 * reachable by clicking.
 *
 * So this is a tool, not a suite: nothing runs it in CI, it is invoked by hand, and it
 * reports what it saw rather than asserting. It costs one command and a few seconds of
 * CPU, which is the whole reason a suite was declined.
 *
 * **How it works.** The parent creates a scenario, then spawns N `stock:hammer --worker`
 * children through `artisan`, each a real process with its own database connection —
 * a loop inside one process would share a connection and never contend for anything.
 *
 *     php artisan stock:hammer                 # oversell: 20 workers race for 10 units
 *     php artisan stock:hammer --deadlock      # A→B and B→A transfers, in a loop
 *     php artisan stock:hammer --ledger        # concurrent movements, then reconcile
 */
final class HammerStock extends Command
{
    protected $signature = 'stock:hammer
        {--workers=20 : How many processes to run at once}
        {--deadlock : Cross transfers instead, to test lock ordering}
        {--ledger : Mixed movements, then check the ledger sums to on-hand}
        {--worker= : Internal. The child mode, given the scenario name}
        {--tenant= : Internal. Which workspace the child should attach to}
        {--warehouse= : Internal}
        {--other= : Internal}
        {--product= : Internal}';

    protected $description = 'Run StockService from many processes at once and report what held';

    public function handle(StockService $stock): int
    {
        return $this->option('worker') === null
            ? $this->parent()
            : $this->worker($stock, (string) $this->option('worker'));
    }

    /** Set the scenario up, run the children, then look at the damage. */
    private function parent(): int
    {
        $tenant = Tenant::query()->first();

        if ($tenant === null) {
            $this->components->error('No workspace to run against.');

            return self::FAILURE;
        }

        tenancy()->initialize($tenant);

        $warehouses = Warehouse::query()->orderBy('id')->take(2)->get();
        $product = Product::query()->first();

        if ($warehouses->count() < 2 || $product === null) {
            $this->components->error('Needs two warehouses and a product. Run `php artisan tenant:seed-demo` first.');

            return self::FAILURE;
        }

        [$warehouse, $other] = [$warehouses[0], $warehouses[1]];

        $scenario = match (true) {
            (bool) $this->option('deadlock') => 'deadlock',
            (bool) $this->option('ledger') => 'ledger',
            default => 'oversell',
        };

        $this->clear($warehouse, $other, $product);

        return match ($scenario) {
            'deadlock' => $this->runDeadlock($tenant, $warehouse, $other, $product),
            'ledger' => $this->runLedger($tenant, $warehouse, $other, $product),
            default => $this->runOversell($tenant, $warehouse, $other, $product),
        };
    }

    /**
     * Twenty processes each try to take one unit from a shelf holding ten.
     *
     * Exactly ten must succeed. Eleven means the lock is not holding; nine means
     * something failed for a reason worth looking at.
     */
    private function runOversell(Tenant $tenant, Warehouse $warehouse, Warehouse $other, Product $product): int
    {
        $workers = (int) $this->option('workers');

        app(StockService::class)->setLevel(
            $warehouse, $product, '10', StockMovementReason::Adjustment,
        );

        $this->components->info("Oversell: {$workers} processes, each taking 1 unit from a shelf of 10.");

        $results = $this->spawn($tenant, 'oversell', $workers, $warehouse, $other, $product);
        $took = count(array_filter($results, static fn (string $r): bool => $r === 'ok'));
        $refused = count(array_filter($results, static fn (string $r): bool => $r === 'refused'));
        $other_ = count($results) - $took - $refused;

        $onHand = app(StockService::class)->onHand($warehouse, $product);

        $this->table(
            ['took a unit', 'refused', 'errored', 'on hand after'],
            [[$took, $refused, $other_, $onHand]],
        );

        return $this->verdict(
            $took === 10 && bccomp($onHand, '0', 4) === 0,
            $took === 10
                ? 'Exactly 10 succeeded and the shelf is empty.'
                : "{$took} processes took a unit from a shelf of 10.",
        );
    }

    /**
     * Transfers in both directions at once — the shape that deadlocks when locks are
     * taken in call order rather than a fixed one.
     */
    private function runDeadlock(Tenant $tenant, Warehouse $warehouse, Warehouse $other, Product $product): int
    {
        $workers = (int) $this->option('workers');

        app(StockService::class)->setLevel($warehouse, $product, '5000', StockMovementReason::Adjustment);
        app(StockService::class)->setLevel($other, $product, '5000', StockMovementReason::Adjustment);

        $this->components->info("Deadlock: {$workers} processes transferring in both directions at once.");

        $results = $this->spawn($tenant, 'deadlock', $workers, $warehouse, $other, $product);
        $deadlocked = count(array_filter(
            $results,
            static fn (string $r): bool => str_contains($r, 'deadlock'),
        ));
        $ok = count(array_filter($results, static fn (string $r): bool => $r === 'ok'));

        $total = bcadd(
            app(StockService::class)->onHand($warehouse, $product),
            app(StockService::class)->onHand($other, $product),
            4,
        );

        $this->table(
            ['completed', 'deadlocked', 'total across both (should be 10000)'],
            [[$ok, $deadlocked, $total]],
        );

        return $this->verdict(
            $deadlocked === 0 && bccomp($total, '10000', 4) === 0,
            $deadlocked === 0
                ? 'No deadlocks, and no stock was created or lost in transit.'
                : "{$deadlocked} processes deadlocked.",
        );
    }

    /**
     * Mixed concurrent movements, then the question the whole design rests on: does the
     * append-only ledger still sum to the materialized on-hand?
     */
    private function runLedger(Tenant $tenant, Warehouse $warehouse, Warehouse $other, Product $product): int
    {
        $workers = (int) $this->option('workers');

        app(StockService::class)->setLevel($warehouse, $product, '1000', StockMovementReason::Adjustment);

        $this->components->info("Ledger: {$workers} processes making mixed movements, then reconciling.");

        $this->spawn($tenant, 'ledger', $workers, $warehouse, $other, $product);

        $onHand = app(StockService::class)->onHand($warehouse, $product);
        // SUM over an empty set comes back as 0, so this is always a number.
        $summed = (string) StockMovement::query()
            ->where('warehouse_id', $warehouse->id)
            ->where('stockable_type', $product->getMorphClass())
            ->where('stockable_id', $product->getKey())
            ->sum('quantity');

        $this->table(
            ['on hand', 'sum of ledger', 'movements'],
            [[$onHand, $summed, StockMovement::query()->count()]],
        );

        return $this->verdict(
            bccomp($onHand, $summed, 4) === 0,
            bccomp($onHand, $summed, 4) === 0
                ? 'The ledger sums to on-hand exactly.'
                : "on-hand {$onHand} but the ledger sums to {$summed}.",
        );
    }

    /**
     * One child. Reports a single word on stdout so the parent can count outcomes
     * without parsing anything.
     */
    private function worker(StockService $stock, string $scenario): int
    {
        tenancy()->initialize(Tenant::query()->findOrFail($this->option('tenant')));

        $warehouse = Warehouse::query()->findOrFail($this->option('warehouse'));
        $other = Warehouse::query()->findOrFail($this->option('other'));
        $product = Product::query()->findOrFail($this->option('product'));

        try {
            match ($scenario) {
                'oversell' => $stock->record($warehouse, $product, '-1', StockMovementReason::Adjustment),
                'deadlock' => $this->crossTransfer($stock, $warehouse, $other, $product),
                default => $this->mixed($stock, $warehouse, $product),
            };

            $this->line('ok');
        } catch (InsufficientStockException) {
            $this->line('refused');
        } catch (\Throwable $e) {
            $this->line(str_contains(strtolower($e->getMessage()), 'deadlock') ? 'deadlock' : 'error');
        }

        return self::SUCCESS;
    }

    /** Half the children go one way, half the other — decided by the process id. */
    private function crossTransfer(StockService $stock, Warehouse $a, Warehouse $b, Product $product): void
    {
        [$from, $to] = getmypid() % 2 === 0 ? [$a, $b] : [$b, $a];

        for ($i = 0; $i < 15; $i++) {
            $stock->transfer($from, $to, $product, '1');
        }
    }

    /** A run of ins and outs that nets to zero, so the total is predictable. */
    private function mixed(StockService $stock, Warehouse $warehouse, Product $product): void
    {
        for ($i = 0; $i < 10; $i++) {
            $stock->record($warehouse, $product, '3.5', StockMovementReason::Adjustment);
            $stock->record($warehouse, $product, '-3.5', StockMovementReason::Adjustment);
        }
    }

    /**
     * Start the children and wait. Real processes, not threads: each needs its own
     * database connection, or there is nothing to contend over.
     *
     * @return list<string>
     */
    private function spawn(Tenant $tenant, string $scenario, int $workers, Warehouse $warehouse, Warehouse $other, Product $product): array
    {
        $processes = [];

        for ($i = 0; $i < $workers; $i++) {
            $process = new Process([
                PHP_BINARY, 'artisan', 'stock:hammer',
                '--worker='.$scenario,
                '--tenant='.$tenant->getTenantKey(),
                '--warehouse='.$warehouse->id,
                '--other='.$other->id,
                '--product='.$product->id,
            ], base_path());

            $process->setTimeout(120);
            $process->start();
            $processes[] = $process;
        }

        $results = [];

        foreach ($processes as $process) {
            $process->wait();
            $results[] = trim($process->getOutput()) ?: 'error';
        }

        return $results;
    }

    /** Wipe this item's stock so a run starts from a known place. */
    private function clear(Warehouse $warehouse, Warehouse $other, Product $product): void
    {
        DB::transaction(function () use ($warehouse, $other, $product): void {
            StockMovement::query()
                ->where('stockable_type', $product->getMorphClass())
                ->where('stockable_id', $product->getKey())
                ->whereIn('warehouse_id', [$warehouse->id, $other->id])
                ->delete();

            WarehouseStock::query()
                ->where('stockable_type', $product->getMorphClass())
                ->where('stockable_id', $product->getKey())
                ->whereIn('warehouse_id', [$warehouse->id, $other->id])
                ->delete();
        });
    }

    private function verdict(bool $held, string $sentence): int
    {
        if ($held) {
            $this->components->info($sentence);

            return self::SUCCESS;
        }

        $this->components->error($sentence);

        return self::FAILURE;
    }
}
