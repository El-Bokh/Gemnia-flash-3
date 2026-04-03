// ─── Payment Types ──────────────────────────────────────

export interface PaymentUser {
  id: number
  name: string
  email: string
  avatar: string | null
}

export interface PaymentUserDetail extends PaymentUser {
  status: string
}

export interface PaymentPlan {
  id: number
  name: string
  slug: string
}

export interface PaymentPlanDetail extends PaymentPlan {
  price_monthly: number
  price_yearly: number
}

export interface PaymentSubscription {
  id: number
  status: string
  plan: PaymentPlan
}

export interface PaymentSubscriptionDetail {
  id: number
  status: string
  billing_cycle: string
  starts_at: string | null
  ends_at: string | null
  plan: PaymentPlanDetail
}

export interface PaymentCoupon {
  id: number
  code: string
  discount_type: string
  discount_value: number
}

export interface PaymentCouponDetail extends PaymentCoupon {
  name: string
}

export interface PaymentInvoiceItem {
  id: number
  uuid: string
  invoice_number: string
  status: string
  total: number
  issued_at: string | null
  paid_at: string | null
}

export interface Payment {
  id: number
  uuid: string
  payment_gateway: string
  gateway_payment_id: string | null
  status: string
  amount: number
  discount_amount: number
  tax_amount: number
  net_amount: number
  currency: string
  payment_method: string | null
  description: string | null
  refunded_amount: number
  refunded_at: string | null
  paid_at: string | null
  created_at: string
  updated_at: string
  deleted_at: string | null
  user: PaymentUser
  subscription: PaymentSubscription | null
  coupon: PaymentCoupon | null
  invoices_count: number
}

export interface PaymentDetail {
  id: number
  uuid: string
  payment_gateway: string
  gateway_payment_id: string | null
  gateway_customer_id: string | null
  status: string
  amount: number
  discount_amount: number
  tax_amount: number
  net_amount: number
  currency: string
  payment_method: string | null
  description: string | null
  refunded_amount: number
  refunded_at: string | null
  refund_reason: string | null
  billing_name: string | null
  billing_email: string | null
  billing_address: string | null
  billing_city: string | null
  billing_state: string | null
  billing_zip: string | null
  billing_country: string | null
  gateway_response: Record<string, unknown> | null
  metadata: Record<string, unknown> | null
  paid_at: string | null
  created_at: string
  updated_at: string
  deleted_at: string | null
  user: PaymentUserDetail
  subscription: PaymentSubscriptionDetail | null
  coupon: PaymentCouponDetail | null
  invoices: PaymentInvoiceItem[]
}

export interface ListPaymentsParams {
  search?: string
  user_id?: number
  subscription_id?: number
  coupon_id?: number
  status?: 'pending' | 'completed' | 'failed' | 'refunded' | 'partially_refunded' | 'cancelled' | 'disputed'
  payment_gateway?: string
  payment_method?: string
  currency?: string
  amount_min?: number
  amount_max?: number
  date_from?: string
  date_to?: string
  paid_from?: string
  paid_to?: string
  has_refund?: boolean
  trashed?: 'with' | 'only'
  sort_by?: 'created_at' | 'amount' | 'net_amount' | 'status' | 'payment_gateway' | 'paid_at' | 'refunded_at' | 'updated_at'
  sort_dir?: 'asc' | 'desc'
  per_page?: number
  page?: number
}

export interface UpdatePaymentData {
  status?: string
  description?: string
  billing_name?: string
  billing_email?: string
  billing_address?: string
  billing_city?: string
  billing_state?: string
  billing_zip?: string
  billing_country?: string
  metadata?: Record<string, unknown>
}

export interface RefundPaymentData {
  amount?: number
  reason: string
}

// ─── Payment Aggregations ───────────────────────────────

export interface PaymentAggregationsSummary {
  total_payments: number
  total_revenue: number
  total_net_revenue: number
  total_discounts: number
  total_taxes: number
  total_refunded: number
  avg_payment_amount: number | null
  completed_count: number
  failed_count: number
  pending_count: number
  refunded_count: number
  disputed_count: number
}

export interface PaymentByGateway {
  payment_gateway: string
  count: number
  total: number
  net_total: number
}

export interface PaymentByCurrency {
  currency: string
  count: number
  total: number
}

export interface PaymentDailyTrend {
  date: string
  count: number
  total: number
  net_total: number
}

export interface PaymentTopUser {
  id: number
  name: string
  email: string
  payments_count: number
  total_spent: number
}

export interface PaymentStatusDistribution {
  status: string
  count: number
  total: number
}

export interface PaymentAggregations {
  summary: PaymentAggregationsSummary
  by_gateway: PaymentByGateway[]
  by_currency: PaymentByCurrency[]
  daily_trend: PaymentDailyTrend[]
  top_users: PaymentTopUser[]
  status_distribution: PaymentStatusDistribution[]
}

export interface PaymentAggregationsParams {
  date_from?: string
  date_to?: string
  trend_days?: number
}

// ─── Invoice Types ──────────────────────────────────────

export interface InvoiceUser {
  id: number
  name: string
  email: string
}

export interface InvoiceUserDetail extends InvoiceUser {
  avatar: string | null
}

export interface InvoicePayment {
  id: number
  uuid: string
  payment_gateway: string
  status: string
  amount: number
}

export interface InvoicePaymentDetail {
  id: number
  uuid: string
  payment_gateway: string
  gateway_payment_id: string | null
  status: string
  amount: number
  net_amount: number
  payment_method: string | null
  paid_at: string | null
}

export interface InvoiceSubscription {
  id: number
  status: string
  plan: { id: number; name: string }
}

export interface InvoiceSubscriptionDetail {
  id: number
  status: string
  billing_cycle: string
  starts_at: string | null
  ends_at: string | null
  plan: { id: number; name: string; slug: string }
}

export interface Invoice {
  id: number
  uuid: string
  invoice_number: string
  status: string
  subtotal: number
  discount_amount: number
  tax_amount: number
  total: number
  currency: string
  issued_at: string | null
  due_at: string | null
  paid_at: string | null
  created_at: string
  deleted_at: string | null
  user: InvoiceUser
  payment: InvoicePayment | null
  subscription: InvoiceSubscription | null
}

export interface InvoiceLineItem {
  description: string
  quantity: number
  unit_price: number
  total: number
}

export interface InvoiceDetail {
  id: number
  uuid: string
  invoice_number: string
  status: string
  subtotal: number
  discount_amount: number
  tax_amount: number
  total: number
  currency: string
  billing_name: string | null
  billing_email: string | null
  billing_address: string | null
  billing_city: string | null
  billing_state: string | null
  billing_zip: string | null
  billing_country: string | null
  line_items: InvoiceLineItem[] | null
  notes: string | null
  footer: string | null
  metadata: Record<string, unknown> | null
  issued_at: string | null
  due_at: string | null
  paid_at: string | null
  created_at: string
  updated_at: string
  deleted_at: string | null
  user: InvoiceUserDetail
  payment: InvoicePaymentDetail | null
  subscription: InvoiceSubscriptionDetail | null
}

export interface ListInvoicesParams {
  search?: string
  user_id?: number
  payment_id?: number
  subscription_id?: number
  status?: 'draft' | 'issued' | 'paid' | 'overdue' | 'cancelled' | 'refunded'
  currency?: string
  total_min?: number
  total_max?: number
  issued_from?: string
  issued_to?: string
  due_from?: string
  due_to?: string
  overdue?: boolean
  trashed?: 'with' | 'only'
  sort_by?: 'created_at' | 'invoice_number' | 'status' | 'total' | 'issued_at' | 'due_at' | 'paid_at' | 'updated_at'
  sort_dir?: 'asc' | 'desc'
  per_page?: number
  page?: number
}

export interface UpdateInvoiceData {
  status?: string
  billing_name?: string
  billing_email?: string
  billing_address?: string
  billing_city?: string
  billing_state?: string
  billing_zip?: string
  billing_country?: string
  notes?: string
  footer?: string
  due_at?: string
  metadata?: Record<string, unknown>
}

// ─── Invoice Aggregations ───────────────────────────────

export interface InvoiceAggregationsSummary {
  total_invoices: number
  total_paid: number
  paid_count: number
  draft_count: number
  issued_count: number
  overdue_count: number
  cancelled_count: number
  refunded_count: number
  avg_invoice_total: number | null
}

export interface InvoiceOverdue {
  count: number
  total: number
}

export interface InvoiceAggregations {
  summary: InvoiceAggregationsSummary
  overdue: InvoiceOverdue
}

export interface InvoiceAggregationsParams {
  date_from?: string
  date_to?: string
}

// ─── Invoice Download Data ──────────────────────────────

export interface InvoiceDownloadCompany {
  name: string
  email: string
}

export interface InvoiceDownloadData {
  invoice: Record<string, unknown>
  company: InvoiceDownloadCompany
}

// ─── Coupon Types ───────────────────────────────────────

export interface CouponApplicablePlan {
  id: number
  name: string
  slug: string
}

export interface Coupon {
  id: number
  code: string
  name: string
  discount_type: string
  discount_value: number
  currency: string
  is_active: boolean
  max_uses: number | null
  max_uses_per_user: number | null
  times_used: number
  usage_percentage: number | null
  starts_at: string | null
  expires_at: string | null
  is_expired: boolean
  applicable_plan: CouponApplicablePlan | null
  created_at: string
  deleted_at: string | null
}

export interface CouponDetail extends Coupon {
  description: string | null
  min_order_amount: number | null
  metadata: Record<string, unknown> | null
  updated_at: string
  payments_count: number
  payments_total?: number
  discount_given_total?: number
}

export interface ListCouponsParams {
  search?: string
  discount_type?: 'percentage' | 'fixed_amount' | 'credits'
  is_active?: boolean
  applicable_plan_id?: number
  expired?: boolean
  has_uses_remaining?: boolean
  trashed?: 'with' | 'only'
  sort_by?: 'created_at' | 'code' | 'name' | 'discount_type' | 'discount_value' | 'times_used' | 'expires_at' | 'updated_at'
  sort_dir?: 'asc' | 'desc'
  per_page?: number
  page?: number
}

export interface StoreCouponData {
  code: string
  name: string
  description?: string
  discount_type: 'percentage' | 'fixed_amount' | 'credits'
  discount_value: number
  currency?: string
  max_uses?: number
  max_uses_per_user?: number
  min_order_amount?: number
  applicable_plan_id?: number
  is_active?: boolean
  starts_at?: string
  expires_at?: string
  metadata?: Record<string, unknown>
}

export interface UpdateCouponData {
  code?: string
  name?: string
  description?: string
  discount_type?: 'percentage' | 'fixed_amount' | 'credits'
  discount_value?: number
  currency?: string
  max_uses?: number
  max_uses_per_user?: number
  min_order_amount?: number
  applicable_plan_id?: number
  is_active?: boolean
  starts_at?: string
  expires_at?: string
  metadata?: Record<string, unknown>
}

export interface ValidateCouponData {
  code: string
  user_id?: number
  plan_id?: number
  amount?: number
}

export interface ValidateCouponDiscount {
  original_amount: number
  discount_amount: number
  final_amount: number
  type: string
}

export interface ValidateCouponCoupon {
  id: number
  code: string
  name: string
  discount_type: string
  discount_value: number
  currency: string
}

export interface ValidateCouponResponse {
  valid: boolean
  reason?: string
  coupon?: ValidateCouponCoupon
  discount?: ValidateCouponDiscount | null
}

// ─── Coupon Usage Stats ─────────────────────────────────

export interface CouponUsageUserBreakdown {
  id: number
  name: string
  email: string
  uses: number
  total_discount: number
  total_amount: number
}

export interface CouponUsageSummary {
  total_uses: number
  total_discount_given: number
  total_revenue: number
  unique_users: number
  avg_discount: number | null
}

export interface CouponUsageDailyTrend {
  date: string
  uses: number
  discount_total: number
}

export interface CouponUsageStats {
  summary: CouponUsageSummary
  user_breakdown: CouponUsageUserBreakdown[]
  daily_trend: CouponUsageDailyTrend[]
}

// ─── Coupon Aggregations ────────────────────────────────

export interface CouponAggregationsSummary {
  total_coupons: number
  active_count: number
  inactive_count: number
  total_uses: number
}

export interface CouponByType {
  discount_type: string
  count: number
  total_uses: number
}

export interface CouponTopCoupon {
  id: number
  code: string
  name: string
  discount_type: string
  discount_value: number
  times_used: number
}

export interface CouponAggregations {
  summary: CouponAggregationsSummary
  expired_count: number
  by_type: CouponByType[]
  top_coupons: CouponTopCoupon[]
}
