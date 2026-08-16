/**
 * Product related products document panel for the block editor.
 *
 * @package AveniaDigitalProducts
 * @since 1.0.0
 */
( function ( wp ) {
	'use strict';

	if ( ! wp.plugins || ! wp.editor || ! wp.element || ! wp.data || ! wp.coreData || ! wp.apiFetch ) {
		return;
	}

	var registerPlugin = wp.plugins.registerPlugin;
	var PluginDocumentSettingPanel = wp.editor.PluginDocumentSettingPanel;
	var el = wp.element.createElement;
	var useState = wp.element.useState;
	var useEffect = wp.element.useEffect;
	var useMemo = wp.element.useMemo;
	var Fragment = wp.element.Fragment;
	var useSelect = wp.data.useSelect;
	var useEntityProp = wp.coreData.useEntityProp;
	var __ = wp.i18n.__;
	var sprintf = wp.i18n.sprintf;

	var META_KEY = '_aveniadp_related_products';

	function getConfig() {
		return typeof aveniadpProductRelatedPanel !== 'undefined' ? aveniadpProductRelatedPanel : {};
	}

	function getExpectedPostType() {
		var config = getConfig();
		return config.postType || 'aveniadp_product';
	}

	function getMaxRelated() {
		var config = getConfig();
		return config.max && config.max > 0 ? config.max : 6;
	}

	function parseRelatedIds( value ) {
		if ( ! value ) {
			return [];
		}

		return value
			.split( ',' )
			.map( function ( id ) {
				return parseInt( id, 10 );
			} )
			.filter( function ( id ) {
				return ! isNaN( id ) && id > 0;
			} );
	}

	function RelatedProductsPanel() {
		var postType = useSelect( function ( select ) {
			return select( 'core/editor' ).getCurrentPostType();
		}, [] );

		var postId = useSelect( function ( select ) {
			return select( 'core/editor' ).getCurrentPostId();
		}, [] );

		var metaProp = useEntityProp( 'postType', postType, 'meta' );
		var meta = metaProp[0] || {};
		var setMeta = metaProp[1];
		var relatedValue = meta[ META_KEY ] || '';
		var selectedIds = parseRelatedIds( relatedValue );
		var maxRelated = getMaxRelated();

		var productsState = useState( [] );
		var products = productsState[0];
		var setProducts = productsState[1];

		var loadingState = useState( true );
		var isLoading = loadingState[0];
		var setIsLoading = loadingState[1];

		var searchState = useState( '' );
		var searchQuery = searchState[0];
		var setSearchQuery = searchState[1];

		useEffect(
			function () {
				var cancelled = false;
				setIsLoading( true );

				wp.apiFetch( {
					path:
						'/wp/v2/' +
						getExpectedPostType() +
						'?per_page=100&orderby=title&order=asc&context=edit&_fields=id,title',
				} )
					.then( function ( response ) {
						if ( cancelled ) {
							return;
						}
						var items = Array.isArray( response ) ? response : [];
						setProducts(
							items.filter( function ( item ) {
								return item.id !== postId;
							} )
						);
					} )
					.catch( function () {
						if ( ! cancelled ) {
							setProducts( [] );
						}
					} )
					.then( function () {
						if ( ! cancelled ) {
							setIsLoading( false );
						}
					} );

				return function () {
					cancelled = true;
				};
			},
			[ postId ]
		);

		var filteredProducts = useMemo(
			function () {
				var query = searchQuery.trim().toLowerCase();
				if ( ! query ) {
					return products;
				}

				return products.filter( function ( product ) {
					var title =
						product.title && product.title.rendered
							? product.title.rendered
							: '';
					return title.toLowerCase().indexOf( query ) !== -1;
				} );
			},
			[ products, searchQuery ]
		);

		function setRelatedIds( newIds ) {
			var nextMeta = Object.assign( {}, meta );
			nextMeta[ META_KEY ] = newIds.join( ',' );
			setMeta( nextMeta );
		}

		function toggleProduct( productId, isChecked ) {
			var ids = selectedIds.slice();

			if ( isChecked ) {
				if ( ids.indexOf( productId ) !== -1 ) {
					return;
				}
				if ( ids.length >= maxRelated ) {
					return;
				}
				ids.push( productId );
			} else {
				ids = ids.filter( function ( id ) {
					return id !== productId;
				} );
			}

			setRelatedIds( ids );
		}

		function getProductTitle( product ) {
			return product.title && product.title.rendered
				? product.title.rendered
				: __( '(no title)', 'avenia-digital-products' );
		}

		return el(
			Fragment,
			null,
			el(
				'p',
				{ className: 'aveniadp-product-related-panel__help' },
				sprintf(
					/* translators: %d: maximum number of related products */
					__(
						'Choose up to %d products to show below this product. Leave empty to use products from the same category.',
						'avenia-digital-products'
					),
					maxRelated
				)
			),
			el(
				'p',
				{ className: 'aveniadp-related-products-search-wrap' },
				el( 'input', {
					type: 'search',
					className: 'aveniadp-related-products-search widefat',
					placeholder: __( 'Search products…', 'avenia-digital-products' ),
					value: searchQuery,
					onChange: function ( event ) {
						setSearchQuery( event.target.value );
					},
				} )
			),
			el(
				'div',
				{
					className: 'aveniadp-related-products-list',
					role: 'group',
					'aria-label': __( 'Related products', 'avenia-digital-products' ),
				},
				isLoading &&
					el(
						'p',
						{ className: 'aveniadp-related-products-empty description' },
						__( 'Loading products…', 'avenia-digital-products' )
					),
				! isLoading &&
					products.length === 0 &&
					el(
						'p',
						{ className: 'aveniadp-related-products-empty description' },
						__( 'No other products found.', 'avenia-digital-products' )
					),
				! isLoading &&
					filteredProducts.map( function ( product ) {
						var isChecked = selectedIds.indexOf( product.id ) !== -1;
						var isDisabled =
							! isChecked && selectedIds.length >= maxRelated;

						return el(
							'label',
							{
								key: product.id,
								className:
									'aveniadp-related-product-item' +
									( isDisabled ? ' is-disabled' : '' ),
							},
							el( 'input', {
								type: 'checkbox',
								className: 'aveniadp-related-product-checkbox',
								value: product.id,
								checked: isChecked,
								disabled: isDisabled,
								onChange: function ( event ) {
									toggleProduct(
										product.id,
										event.target.checked
									);
								},
							} ),
							el(
								'span',
								{ className: 'aveniadp-related-product-title' },
								getProductTitle( product )
							)
						);
					} )
			),
			el(
				'p',
				{ className: 'aveniadp-related-products-count description' },
				sprintf(
					/* translators: 1: selected count, 2: maximum count */
					__( '%1$d of %2$d selected', 'avenia-digital-products' ),
					selectedIds.length,
					maxRelated
				)
			)
		);
	}

	function RelatedPanelSlot() {
		var postType = useSelect( function ( select ) {
			return select( 'core/editor' ).getCurrentPostType();
		}, [] );

		if ( postType !== getExpectedPostType() ) {
			return null;
		}

		return el(
			PluginDocumentSettingPanel,
			{
				name: 'aveniadp-product-related-panel',
				title: __( 'Related products', 'avenia-digital-products' ),
				className: 'aveniadp-product-related-panel',
				order: 29,
			},
			el( RelatedProductsPanel )
		);
	}

	registerPlugin( 'aveniadp-product-related', {
		render: RelatedPanelSlot,
	} );
}( window.wp ) );
