<?php
declare(strict_types=1);

namespace app\command;

use app\model\AuditLog;
use app\model\Listing;
use app\model\Order;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;
use think\facade\Log;

/**
 * Auto-expire pending_match orders older than 30 minutes.
 *
 * This command is designed to run every minute via cron. It is fully
 * idempotent: orders already expired will not be touched because the
 * WHERE clause filters on status = 'pending_match'.
 */
class OrderExpireCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('order:expire')
             ->setDescription('Auto-expire pending_match orders older than 30 minutes');
    }

    protected function execute(Input $input, Output $output): int
    {
        $cutoff = date('Y-m-d H:i:s', strtotime('-30 minutes'));

        $pendingOrders = Order::where('status', 'pending_match')
            ->where('created_at', '<', $cutoff)
            ->select();

        $expiredCount = 0;

        Db::startTrans();
        try {
            foreach ($pendingOrders as $order) {
                // Expire the order
                $order->status = 'expired';
                $order->expires_at = date('Y-m-d H:i:s');
                $order->save();

                // Restore the listing to active status so it can be matched again
                $listing = Listing::find($order->listing_id);
                if ($listing && $listing->status === 'matched') {
                    $listing->status = 'active';
                    $listing->save();
                }

                // Create audit log entry
                AuditLog::create([
                    'organization_id' => $order->organization_id,
                    'user_id'         => $order->passenger_id,
                    'action'          => 'order.auto_expired',
                    'resource_type'   => 'order',
                    'resource_id'     => $order->id,
                    'old_value'       => json_encode(['status' => 'pending_match']),
                    'new_value'       => json_encode(['status' => 'expired']),
                    'ip_address'      => '127.0.0.1',
                    'created_at'      => date('Y-m-d H:i:s'),
                ]);

                $expiredCount++;
            }

            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            Log::error('order:expire failed: ' . $e->getMessage());
            $output->writeln("<error>Error: {$e->getMessage()}</error>");
            return 1;
        }

        Log::info("order:expire completed: expired {$expiredCount} orders");
        $output->writeln("Expired {$expiredCount} orders.");

        return 0;
    }
}
