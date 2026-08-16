/**
 * Product demo link document panel for the block editor.
 *
 * @package AveniaDigitalProducts
 * @since 1.0.0
 */
( function ( wp ) {
	'use strict';

	if ( ! wp.plugins || ! wp.editor || ! wp.element || ! wp.data || ! wp.coreData ) {
		return;
	}

	var registerPlugin = wp.plugins.registerPlugin;
	var PluginDocumentSettingPanel = wp.editor.PluginDocumentSettingPanel;
	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var TextControl = wp.components.TextControl;
	var useSelect = wp.data.useSelect;
	var useEntityProp = wp.coreData.useEntityProp;
	var __ = wp.i18n.__;

	var TEXT_META_KEY = '_aveniadp_demo_button_text';
	var URL_META_KEY = '_aveniadp_demo_button_url';

	function getExpectedPostType() {
		return typeof aveniadpProductDemoPanel !== 'undefined' && aveniadpProductDemoPanel.postType
			? aveniadpProductDemoPanel.postType
			: 'aveniadp_product';
	}

	function ProductDemoPanel() {
		var postType = useSelect( function ( select ) {
			return select( 'core/editor' ).getCurrentPostType();
		}, [] );

		var metaProp = useEntityProp( 'postType', postType, 'meta' );
		var meta = metaProp[0] || {};
		var setMeta = metaProp[1];
		var demoText = meta[ TEXT_META_KEY ] || '';
		var demoUrl = meta[ URL_META_KEY ] || '';

		function updateMeta( key, value ) {
			var nextMeta = Object.assign( {}, meta );
			nextMeta[ key ] = value;
			setMeta( nextMeta );
		}

		return el(
			Fragment,
			null,
			el(
				'p',
				{ className: 'aveniadp-product-demo-panel__help' },
				__(
					'Optional demo button on the product page. Leave the URL empty to hide it. Opens in a new tab.',
					'avenia-digital-products'
				)
			),
			el( TextControl, {
				label: __( 'Button text', 'avenia-digital-products' ),
				value: demoText,
				placeholder: __( 'View Demo', 'avenia-digital-products' ),
				onChange: function ( value ) {
					updateMeta( TEXT_META_KEY, value );
				},
				__nextHasNoMarginBottom: true,
				__next40pxDefaultSize: true,
			} ),
			el( 'div', { className: 'aveniadp-product-demo-panel__field' },
				el( TextControl, {
					label: __( 'Link URL', 'avenia-digital-products' ),
					type: 'url',
					value: demoUrl,
					placeholder: 'https://',
					onChange: function ( value ) {
						updateMeta( URL_META_KEY, value );
					},
					__nextHasNoMarginBottom: true,
					__next40pxDefaultSize: true,
				} )
			)
		);
	}

	function DemoPanelSlot() {
		var postType = useSelect( function ( select ) {
			return select( 'core/editor' ).getCurrentPostType();
		}, [] );

		if ( postType !== getExpectedPostType() ) {
			return null;
		}

		return el(
			PluginDocumentSettingPanel,
			{
				name: 'aveniadp-product-demo-panel',
				title: __( 'Demo link', 'avenia-digital-products' ),
				className: 'aveniadp-product-demo-panel',
				order: 27,
			},
			el( ProductDemoPanel )
		);
	}

	registerPlugin( 'aveniadp-product-demo', {
		render: DemoPanelSlot,
	} );
}( window.wp ) );
