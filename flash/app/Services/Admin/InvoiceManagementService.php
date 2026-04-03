<?php

namespace App\Services\Admin;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\UsageLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class InvoiceManagementService
{
    // ──────────────────────────────────────────────
    //  LIST (paginated with filters)
    // ──────────────────────────────────────────────

    public function list(array $filters = []): LengthAwarePaginator
    {
        $query = Invoice::query()
            ->with([
                'user:id,name,email,avatar',
                'payment:id,uuid,status,amount,payment_gateway',
                'subscription:id,plan_id,status,billing_cycle',
                'subscription.plan:id,name,slug',
            ]);

        // ── Trashed ──
        if (! empty($filters['trashed'])) {
            $filters['trashed'] === 'only'
                ? $query->onlyTrashed()
                : $query->withTrashed();
        }

        // ── Search (invoice_number, uuid, billing_name, billing_email, user) ──
        if (! empty($filters['search'])) {
            $term = $filters['search'];
            $query->where(function (Builder $q) use ($term) {
                $q->where('invoice_number', 'LIKE', "%{$term}%")
                  ->orWhere('uuid', 'LIKE', "%{$term}%")
                  ->orWhere('billing_name', 'LIKE', "%{$term}%")
                  ->orWhere('billing_email', 'LIKE', "%{$term}%")
                  ->orWhereHas('user', function (Builder $uq) use ($term) {
                      $uq->where('name', 'LIKE', "%{$term}%")
                         ->orWhere('email', 'LIKE', "%{$term}%");
                  });
            });
        }

        // ── Exact filters ──
        if (! empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }
        if (! empty($filters['payment_id'])) {
            $query->where('payment_id', $filters['payment_id']);
        }
        if (! empty($filters['subscription_id'])) {
            $query->where('subscription_id', $filters['subscription_id']);
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['currency'])) {
            $query->where('currency', $filters['currency']);
        }

        // ── Total range ──
        if (isset($filters['total_min'])) {
            $query->where('total', '>=', $filters['total_min']);
        }
        if (isset($filters['total_max'])) {
            $query->where('total', '<=', $filters['total_max']);
        }

        // ── Date ranges ──
        if (! empty($filters['issued_from'])) {
            $query->whereDate('issued_at', '>=', $filters['issued_from']);
        }
        if (! empty($filters['issued_to'])) {
            $query->whereDate('issued_at', '<=', $filters['issued_to']);
        }
        if (! empty($filters['due_from'])) {
            $query->whereDate('due_at', '>=', $filters['due_from']);
        }
        if (! empty($filters['due_to'])) {
            $query->whereDate('due_at', '<=', $filters['due_to']);
        }

        // ── Overdue filter ──
        if (isset($filters['overdue']) && $filters['overdue']) {
            $query->where('status', '!=', 'paid')
                  ->where('status', '!=', 'cancelled')
                  ->whereNotNull('due_at')
                  ->where('due_at', '<', now());
        }

        // ── Sort ──
        $sortBy  = $filters['sort_by'] ?? 'created_at';
        $sortDir = $filters['sort_dir'] ?? 'desc';
        $query->orderBy($sortBy, $sortDir);

        return $query->paginate($filters['per_page'] ?? 15);
    }

    // ──────────────────────────────────────────────
    //  DETAIL
    // ──────────────────────────────────────────────

    public function getDetail(int $id): Invoice
    {
        return Invoice::withTrashed()
            ->with([
                'user:id,name,email,avatar,phone',
                'payment:id,uuid,payment_gateway,gateway_payment_id,status,amount,net_amount,payment_method,paid_at',
                'subscription:id,plan_id,status,billing_cycle,starts_at,ends_at',
                'subscription.plan:id,name,slug',
            ])
            ->findOrFail($id);
    }

    // ──────────────────────────────────────────────
    //  UPDATE
    // ──────────────────────────────────────────────

    public function update(Invoice $invoice, array $data, int $adminId): Invoice
    {
        $oldStatus = $invoice->status;

        // Auto-set paid_at when status changes to paid
        if (isset($data['status']) && $data['status'] === 'paid' && $invoice->status !== 'paid') {
            $data['paid_at'] = now();
        }

        $invoice->update($data);

        if (isset($data['status']) && $data['status'] !== $oldStatus) {
            $this->logAction($invoice, $adminId, 'invoice_status_changed', [
                'old_status' => $oldStatus,
                'new_status' => $data['status'],
            ]);
        }

        return $invoice->fresh([
            'user:id,name,email,avatar',
            'payment:id,uuid,status,amount',
            'subscription:id,plan_id,status',
        ]);
    }

    // ──────────────────────────────────────────────
    //  GENERATE INVOICE FROM PAYMENT
    // ──────────────────────────────────────────────

    public function generateFromPayment(Payment $payment, int $adminId): Invoice
    {
        if ($payment->status !== 'completed') {
            abort(422, 'Can only generate invoices for completed payments.');
        }

        // Check if an invoice already exists for this payment
        $existing = Invoice::where('payment_id', $payment->id)->first();
        if ($existing) {
            abort(422, 'An invoice already exists for this payment.');
        }

        $invoiceNumber = $this->generateInvoiceNumber();

        $lineItems = [
            [
                'description' => $payment->description ?? 'Subscription Payment',
                'quantity'    => 1,
                'unit_price'  => (float) $payment->amount,
                'total'       => (float) $payment->amount,
            ],
        ];

        if ((float) $payment->discount_amount > 0) {
            $lineItems[] = [
                'description' => 'Discount',
                'quantity'    => 1,
                'unit_price'  => -1 * (float) $payment->discount_amount,
                'total'       => -1 * (float) $payment->discount_amount,
            ];
        }

        $invoice = Invoice::create([
            'invoice_number'  => $invoiceNumber,
            'user_id'         => $payment->user_id,
            'payment_id'      => $payment->id,
            'subscription_id' => $payment->subscription_id,
            'status'          => 'paid',
            'subtotal'        => $payment->amount,
            'discount_amount' => $payment->discount_amount ?? 0,
            'tax_amount'      => $payment->tax_amount ?? 0,
            'total'           => $payment->net_amount ?? $payment->amount,
            'currency'        => $payment->currency ?? 'USD',
            'billing_name'    => $payment->billing_name,
            'billing_email'   => $payment->billing_email,
            'billing_address' => $payment->billing_address,
            'billing_city'    => $payment->billing_city,
            'billing_state'   => $payment->billing_state,
            'billing_zip'     => $payment->billing_zip,
            'billing_country' => $payment->billing_country,
            'line_items'      => $lineItems,
            'issued_at'       => now(),
            'paid_at'         => $payment->paid_at ?? now(),
        ]);

        $this->logAction($invoice, $adminId, 'invoice_generated', [
            'payment_id' => $payment->id,
        ]);

        return $invoice->fresh([
            'user:id,name,email,avatar',
            'payment:id,uuid,status,amount',
        ]);
    }

    // ──────────────────────────────────────────────
    //  DELETE / FORCE-DELETE / RESTORE
    // ──────────────────────────────────────────────

    public function delete(Invoice $invoice, int $adminId): bool
    {
        if ($invoice->status === 'paid') {
            abort(422, 'Cannot delete a paid invoice. Cancel or refund it first.');
        }

        $this->logAction($invoice, $adminId, 'invoice_deleted');
        return $invoice->delete();
    }

    public function forceDelete(int $id, int $adminId): bool
    {
        $invoice = Invoice::withTrashed()->findOrFail($id);
        $this->logAction($invoice, $adminId, 'invoice_force_deleted');
        return $invoice->forceDelete();
    }

    public function restore(int $id, int $adminId): Invoice
    {
        $invoice = Invoice::onlyTrashed()->findOrFail($id);
        $invoice->restore();
        $this->logAction($invoice, $adminId, 'invoice_restored');

        return $invoice->fresh([
            'user:id,name,email,avatar',
            'payment:id,uuid,status,amount',
        ]);
    }

    // ──────────────────────────────────────────────
    //  GET INVOICE DATA (for PDF rendering)
    // ──────────────────────────────────────────────

    public function getInvoiceData(int $id): array
    {
        $invoice = Invoice::withTrashed()
            ->with([
                'user:id,name,email,phone',
                'payment:id,uuid,payment_gateway,payment_method,paid_at',
                'subscription:id,plan_id,billing_cycle',
                'subscription.plan:id,name',
            ])
            ->findOrFail($id);

        return [
            'invoice'      => $invoice->toArray(),
            'company'      => [
                'name'    => config('app.name', 'Flash'),
                'email'   => config('mail.from.address', 'support@flash.test'),
            ],
        ];
    }

    // ──────────────────────────────────────────────
    //  AGGREGATIONS
    // ──────────────────────────────────────────────

    public function getAggregations(array $filters = []): array
    {
        $baseQuery = Invoice::query();

        if (! empty($filters['date_from'])) {
            $baseQuery->whereDate('created_at', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $baseQuery->whereDate('created_at', '<=', $filters['date_to']);
        }

        $stats = (clone $baseQuery)->select([
            DB::raw('COUNT(*) as total_invoices'),
            DB::raw("SUM(CASE WHEN status = 'paid' THEN total ELSE 0 END) as total_paid"),
            DB::raw("SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as paid_count"),
            DB::raw("SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft_count"),
            DB::raw("SUM(CASE WHEN status = 'issued' THEN 1 ELSE 0 END) as issued_count"),
            DB::raw("SUM(CASE WHEN status = 'overdue' THEN 1 ELSE 0 END) as overdue_count"),
            DB::raw("SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_count"),
            DB::raw("SUM(CASE WHEN status = 'refunded' THEN 1 ELSE 0 END) as refunded_count"),
            DB::raw("AVG(CASE WHEN status = 'paid' THEN total ELSE NULL END) as avg_invoice_total"),
        ])->first();

        $overdue = Invoice::query()
            ->where('status', '!=', 'paid')
            ->where('status', '!=', 'cancelled')
            ->whereNotNull('due_at')
            ->where('due_at', '<', now())
            ->select([
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(total) as total'),
            ])
            ->first();

        return [
            'summary' => $stats,
            'overdue' => $overdue,
        ];
    }

    // ──────────────────────────────────────────────
    //  PRIVATE HELPERS
    // ──────────────────────────────────────────────

    private function generateInvoiceNumber(): string
    {
        $prefix = 'INV-' . date('Y');
        $last = Invoice::withTrashed()
            ->where('invoice_number', 'LIKE', "{$prefix}%")
            ->orderByDesc('invoice_number')
            ->value('invoice_number');

        if ($last) {
            $seq = (int) substr($last, strlen($prefix) + 1) + 1;
        } else {
            $seq = 1;
        }

        return $prefix . '-' . str_pad($seq, 6, '0', STR_PAD_LEFT);
    }

    private function logAction(Invoice $invoice, int $adminId, string $action, array $extra = []): void
    {
        UsageLog::create([
            'user_id'     => $invoice->user_id,
            'action'      => $action,
            'description' => "Admin #{$adminId} performed [{$action}] on invoice #{$invoice->id}",
            'metadata'    => array_merge([
                'invoice_id'     => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'admin_id'       => $adminId,
            ], $extra),
        ]);
    }
}
