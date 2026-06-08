=== Smart Product Recommendation Engine ===
Contributors: google-deepmind
Tags: woocommerce, recommendations, related products, upsell, e-commerce, trending, conversion, elementor, gutenberg
Requires at least: 5.8
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv3 or later
License URI: http://www.gnu.org/licenses/gpl-3.0.html

An enterprise-grade, high-performance product recommendation engine for WooCommerce. Uses co-purchase logs, similarity weights, and trending velocity to maximize average order value.

== Description ==

Smart Product Recommendation Engine is a scalable recommendation system designed for enterprise WooCommerce stores. It replaces WooCommerce's default related products with an intelligent scoring system and introduces sections like "Frequently Bought Together" (FBT), "Personalized Recommendations", and "Trending Products".

Key highlights include:
*   **Weighted Related Products Engine:** Scores similarity based on category matches, matching tags, price ranges, brand attributes, and sales popularity.
*   **Frequently Bought Together:** Aggregates co-purchase patterns from historical orders in the background. Uses high-performance indexed tables to prevent performance issues.
*   **Personalized Recommendations:** Analyzes logged-in user purchase patterns, recently viewed logs, and favorite categories to serve tailored suggestions.
*   **Trending Products:** Tracks views, sales, and add-to-carts to compute sales velocities for the last 24 hours, 7 days, or 30 days.
*   **Visual Rule Builder:** Define conditional logic, such as "If cart contains Laptop, recommend Accessories" or "If viewing Shoes, recommend Socks".
*   **A/B Testing Framework:** Compare two recommendation algorithms side-by-side (e.g., Weighted Similarity vs. FBT) and track clicks, CTR, and revenue.
*   **SaaS-Style Admin Dashboard:** React-based admin center utilizing Chart.js to report clicks, impressions, conversion rate, and revenue generation.
*   **GDPR-Safe Tracking:** Uses randomized SHA-256 session cookie hashes for guests without storing personal data.

== Installation ==

1. Upload the plugin folder to the `/wp-content/plugins/` directory, or install directly through the WordPress plugins dashboard.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. If installing manually from Git, run `composer install` inside the plugin folder to compile PSR-4 classes.
4. Go to **WooCommerce > Recommendation Engine** to configure algorithms, A/B tests, rules, and view the analytics dashboard.

== Shortcodes ==

The plugin provides three highly customizable shortcodes for frontend placement:

*   **[spre_recommendations limit="4" title="Recommended for You"]**: Displays personalized items for logged-in users, falling back to trending products.
*   **[spre_trending limit="4" period="7d" title="Trending Now"]**: Displays trending items based on sales velocity. Period options: `24h`, `7d`, `30d`.
*   **[spre_frequently_bought limit="3" title="Frequently Bought Together"]**: Displays items bought together with the current product. Only active on product pages.
