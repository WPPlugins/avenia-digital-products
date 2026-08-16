/**
 * Open product meta box panels by default in the block editor (preferences: inactivePanels / openPanels).
 *
 * @package AveniaDigitalProducts
 * @since 1.0.0
 */
( function ( wp ) {
	'use strict';

	if ( ! wp || ! wp.domReady || ! wp.data ) {
		return;
	}

	var PANEL_IDS = [
		'meta-box-aveniadp_product_details',
		'meta-box-aveniadp_product_pricing',
		'meta-box-aveniadp_product_info_sections',
	];

	function getExpectedPostType() {
		if ( typeof aveniadpProductEditorPanels !== 'undefined' && aveniadpProductEditorPanels.postType ) {
			return aveniadpProductEditorPanels.postType;
		}
		return 'aveniadp_product';
	}

	function urlIsProductEditor() {
		var pt = getExpectedPostType();
		var s = window.location.search || '';
		return s.indexOf( 'post_type=' + pt ) !== -1 || s.indexOf( 'post_type=' + encodeURIComponent( pt ) ) !== -1;
	}

	function editorIsProduct() {
		var pt = getExpectedPostType();
		var editor = wp.data.select( 'core/editor' );
		if ( ! editor || typeof editor.getCurrentPostType !== 'function' ) {
			return false;
		}
		return editor.getCurrentPostType() === pt;
	}

	function contextIsProductScreen() {
		return urlIsProductEditor() || editorIsProduct();
	}

	function storageKeyForExpandOnce() {
		return 'aveniadp_product_meta_panels_auto_expanded_' + window.location.pathname + window.location.search;
	}

	function applyProductMetaPanelPreferences( alsoExpand ) {
		var select = wp.data.select( 'core/preferences' );
		var dispatch = wp.data.dispatch( 'core/preferences' );
		if ( ! select || ! dispatch || typeof select.get !== 'function' || typeof dispatch.set !== 'function' ) {
			return false;
		}

		var inactive = select.get( 'core', 'inactivePanels' ) || [];
		var open = select.get( 'core', 'openPanels' ) || [];

		var newInactive = inactive.filter( function ( id ) {
			return PANEL_IDS.indexOf( id ) === -1;
		} );

		dispatch.set( 'core', 'inactivePanels', newInactive );

		if ( alsoExpand ) {
			var newOpen = open.slice();
			PANEL_IDS.forEach( function ( id ) {
				if ( newOpen.indexOf( id ) === -1 ) {
					newOpen.push( id );
				}
			} );
			dispatch.set( 'core', 'openPanels', newOpen );
		}

		return true;
	}

	function tryApply() {
		if ( ! contextIsProductScreen() ) {
			return false;
		}
		var expandOnce = ! window.sessionStorage.getItem( storageKeyForExpandOnce() );
		if ( ! applyProductMetaPanelPreferences( expandOnce ) ) {
			return false;
		}
		if ( expandOnce ) {
			window.sessionStorage.setItem( storageKeyForExpandOnce(), '1' );
		}
		return true;
	}

	function getMetaPanelLabel() {
		if ( typeof aveniadpProductEditorPanels !== 'undefined' && aveniadpProductEditorPanels.metaPanelLabel ) {
			return aveniadpProductEditorPanels.metaPanelLabel;
		}
		return 'Product Details';
	}

	/**
	 * Core labels the collapsible region "Meta Boxes" (wp-edit-post). Retitle for products.
	 */
	function renameMetaBoxesMainPanelLabel() {
		if ( ! contextIsProductScreen() ) {
			return;
		}
		var label = getMetaPanelLabel();
		var main = document.querySelector( '.edit-post-meta-boxes-main' );
		if ( main ) {
			main.setAttribute( 'aria-label', label );
		}
		var presenter = document.querySelector( '.edit-post-meta-boxes-main__presenter' );
		if ( ! presenter ) {
			return;
		}
		var btn = presenter.querySelector( 'button' );
		if ( ! btn ) {
			return;
		}
		var i;
		var replaced = false;
		for ( i = 0; i < btn.childNodes.length; i++ ) {
			var n = btn.childNodes[ i ];
			if ( n.nodeType === Node.TEXT_NODE && n.textContent.trim() ) {
				n.textContent = label;
				replaced = true;
				break;
			}
		}
		if ( ! replaced && btn.childNodes.length ) {
			btn.insertBefore( document.createTextNode( label ), btn.firstChild );
		}
	}

	function debounce( fn, ms ) {
		var t;
		return function () {
			window.clearTimeout( t );
			t = window.setTimeout( fn, ms );
		};
	}

	wp.domReady( function () {
		var tries = 0;
		var maxTries = 150;
		var prefsApplied = false;
		var timer = window.setInterval( function () {
			tries++;
			if ( contextIsProductScreen() ) {
				renameMetaBoxesMainPanelLabel();
				if ( ! prefsApplied && tryApply() ) {
					prefsApplied = true;
				}
			}
			if ( tries >= maxTries ) {
				window.clearInterval( timer );
			}
		}, 100 );

		if ( typeof MutationObserver !== 'undefined' ) {
			var debouncedRename = debounce( function () {
				if ( contextIsProductScreen() ) {
					renameMetaBoxesMainPanelLabel();
				}
			}, 200 );
			var obs = new MutationObserver( function () {
				debouncedRename();
			} );
			obs.observe( document.body, { childList: true, subtree: true } );
		}
	} );
}( window.wp ) );
