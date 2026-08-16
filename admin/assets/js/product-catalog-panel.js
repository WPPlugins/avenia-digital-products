/**
 * Product catalog display document panel for the block editor.
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
	var useSelect = wp.data.useSelect;
	var useEntityProp = wp.coreData.useEntityProp;
	var __ = wp.i18n.__;

	var META_KEY = '_aveniadp_pinned_product';

	function getExpectedPostType() {
		return typeof aveniadpProductCatalogPanel !== 'undefined' && aveniadpProductCatalogPanel.postType
			? aveniadpProductCatalogPanel.postType
			: 'aveniadp_product';
	}

	function CatalogDisplayPanel() {
		var postType = useSelect( function ( select ) {
			return select( 'core/editor' ).getCurrentPostType();
		}, [] );

		var metaProp = useEntityProp( 'postType', postType, 'meta' );
		var meta = metaProp[0] || {};
		var setMeta = metaProp[1];
		var isPinned = '1' === String( meta[ META_KEY ] || '0' );

		function setPinned( checked ) {
			var nextMeta = Object.assign( {}, meta );
			nextMeta[ META_KEY ] = checked ? '1' : '0';
			setMeta( nextMeta );
		}

		return el(
			Fragment,
			null,
			el(
				'p',
				{ className: 'aveniadp-product-catalog-panel__help' },
				__(
					'Shows this product full width at the top of the shop and catalog with a 9:4 image. Only one product can be pinned at a time.',
					'avenia-digital-products'
				)
			),
			el(
				'div',
				{
					className: 'aveniadp-catalog-display-list',
					role: 'group',
					'aria-label': __( 'Catalog display', 'avenia-digital-products' ),
				},
				el(
					'label',
					{ className: 'aveniadp-catalog-display-item' },
					el( 'input', {
						type: 'checkbox',
						className: 'aveniadp-catalog-display-checkbox',
						checked: isPinned,
						onChange: function ( event ) {
							setPinned( event.target.checked );
						},
					} ),
					el(
						'span',
						{ className: 'aveniadp-catalog-display-title' },
						__( 'Pin to catalog', 'avenia-digital-products' )
					)
				)
			)
		);
	}

	function CatalogPanelSlot() {
		var postType = useSelect( function ( select ) {
			return select( 'core/editor' ).getCurrentPostType();
		}, [] );

		if ( postType !== getExpectedPostType() ) {
			return null;
		}

		return el(
			PluginDocumentSettingPanel,
			{
				name: 'aveniadp-product-catalog-panel',
				title: __( 'Catalog display', 'avenia-digital-products' ),
				className: 'aveniadp-product-catalog-panel',
				order: 30,
			},
			el( CatalogDisplayPanel )
		);
	}

	registerPlugin( 'aveniadp-product-catalog', {
		render: CatalogPanelSlot,
	} );
}( window.wp ) );
