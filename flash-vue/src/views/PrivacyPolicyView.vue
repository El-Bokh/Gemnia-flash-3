<script setup lang="ts">
import { computed } from 'vue'
import { useSeo } from '@/composables/useSeo'

useSeo({
  title: computed(() => 'Privacy Policy & Terms – Klek AI'),
  description: computed(() => 'Read the Klek AI terms and conditions and privacy policy.'),
  path: '/privacy-policy',
})
</script>

<template>
  <div class="legal-page">
    <div class="legal-bg">
      <div class="bg-gradient" />
    </div>

    <div class="legal-container">
      <!-- Back link -->
      <router-link to="/" class="back-link">
        <i class="pi pi-arrow-left" /> Back to Home
      </router-link>

      <!-- Terms and Conditions -->
      <section class="legal-card">
        <h1 class="legal-title">Terms and Conditions</h1>

        <div class="legal-section">
          <h2>1. Introduction</h2>
          <p>
            Welcome to Klek.studio. By accessing and using our website and services, you agree to
            comply with and be bound by the following terms and conditions.
          </p>
        </div>

        <div class="legal-section">
          <h2>2. Services Provided</h2>
          <p>
            Klek studio provides AI-powered image generation services. We grant users access to
            generate digital art based on specific inputs and prompts.
          </p>
        </div>

        <div class="legal-section">
          <h2>3. Payments and Subscriptions</h2>
          <p>
            All payments are processed securely through Lemon Squeezy. By subscribing or purchasing
            a credit pack, you agree to their terms of service.
          </p>
          <ul>
            <li>
              <strong>Refunds:</strong> Due to the nature of digital credits and computational
              costs, refunds are generally not provided once credits have been used.
            </li>
          </ul>
        </div>

        <div class="legal-section">
          <h2>4. Intellectual Property</h2>
          <ul>
            <li>
              <strong>User Content:</strong> You retain ownership of the prompts you provide.
            </li>
            <li>
              <strong>Generated Content:</strong> Klek grants you a license to use the images
              generated for personal or commercial use (depending on your plan). However, we reserve
              the right to display generated images for promotional purposes unless otherwise agreed.
            </li>
          </ul>
        </div>

        <div class="legal-section">
          <h2>5. Prohibited Use</h2>
          <p>
            Users are prohibited from using the service to generate harmful, illegal, or infringing
            content. We reserve the right to terminate accounts that violate these guidelines.
          </p>
        </div>

        <div class="legal-section">
          <h2>6. Limitation of Liability</h2>
          <p>
            Klek is provided "as is." We are not responsible for any indirect damages arising from
            your use of the service or the inability to access it.
          </p>
        </div>

        <div class="legal-section">
          <h2>7. Governing Law</h2>
          <p>These terms are governed by the laws of Kuwait.</p>
        </div>
      </section>

      <!-- Privacy Policy -->
      <section class="legal-card">
        <h1 class="legal-title">Privacy Policy</h1>

        <div class="legal-section">
          <h2>1. Information We Collect</h2>
          <ul>
            <li>
              <strong>Account Information:</strong> When you register, we collect your email address
              and name.
            </li>
            <li>
              <strong>Payment Data:</strong> Payments are processed via Lemon Squeezy. We do not
              store your credit card details on our servers.
            </li>
            <li>
              <strong>User Content:</strong> We store the prompts you enter and the images generated
              by our AI to provide the service and improve user experience.
            </li>
            <li>
              <strong>Usage Data:</strong> We collect information on how you interact with Klek (IP
              address, browser type, pages visited).
            </li>
          </ul>
        </div>

        <div class="legal-section">
          <h2>2. How We Use Your Information</h2>
          <ul>
            <li>To provide, maintain, and improve the Klek AI service.</li>
            <li>
              To process transactions and send related information (receipts, confirmations).
            </li>
            <li>To communicate with you regarding updates or support.</li>
          </ul>
        </div>

        <div class="legal-section">
          <h2>3. Data Sharing and Disclosure</h2>
          <p>
            We do not sell your personal data. We only share information with third-party service
            providers (like Lemon Squeezy for payments or cloud hosting services) necessary to
            operate our platform.
          </p>
        </div>

        <div class="legal-section">
          <h2>4. Data Security</h2>
          <p>
            We implement industry-standard security measures to protect your data. However, no
            method of transmission over the internet is 100% secure.
          </p>
        </div>

        <div class="legal-section">
          <h2>5. Cookies</h2>
          <p>
            We use cookies to enhance your experience and remember your preferences. You can disable
            cookies through your browser settings.
          </p>
        </div>

        <div class="legal-section">
          <h2>6. Your Rights</h2>
          <p>
            You have the right to access, update, or delete your personal information at any time by
            contacting us.
          </p>
        </div>

        <div class="legal-section">
          <h2>7. Contact Us</h2>
          <p>
            If you have any questions about this Privacy Policy, please contact us at:
            <a href="mailto:klek.studio@gmail.com" class="email-link">klek.studio@gmail.com</a>
          </p>
        </div>
      </section>
    </div>
  </div>
</template>

<style scoped>
.legal-page {
  --lp-bg: #07070e;
  --lp-surface: #0b0b16;
  --lp-card: rgba(14, 14, 28, 0.65);
  --lp-card-border: rgba(255, 255, 255, 0.06);
  --lp-text: #dddde4;
  --lp-text-dim: #8b8b9e;
  --lp-accent: #9580ff;

  min-height: 100vh;
  background: var(--lp-bg);
  color: var(--lp-text);
  position: relative;
  overflow: hidden;
}

.legal-bg {
  position: fixed;
  inset: 0;
  pointer-events: none;
  z-index: 0;
}
.bg-gradient {
  position: absolute;
  width: 600px;
  height: 600px;
  top: -200px;
  left: 50%;
  transform: translateX(-50%);
  background: radial-gradient(circle, rgba(149, 128, 255, 0.10) 0%, transparent 70%);
  filter: blur(80px);
}

.legal-container {
  position: relative;
  z-index: 1;
  max-width: 800px;
  margin: 0 auto;
  padding: 48px 24px 80px;
}

.back-link {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  color: var(--lp-text-dim);
  text-decoration: none;
  font-size: 0.85rem;
  margin-bottom: 32px;
  transition: color 0.2s;
}
.back-link:hover {
  color: var(--lp-accent);
}

.legal-card {
  background: var(--lp-card);
  border: 1px solid var(--lp-card-border);
  border-radius: 16px;
  padding: 40px 36px;
  backdrop-filter: blur(12px);
  margin-bottom: 32px;
}

.legal-title {
  font-size: 1.6rem;
  font-weight: 800;
  background: linear-gradient(135deg, #fff, var(--lp-accent));
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
  margin: 0 0 28px;
}

.legal-section {
  margin-bottom: 24px;
}
.legal-section:last-child {
  margin-bottom: 0;
}

.legal-section h2 {
  font-size: 1.05rem;
  font-weight: 700;
  color: #fff;
  margin: 0 0 10px;
}

.legal-section p {
  font-size: 0.9rem;
  line-height: 1.7;
  color: var(--lp-text-dim);
  margin: 0;
}

.legal-section ul {
  list-style: none;
  padding: 0;
  margin: 0;
}
.legal-section ul li {
  font-size: 0.9rem;
  line-height: 1.7;
  color: var(--lp-text-dim);
  padding-left: 18px;
  position: relative;
  margin-bottom: 8px;
}
.legal-section ul li::before {
  content: '';
  position: absolute;
  left: 0;
  top: 10px;
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: var(--lp-accent);
}
.legal-section ul li:last-child {
  margin-bottom: 0;
}

.email-link {
  color: var(--lp-accent);
  text-decoration: none;
  font-weight: 600;
}
.email-link:hover {
  text-decoration: underline;
}

@media (max-width: 768px) {
  .legal-container {
    padding: 32px 16px 60px;
  }
  .legal-card {
    padding: 28px 20px;
  }
  .legal-title {
    font-size: 1.3rem;
  }
}
</style>
