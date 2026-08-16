/**
 * Product gallery document panel for the block editor.
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
	var useState = wp.element.useState;
	var useEffect = wp.element.useEffect;
	var Fragment = wp.element.Fragment;
	var Button = wp.components.Button;
	var useSelect = wp.data.useSelect;
	var useEntityProp = wp.coreData.useEntityProp;
	var __ = wp.i18n.__;

	var META_KEY = '_aveniadp_product_gallery';

	function getExpectedPostType() {
		if ( typeof aveniadpProductGalleryPanel !== 'undefined' && aveniadpProductGalleryPanel.postType ) {
			return aveniadpProductGalleryPanel.postType;
		}
		return 'aveniadp_product';
	}

	function parseGalleryIds( value ) {
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

	function ProductGalleryPanel() {
		var postType = useSelect( function ( select ) {
			return select( 'core/editor' ).getCurrentPostType();
		}, [] );

		var metaProp = useEntityProp( 'postType', postType, 'meta' );
		var meta = metaProp[0] || {};
		var setMeta = metaProp[1];
		var galleryValue = meta[ META_KEY ] || '';
		var ids = parseGalleryIds( galleryValue );
		var previewsState = useState( {} );
		var previews = previewsState[0];
		var setPreviews = previewsState[1];

		useEffect(
			function () {
				var cancelled = false;
				var next = {};
				var pending = 0;

				function finish() {
					if ( ! cancelled && pending === 0 ) {
						setPreviews( next );
					}
				}

				if ( ! ids.length ) {
					setPreviews( {} );
					return function () {
						cancelled = true;
					};
				}

				ids.forEach( function ( id ) {
					var record = wp.data.select( 'core' ).getEntityRecord( 'postType', 'attachment', id );
					if ( record && record.source_url ) {
						next[ id ] =
							record.media_details &&
							record.media_details.sizes &&
							record.media_details.sizes.thumbnail
								? record.media_details.sizes.thumbnail.source_url
								: record.source_url;
						return;
					}

					pending++;
					wp.apiFetch( { path: '/wp/v2/media/' + id } )
						.then( function ( response ) {
							if ( response && response.source_url ) {
								next[ id ] =
									response.media_details &&
									response.media_details.sizes &&
									response.media_details.sizes.thumbnail
										? response.media_details.sizes.thumbnail.source_url
										: response.source_url;
							}
						} )
						.catch( function () {} )
						.then( function () {
							pending--;
							finish();
						} );
				} );

				finish();

				return function () {
					cancelled = true;
				};
			},
			[ galleryValue ]
		);

		function setGalleryIds( newIds ) {
			var nextMeta = Object.assign( {}, meta );
			nextMeta[ META_KEY ] = newIds.join( ',' );
			setMeta( nextMeta );
		}

		function openMediaLibrary() {
			var frame = wp.media( {
				title: __( 'Add images to gallery', 'avenia-digital-products' ),
				button: {
					text: __( 'Add to gallery', 'avenia-digital-products' ),
				},
				library: {
					type: 'image',
				},
				multiple: true,
			} );

			frame.on( 'select', function () {
				var selected = frame.state().get( 'selection' ).toJSON();
				var merged = ids.slice();

				selected.forEach( function ( attachment ) {
					if ( merged.indexOf( attachment.id ) === -1 ) {
						merged.push( attachment.id );
					}
				} );

				setGalleryIds( merged );
			} );

			frame.open();
		}

		function removeImage( id ) {
			setGalleryIds(
				ids.filter( function ( imageId ) {
					return imageId !== id;
				} )
			);
		}

		return el(
			Fragment,
			null,
			el(
				'p',
				{ className: 'aveniadp-product-gallery-panel__help' },
				__(
					'The featured image is shown first on the product page. Add extra images here for the gallery.',
					'avenia-digital-products'
				)
			),
			ids.length > 0 &&
				el(
					'ul',
					{ className: 'aveniadp-product-gallery-panel__images' },
					ids.map( function ( id ) {
						return el(
							'li',
							{ key: id, className: 'aveniadp-product-gallery-panel__image' },
							previews[ id ] &&
								el( 'img', {
									src: previews[ id ],
									alt: '',
								} ),
							el(
								'button',
								{
									type: 'button',
									className: 'aveniadp-product-gallery-panel__remove',
									onClick: function () {
										removeImage( id );
									},
									'aria-label': __( 'Remove image', 'avenia-digital-products' ),
								},
								'\u00d7'
							)
						);
					} )
				),
			el(
				Button,
				{
					variant: 'secondary',
					className: 'aveniadp-product-gallery-panel__add',
					onClick: openMediaLibrary,
				},
				__( 'Add to gallery', 'avenia-digital-products' )
			)
		);
	}

	function GalleryPanelSlot() {
		var postType = useSelect( function ( select ) {
			return select( 'core/editor' ).getCurrentPostType();
		}, [] );

		if ( postType !== getExpectedPostType() ) {
			return null;
		}

		return el(
			PluginDocumentSettingPanel,
			{
				name: 'aveniadp-product-gallery-panel',
				title: __( 'Product gallery', 'avenia-digital-products' ),
				className: 'aveniadp-product-gallery-panel',
				order: 26,
			},
			el( ProductGalleryPanel )
		);
	}

	registerPlugin( 'aveniadp-product-gallery', {
		render: GalleryPanelSlot,
	} );
}( window.wp ) );
