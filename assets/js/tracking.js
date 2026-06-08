/**
 * Smart Product Recommendation Engine Frontend Tracking
 */
(function() {
	'use strict';

	document.addEventListener('DOMContentLoaded', function() {
		// Verify tracking variables are present
		if (typeof spre_tracking_params === 'undefined') {
			return;
		}

		var api_url = spre_tracking_params.api_url;
		var product_id = parseInt(spre_tracking_params.product_id, 10);

		// 1. Single Product View Tracking
		if (product_id > 0) {
			sendTrackingEvent('view', {
				product_id: product_id,
				widget_type: 'direct'
			});
		}

		// 2. Widget Interactions Tracking (Impressions and Clicks)
		setupWidgetTracking();

		/**
		 * Helper to send asynchronous events to our REST API.
		 */
		function sendTrackingEvent(eventType, data) {
			var payload = {
				event_type: eventType,
				product_id: data.product_id,
				source_product_id: data.source_product_id || product_id || null,
				widget_type: data.widget_type || 'direct',
				ab_test_id: data.ab_test_id || null,
				ab_variation: data.ab_variation || null
			};

			var xhr = new XMLHttpRequest();
			xhr.open('POST', api_url, true);
			xhr.setRequestHeader('Content-Type', 'application/json');
			if (spre_tracking_params.nonce) {
				xhr.setRequestHeader('X-WP-Nonce', spre_tracking_params.nonce);
			}
			xhr.send(JSON.stringify(payload));
		}

		/**
		 * Scans for recommendation widgets and registers observers and click triggers.
		 */
		function setupWidgetTracking() {
			var widgets = document.querySelectorAll('.spre-recommendations-wrapper');
			if (widgets.length === 0) {
				return;
			}

			// Intersection Observer to track impressions when widgets are scrolled into view
			var impressionObserver = null;
			if ('IntersectionObserver' in window) {
				impressionObserver = new IntersectionObserver(function(entries, observer) {
					entries.forEach(function(entry) {
						if (entry.isIntersecting) {
							var widget = entry.target;
							trackWidgetImpression(widget);
							observer.unobserve(widget); // Fire once per view
						}
					});
				}, { threshold: 0.2 }); // Trigger when 20% visible
			}

			widgets.forEach(function(widget) {
				if (impressionObserver) {
					impressionObserver.observe(widget);
				} else {
					// Fallback if IntersectionObserver not supported
					trackWidgetImpression(widget);
				}

				// Click triggers for products inside the widget
				var productCards = widget.querySelectorAll('.spre-product-card');
				productCards.forEach(function(card) {
					var clickTarget = card.querySelector('.spre-product-link');
					var cartTarget = card.querySelector('.spre-add-to-cart-button');

					var trackClick = function() {
						trackWidgetClick(widget, card);
					};

					if (clickTarget) {
						clickTarget.addEventListener('click', trackClick);
					}
					if (cartTarget) {
						cartTarget.addEventListener('click', trackClick);
					}
				});
			});
		}

		/**
		 * Sends impression logs for all products inside the widget.
		 */
		function trackWidgetImpression(widget) {
			var widgetType = widget.getAttribute('data-widget-type');
			var abTestId = parseInt(widget.getAttribute('data-ab-test-id'), 10) || null;
			var abVariation = widget.getAttribute('data-ab-variation') || null;

			var productCards = widget.querySelectorAll('.spre-product-card');
			productCards.forEach(function(card) {
				var targetProductId = parseInt(card.getAttribute('data-product-id'), 10);
				if (targetProductId > 0) {
					sendTrackingEvent('impression', {
						product_id: targetProductId,
						widget_type: widgetType,
						ab_test_id: abTestId,
						ab_variation: abVariation
					});
				}
			});
		}

		/**
		 * Sends click log for the interacted product card.
		 */
		function trackWidgetClick(widget, card) {
			var widgetType = widget.getAttribute('data-widget-type');
			var abTestId = parseInt(widget.getAttribute('data-ab-test-id'), 10) || null;
			var abVariation = widget.getAttribute('data-ab-variation') || null;
			var targetProductId = parseInt(card.getAttribute('data-product-id'), 10);

			if (targetProductId > 0) {
				sendTrackingEvent('click', {
					product_id: targetProductId,
					widget_type: widgetType,
					ab_test_id: abTestId,
					ab_variation: abVariation
				});
			}
		}

		// 3. Slider/Carousel Arrow Navigation Setup
		setupCarouselSliders();

		function setupCarouselSliders() {
			var wrappers = document.querySelectorAll('.spre-carousel-wrapper');
			if (wrappers.length === 0) {
				return;
			}

			wrappers.forEach(function(wrapper) {
				var slider = wrapper.querySelector('.spre-layout-slider');
				var prevBtn = wrapper.querySelector('.spre-carousel-prev');
				var nextBtn = wrapper.querySelector('.spre-carousel-next');

				if (!slider || !prevBtn || !nextBtn) {
					return;
				}

				// Check scroll limits and show/hide arrows
				var toggleArrows = function() {
					var scrollLeft = slider.scrollLeft;
					var maxScroll = slider.scrollWidth - slider.clientWidth;
					
					// Hide prev arrow if scrolled to start
					if (scrollLeft <= 5) {
						prevBtn.classList.add('disabled');
					} else {
						prevBtn.classList.remove('disabled');
					}

					// Hide next arrow if scrolled to end
					if (scrollLeft >= maxScroll - 5) {
						nextBtn.classList.add('disabled');
					} else {
						nextBtn.classList.remove('disabled');
					}
				};

				// Initial check
				toggleArrows();
				
				// Monitor scrolling & resize
				slider.addEventListener('scroll', toggleArrows);
				window.addEventListener('resize', toggleArrows);

				// Scroll actions - move by individual card width
				var getScrollAmount = function() {
					var card = slider.querySelector('.spre-product-card');
					if (card) {
						return card.clientWidth + 24; // Card width + gap
					}
					return slider.clientWidth * 0.8; // Fallback
				};

				prevBtn.addEventListener('click', function() {
					slider.scrollBy({
						left: -getScrollAmount(),
						behavior: 'smooth'
					});
				});

				nextBtn.addEventListener('click', function() {
					slider.scrollBy({
						left: getScrollAmount(),
						behavior: 'smooth'
					});
				});
			});
		}
	});
})();
