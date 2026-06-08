# 🛒 Smart Product Recommendation Engine (SPRE)

> A high-performance, enterprise-grade recommendation system for WooCommerce — replacing sluggish default queries with sub-2ms algorithmic computations powered by custom-indexed SQL tables and background processing.

---

## ✨ Features

- ⚡ **Sub-2ms response times** via pre-computed similarity matrices
- 🧠 **5 recommendation algorithms** — weighted similarity, FBT, personalized, trending, and rule-based
- 🔒 **GDPR & CCPA compliant** out of the box
- 🧪 **Built-in A/B testing** with attributed revenue analytics
- 🎨 **SaaS-style admin dashboard** with live charts, color pickers, and a custom CSS editor
- 📱 **Touch-native carousel** with CSS scroll-snapping — no jQuery slider libraries

---

## 🧩 How It Works

### 1. Weighted Related Products Engine

Replaces WooCommerce's native related products loop with a mathematical scoring system.

| Criteria | Weight |
|---|---|
| Same Category Match | High |
| Shared Product Tags | Medium |
| Price Proximity | Normalized |
| Matching Brand / Attribute | Taxonomy-based |
| Catalog Popularity | Lifetime sales |

A **daily cron job** pre-calculates and caches the full similarity matrix in `wp_spre_product_relations`, keeping page load times under 2ms — zero real-time CPU spikes.

---

### 2. Frequently Bought Together (FBT)

Identifies items commonly purchased together using actual store order history.

- Scans completed orders for co-purchase patterns
- Builds a normalized co-purchase matrix stored in a dedicated, indexed table
- Renders a "Frequently Bought Together" widget on single product pages
- Bypasses heavy joins on WooCommerce core tables entirely

---

### 3. Personalized Recommendations

Tailored product streams per individual shopper.

- Tracks products viewed during the active session
- For logged-in users, scans past orders to identify frequently purchased categories and tags
- Merges browsing and purchase signals in real-time for dynamic, relevant listings

---

### 4. Trending Products Engine

Surfaces fast-moving, high-demand items across the store.

**Trending Score formula:**

```
Trending Score = (Sales × 10) + (Add to Cart × 3) + Impressions
```

Configurable time windows via shortcode: **24 hours**, **7 days**, or **30 days**.

---

### 5. Visual Rule Builder

Manual overrides for algorithmic recommendations using an intuitive React-based admin panel.

**Example rule:**
```
IF  viewing [Product / Category]
OR  cart contains [specific items]
THEN recommend [custom product list]
```

Rules are evaluated **first**, with algorithms as the fallback — giving store admins full control when needed.

---

### 6. Stateless A/B Testing Framework

Compare algorithm performance without expensive per-request tracking.

Traffic is split deterministically using a session hash modulo:

```
Bucket = md5(session_hash + test_id) % 100
```

Each variation tracks **impressions**, **CTR**, **conversions**, and **revenue** — so you can see exactly which algorithm drives more sales.

---

### 7. GDPR-Compliant User Tracking

Privacy-first tracking with zero PII stored.

- **Anonymized sessions** — guests tracked via salted SHA-256 hash of IP + User-Agent
- **Auto-pruning cron jobs:**
  - Guest view history (`wp_spre_views`) pruned after **30 days**
  - Click/impression logs (`wp_spre_analytics`) pruned after **90 days** (aggregated metrics retained)
- **Cookie consent integration** — hooks into Complianz, CookieYes, and GDPR Cookie Consent. Tracking suspends until the visitor opts in.

---

### 8. SaaS-Style Admin Dashboard

A full React dashboard embedded in the WordPress admin panel.

| Feature | Description |
|---|---|
| Analytics Charts | Interactive Chart.js line graphs for views, clicks, CTR, conversions, and revenue |
| Display Controls | Toggle card elements: prices, badges, descriptions, ratings, add-to-cart buttons |
| Color Pickers | Customize brand, text, price, and button colors — injected as CSS variables |
| Custom CSS Editor | Sanitized raw stylesheet editor for storefront overrides |
| One-Click Reset | Rolls all settings and styles back to factory defaults |

---

### 9. Touch-Native Carousel Slider

A smooth, responsive carousel with zero third-party dependencies.

- CSS scroll-snapping with hardware-accelerated touch swiping
- No Slick, Owl Carousel, or other heavy JS slider libraries
- Smart directional Prev/Next arrows on desktop
- Scroll boundary detection — arrows auto-hide at the start and end of the track

---

## 🗂️ Shortcodes

```php
[spre_recommendations]        // Weighted related products
[spre_trending]               // Trending products (configurable window)
[spre_frequently_bought]      // Frequently bought together
```

---

## 🗃️ Custom Database Tables

| Table | Purpose |
|---|---|
| `wp_spre_product_relations` | Pre-computed similarity matrix |
| `wp_spre_views` | Guest browsing history (pruned after 30 days) |
| `wp_spre_analytics` | Click & impression logs (pruned after 90 days) |
| `wp_spre_recommendations` | Cached recommendation results |
| `wp_spre_ab_tests` | A/B test configurations and results |

---

## 🛠️ Tech Stack

- **PHP** — PSR-4 autoloading, reflection-based DI container, REST API controllers
- **React 18** — Admin dashboard, Rule Builder, A/B Test config panel
- **Chart.js** — Analytics visualizations
- **Vite** — Asset bundling and production builds
- **PHPUnit** — Unit and integration test suites
- **WooCommerce Hooks** — Deep integration via actions and filters

---

## 📄 License

Proprietary. All rights reserved.
