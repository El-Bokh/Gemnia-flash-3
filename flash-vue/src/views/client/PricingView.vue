<script setup lang="ts">
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import Button from 'primevue/button'
import Tag from 'primevue/tag'

const { t } = useI18n()
const router = useRouter()

const plans = computed(() => [
  {
    name: t('client.planFree'),
    slug: 'free',
    price: 0,
    period: t('client.perMonth'),
    description: t('client.planFreeDesc'),
    features: [
      t('client.freeFeature1'),
      t('client.freeFeature2'),
      t('client.freeFeature3'),
      t('client.freeFeature4'),
    ],
    cta: t('client.startFree'),
    popular: false,
    tone: '#64748b',
  },
  {
    name: t('client.planStarter'),
    slug: 'starter',
    price: 9,
    period: t('client.perMonth'),
    description: t('client.planStarterDesc'),
    features: [
      t('client.starterFeature1'),
      t('client.starterFeature2'),
      t('client.starterFeature3'),
      t('client.starterFeature4'),
      t('client.starterFeature5'),
    ],
    cta: t('client.subscribe'),
    popular: false,
    tone: '#0ea5e9',
  },
  {
    name: t('client.planPro'),
    slug: 'pro',
    price: 29,
    period: t('client.perMonth'),
    description: t('client.planProDesc'),
    features: [
      t('client.proFeature1'),
      t('client.proFeature2'),
      t('client.proFeature3'),
      t('client.proFeature4'),
      t('client.proFeature5'),
      t('client.proFeature6'),
    ],
    cta: t('client.subscribe'),
    popular: true,
    tone: '#8b5cf6',
  },
  {
    name: t('client.planEnterprise'),
    slug: 'enterprise',
    price: 99,
    period: t('client.perMonth'),
    description: t('client.planEnterpriseDesc'),
    features: [
      t('client.enterpriseFeature1'),
      t('client.enterpriseFeature2'),
      t('client.enterpriseFeature3'),
      t('client.enterpriseFeature4'),
      t('client.enterpriseFeature5'),
      t('client.enterpriseFeature6'),
    ],
    cta: t('client.contactSales'),
    popular: false,
    tone: '#f59e0b',
  },
])

function selectPlan(slug: string) {
  router.push({ name: 'login' })
}
</script>

<template>
  <div class="pricing-page">
    <div class="pricing-header">
      <h1 class="pricing-title">{{ t('client.pricingTitle') }}</h1>
      <p class="pricing-sub">{{ t('client.pricingSub') }}</p>
    </div>

    <div class="plans-grid">
      <article
        v-for="plan in plans"
        :key="plan.slug"
        class="plan-card"
        :class="{ popular: plan.popular }"
      >
        <div v-if="plan.popular" class="popular-badge">
          <Tag :value="t('client.mostPopular')" severity="contrast" class="pop-tag" />
        </div>

        <div class="plan-head">
          <h3 class="plan-name" :style="{ color: plan.tone }">{{ plan.name }}</h3>
          <p class="plan-desc">{{ plan.description }}</p>
        </div>

        <div class="plan-price">
          <span class="price-amount">${{ plan.price }}</span>
          <span class="price-period">/ {{ plan.period }}</span>
        </div>

        <ul class="plan-features">
          <li v-for="(feature, fi) in plan.features" :key="fi" class="feature-item">
            <i class="pi pi-check-circle feature-check" :style="{ color: plan.tone }" />
            <span>{{ feature }}</span>
          </li>
        </ul>

        <Button
          :label="plan.cta"
          :outlined="!plan.popular"
          :severity="plan.popular ? undefined : 'secondary'"
          size="small"
          class="plan-cta"
          @click="selectPlan(plan.slug)"
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
  flex: 1;
  padding: 40px 20px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 32px;
  min-width: 0;
  overflow: hidden;
}

.pricing-header {
  text-align: center;
  max-width: 560px;
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

/* Grid */
.plans-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 16px;
  width: 100%;
  max-width: 1000px;
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
  border: 1px solid var(--card-border);
  border-radius: 14px;
  background: var(--card-bg);
  padding: 24px 20px;
  gap: 16px;
  transition: border-color 0.2s, box-shadow 0.2s;
}

.plan-card:hover {
  border-color: color-mix(in srgb, var(--active-color) 40%, transparent);
}

.plan-card.popular {
  border-color: var(--active-color);
  box-shadow: 0 0 0 1px var(--active-color), 0 8px 24px rgba(99, 102, 241, 0.1);
}

.popular-badge {
  position: absolute;
  top: -10px;
  inset-inline-start: 50%;
  transform: translateX(-50%);
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
</style>
