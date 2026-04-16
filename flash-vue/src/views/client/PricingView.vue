<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import Button from 'primevue/button'
import Tag from 'primevue/tag'
import SelectButton from 'primevue/selectbutton'
import { getPlans, upgradeSubscription } from '@/services/subscriptionService'
import { useAuthStore } from '@/stores/auth'
import { useSeo } from '@/composables/useSeo'

const { t } = useI18n()
const router = useRouter()
const auth = useAuthStore()

useSeo({
  title: computed(() => t('seo.pricingTitle')),
  description: computed(() => t('seo.pricingDescription')),
  path: '/pricing',
  jsonLd: {
    '@context': 'https://schema.org',
    '@type': 'WebPage',
    name: 'Plans & Pricing - Klek AI',
    url: 'https://klek.studio/pricing',
    description: 'Choose the perfect Klek AI plan for your creative needs.',
    isPartOf: {
      '@type': 'WebSite',
      name: 'Klek AI',
      url: 'https://klek.studio',
    },
  },
})

const loading = ref(true)
const upgrading = ref<number | null>(null)
const billingCycle = ref<'monthly' | 'yearly'>('monthly')
const rawPlans = ref<any[]>([])

const cycleOptions = computed(() => [
  { label: t('client.monthly'), value: 'monthly' },
  { label: t('client.yearly'), value: 'yearly' },
])

const toneMap: Record<string, string> = {
  free: '#64748b',
  starter: '#0ea5e9',
  pro: '#8b5cf6',
  professional: '#8b5cf6',
  enterprise: '#f59e0b',
}

const plans = computed(() =>
  (rawPlans.value ?? []).map((p) => ({
    id: p.id,
    name: p.name,
    slug: p.slug,
    price: billingCycle.value === 'yearly' ? p.price_yearly : p.price_monthly,
    credits: billingCycle.value === 'yearly' ? p.credits_yearly : p.credits_monthly,
    currency: p.currency ?? 'USD',
    description: p.description ?? '',
    is_free: p.is_free,
    is_featured: p.is_featured,
    features: (p.features ?? []).map((f: any) => f.name),
    tone: toneMap[p.slug] ?? '#64748b',
    isCurrent: auth.isAuthenticated && auth.quota?.plan_slug === p.slug,
  })),
)

onMounted(async () => {
  try {
    const res = await getPlans()
    rawPlans.value = res.data ?? []
  } catch {
    rawPlans.value = []
  } finally {
    loading.value = false
  }
})

async function selectPlan(plan: any) {
  if (!auth.isAuthenticated) {
    router.push({ name: 'login' })
    return
  }
  if (plan.isCurrent || plan.is_free) return

  upgrading.value = plan.id
  try {
    await upgradeSubscription(plan.id, billingCycle.value)
    await auth.refreshQuota()
  } catch {
    // error handled by global interceptor
  } finally {
    upgrading.value = null
  }
}
</script>

<template>
  <div class="pricing-page">
    <div class="pricing-header">
      <h1 class="pricing-title">{{ t('client.pricingTitle') }}</h1>
      <p class="pricing-sub">{{ t('client.pricingSub') }}</p>

      <div class="cycle-toggle">
        <SelectButton
          v-model="billingCycle"
          :options="cycleOptions"
          optionLabel="label"
          optionValue="value"
          :allowEmpty="false"
        />
        <span v-if="billingCycle === 'yearly'" class="save-badge">{{ t('client.save20') }}</span>
      </div>
    </div>

    <div v-if="loading" class="loading-state">
      <i class="pi pi-spin pi-spinner" style="font-size: 2rem; color: var(--text-muted)" />
    </div>

    <div v-else class="plans-grid">
      <article
        v-for="plan in plans"
        :key="plan.slug"
        class="plan-card"
        :class="{ popular: plan.is_featured, current: plan.isCurrent }"
      >
        <div v-if="plan.is_featured" class="popular-badge">
          <Tag :value="t('client.mostPopular')" severity="contrast" class="pop-tag" />
        </div>
        <div v-if="plan.isCurrent" class="current-badge">
          <Tag :value="t('client.currentPlan')" severity="success" class="pop-tag" />
        </div>

        <div class="plan-head">
          <h3 class="plan-name" :style="{ color: plan.tone }">{{ plan.name }}</h3>
          <p class="plan-desc">{{ plan.description }}</p>
        </div>

        <div class="plan-price">
          <span class="price-amount">${{ plan.price }}</span>
          <span class="price-period">/ {{ billingCycle === 'yearly' ? t('client.perYear') : t('client.perMonth') }}</span>
        </div>

        <div class="plan-credits">
          <span class="credits-amount">{{ plan.credits?.toLocaleString() }}</span>
          <span class="credits-label">{{ t('client.credits') }}</span>
        </div>

        <ul class="plan-features">
          <li v-for="(feature, fi) in plan.features" :key="fi" class="feature-item">
            <i class="pi pi-check-circle feature-check" :style="{ color: plan.tone }" />
            <span>{{ feature }}</span>
          </li>
        </ul>

        <Button
          :label="plan.isCurrent ? t('client.currentPlan') : plan.is_free ? t('client.startFree') : t('client.subscribe')"
          :outlined="!plan.is_featured"
          :severity="plan.is_featured ? undefined : 'secondary'"
          :disabled="plan.isCurrent || upgrading !== null"
          :loading="upgrading === plan.id"
          size="small"
          class="plan-cta"
          @click="selectPlan(plan)"
        />
      </article>
    </div>

    <div class="pricing-footer">
      <p>{{ t('client.pricingFooter') }}</p>
    </div>
  </div>
</template>

<style scoped>
.pricing-page {
  --pricing-grid-line: rgba(99, 102, 241, 0.08);
  --pricing-grid-glow: rgba(99, 102, 241, 0.14);
  --pricing-grid-glow-alt: rgba(16, 185, 129, 0.1);

  position: relative;
  isolation: isolate;
  width: 100%;
  max-width: none;
  margin: 0;
  padding: 34px 26px 60px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 32px;
  min-width: 0;
  overflow: hidden;
  border: 1px solid rgba(255, 255, 255, 0.05);
  border-radius: 30px;
  background:
    linear-gradient(180deg, rgba(255, 255, 255, 0.02), rgba(255, 255, 255, 0)),
    rgba(8, 10, 18, 0.82);
  box-shadow: 0 28px 70px rgba(0, 0, 0, 0.24);
}

:global(.dark) .pricing-page {
  --pricing-grid-line: rgba(147, 197, 253, 0.08);
  --pricing-grid-glow: rgba(129, 140, 248, 0.16);
  --pricing-grid-glow-alt: rgba(56, 189, 248, 0.12);
}

.pricing-page::before,
.pricing-page::after {
  content: '';
  position: absolute;
  inset: 0;
  pointer-events: none;
}

.pricing-page::before {
  background:
    linear-gradient(var(--pricing-grid-line) 1px, transparent 1px),
    linear-gradient(90deg, var(--pricing-grid-line) 1px, transparent 1px);
  background-size: 42px 42px;
  mask-image: linear-gradient(180deg, rgba(0, 0, 0, 0.86), rgba(0, 0, 0, 0.24));
  z-index: -2;
}

.pricing-page::after {
  background:
    radial-gradient(circle at 14% 14%, var(--pricing-grid-glow), transparent 24%),
    radial-gradient(circle at 84% 20%, var(--pricing-grid-glow-alt), transparent 22%),
    radial-gradient(circle at 50% 88%, rgba(99, 102, 241, 0.08), transparent 24%);
  z-index: -1;
}

.pricing-page > * {
  position: relative;
  z-index: 1;
}

.pricing-header {
  text-align: center;
  max-width: 640px;
}

.pricing-title {
  font-size: 1.6rem;
  font-weight: 700;
  color: var(--text-primary);
  margin: 0 0 8px;
}

.pricing-sub {
  font-size: 0.88rem;
  color: var(--text-muted);
  margin: 0;
}

.cycle-toggle {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  margin-top: 16px;
}

.save-badge {
  font-size: 0.72rem;
  font-weight: 600;
  color: #10b981;
  background: rgba(16, 185, 129, 0.1);
  padding: 2px 8px;
  border-radius: 8px;
}

.loading-state {
  display: flex;
  justify-content: center;
  padding: 60px 0;
}

/* Grid */
.plans-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 18px;
  width: 100%;
  max-width: none;
}

@media (min-width: 640px) {
  .plans-grid { grid-template-columns: repeat(2, 1fr); }
}

@media (min-width: 1024px) {
  .plans-grid { grid-template-columns: repeat(4, 1fr); }
}

/* Card */
.plan-card {
  position: relative;
  display: flex;
  flex-direction: column;
  border: 1px solid color-mix(in srgb, var(--card-border) 84%, rgba(255, 255, 255, 0.08));
  border-radius: 18px;
  background: color-mix(in srgb, var(--card-bg) 92%, transparent);
  padding: 24px 20px;
  gap: 16px;
  transition: border-color 0.2s, box-shadow 0.2s, transform 0.2s;
  backdrop-filter: blur(14px);
}

.plan-card:hover {
  border-color: color-mix(in srgb, var(--active-color) 40%, transparent);
  transform: translateY(-2px);
}

.plan-card.popular {
  border-color: var(--active-color);
  box-shadow: 0 0 0 1px var(--active-color), 0 8px 24px rgba(99, 102, 241, 0.1);
}

.plan-card.current {
  border-color: #10b981;
  box-shadow: 0 0 0 1px #10b981, 0 8px 24px rgba(16, 185, 129, 0.1);
}

.popular-badge {
  position: absolute;
  top: -10px;
  inset-inline-start: 50%;
  transform: translateX(-50%);
}

.current-badge {
  position: absolute;
  top: -10px;
  inset-inline-end: 12px;
}

[dir='rtl'] .popular-badge {
  transform: translateX(50%);
}

.pop-tag {
  font-size: 0.62rem !important;
  padding: 3px 10px !important;
}

.plan-head {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.plan-name {
  margin: 0;
  font-size: 1rem;
  font-weight: 700;
}

.plan-desc {
  margin: 0;
  font-size: 0.74rem;
  color: var(--text-muted);
  line-height: 1.4;
}

.plan-price {
  display: flex;
  align-items: baseline;
  gap: 4px;
}

.price-amount {
  font-size: 2rem;
  font-weight: 800;
  color: var(--text-primary);
  line-height: 1;
}

.price-period {
  font-size: 0.78rem;
  color: var(--text-muted);
}

.plan-credits {
  display: flex;
  align-items: baseline;
  gap: 4px;
}

.credits-amount {
  font-size: 1.1rem;
  font-weight: 700;
  color: var(--text-primary);
}

.credits-label {
  font-size: 0.74rem;
  color: var(--text-muted);
}

.plan-features {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 10px;
  flex: 1;
}

.feature-item {
  display: flex;
  align-items: flex-start;
  gap: 8px;
  font-size: 0.78rem;
  color: var(--text-secondary);
  line-height: 1.4;
}

.feature-check {
  font-size: 0.82rem;
  margin-top: 1px;
  flex-shrink: 0;
}

.plan-cta {
  width: 100%;
  border-radius: 10px !important;
  font-weight: 600 !important;
  font-size: 0.82rem !important;
}

.pricing-footer {
  text-align: center;
}

.pricing-footer p {
  font-size: 0.72rem;
  color: var(--text-muted);
  margin: 0;
}

@media (max-width: 640px) {
  .pricing-page {
    padding: 20px 12px 40px;
    border-radius: 22px;
  }
}
</style>
