/**
 * Smart Product Recommendation Engine Gutenberg Block Editor Script
 */
(function(blocks, element, components, blockEditor) {
	'use strict';

	var el = element.createElement;
	var InspectorControls = blockEditor.InspectorControls;
	var PanelBody = components.PanelBody;
	var SelectControl = components.SelectControl;
	var TextControl = components.TextControl;
	var RangeControl = components.RangeControl;

	blocks.registerBlockType('spre/recommendation-block', {
		title: 'Smart Product Recommendations',
		description: 'Show intelligent recommendations such as Related Products, Frequently Bought Together, Personalized, or Trending items.',
		icon: 'products',
		category: 'woocommerce',
		attributes: {
			widget_type: {
				type: 'string',
				default: 'related'
			},
			title: {
				type: 'string',
				default: 'Recommended Products'
			},
			limit: {
				type: 'number',
				default: 4
			},
			period: {
				type: 'string',
				default: '7d'
			}
		},
		edit: function(props) {
			var attributes = props.attributes;

			function onChangeWidgetType(newType) {
				props.setAttributes({ widget_type: newType });
			}
			function onChangeTitle(newTitle) {
				props.setAttributes({ title: newTitle });
			}
			function onChangeLimit(newLimit) {
				props.setAttributes({ limit: newLimit });
			}
			function onChangePeriod(newPeriod) {
				props.setAttributes({ period: newPeriod });
			}

			// Render Editor layout
			return [
				// Controls in inspector sidebar
				el(InspectorControls, { key: 'controls' },
					el(PanelBody, { title: 'Widget Settings', initialOpen: true },
						el(SelectControl, {
							label: 'Recommendation Algorithm',
							value: attributes.widget_type,
							options: [
								{ label: 'Related Products (Weighted Similarity)', value: 'related' },
								{ label: 'Frequently Bought Together (Co-purchases)', value: 'fbt' },
								{ label: 'Personalized (Recommended for You)', value: 'personalized' },
								{ label: 'Trending Velocity', value: 'trending' }
							],
							onChange: onChangeWidgetType
						}),
						el(TextControl, {
							label: 'Custom Block Title',
							value: attributes.title,
							onChange: onChangeTitle
						}),
						el(RangeControl, {
							label: 'Products Count',
							value: attributes.limit,
							min: 1,
							max: 12,
							onChange: onChangeLimit
						}),
						// Conditional option: Trending Period
						attributes.widget_type === 'trending' ? el(SelectControl, {
							label: 'Sales Velocity Period',
							value: attributes.period,
							options: [
								{ label: 'Last 24 Hours', value: '24h' },
								{ label: 'Last 7 Days', value: '7d' },
								{ label: 'Last 30 Days', value: '30d' }
							],
							onChange: onChangePeriod
						}) : null
					)
				),
				// Canvas preview block
				el('div', {
					key: 'preview',
					className: 'spre-block-editor-preview',
					style: {
						border: '2px dashed #639',
						borderRadius: '8px',
						padding: '20px',
						backgroundColor: '#f9f6fc',
						textAlign: 'center'
					}
				},
					el('h4', { style: { margin: '0 0 8px 0', color: '#639' } }, 'Smart Recommendations: ' + attributes.title),
					el('p', { style: { margin: '0', fontSize: '0.85em', color: '#555' } },
						'Algorithm: ' + attributes.widget_type.toUpperCase() + ' | Show Limit: ' + attributes.limit
					)
				)
			];
		},
		save: function() {
			// Return null for dynamic block - display is handled server-side in PHP render_callback
			return null;
		}
	});
})(
	window.wp.blocks,
	window.wp.element,
	window.wp.components,
	window.wp.blockEditor
);
