/**
 * Public JavaScript.
 *
 * @package AveniaDigitalProducts
 * @since 1.0.0
 */

(function($) {
	'use strict';

	function aveniadpShowInlineNotice($target, message, type, autoHideMs) {
		if (!$target || !$target.length) {
			return;
		}

		var isSuccess = type === 'success';

		$target
			.addClass('aveniadp-notice')
			.removeClass('is-success is-error')
			.toggleClass('is-success', isSuccess)
			.toggleClass('is-error', !isSuccess)
			.text(message)
			.prop('hidden', false);

		clearTimeout($target.data('clearTimeout'));
		if (autoHideMs) {
			$target.data('clearTimeout', setTimeout(function() {
				$target.prop('hidden', true).text('').removeClass('is-success is-error');
			}, autoHideMs));
		}
	}

	function aveniadpShowToastNotice(message, type, duration) {
		var isSuccess = type === 'success';
		var $toast = $('<div>', {
			class: 'aveniadp-notice aveniadp-notice--toast ' + (isSuccess ? 'is-success' : 'is-error'),
			role: 'alert',
			text: message
		});

		$('body').append($toast);

		setTimeout(function() {
			$toast.remove();
		}, duration || 8000);

		return $toast;
	}

	$(document).ready(function() {
		var galleryFadeMs = 220;
		var aveniadpBootstrapPromise = null;

		/**
		 * Apply aveniadp_bootstrap payload (nonce, auth, cart) to the current page.
		 *
		 * @param {Object} data Bootstrap payload.
		 */
		function aveniadpApplyBootstrapPayload(data) {
			if (!data || !window.aveniadpPublic) {
				return;
			}

			if (data.nonce) {
				aveniadpPublic.nonce = data.nonce;
			}
			aveniadpPublic.isLoggedIn = !!data.isLoggedIn;
			aveniadpPublic.userEmail = data.userEmail || '';
			aveniadpPublic.needsBootstrap = false;

			if (typeof data.cart_count !== 'undefined') {
				aveniadpUpdateCartIconCount(data.cart_count);
			}

			if (data.drawer) {
				aveniadpRenderCartDrawer(data.drawer);
			}

			// Prefer bootstrap cart_items map; fall back to drawer-derived options.
			var cartItems = data.cart_items;
			if (!cartItems || Array.isArray(cartItems)) {
				cartItems = aveniadpCartItemsFromDrawer(data.drawer);
			}
			aveniadpSyncPurchaseFormFromCart(cartItems);

			$('.aveniadp-free-download-account-link').prop('hidden', !aveniadpPublic.isLoggedIn);
		}

		/**
		 * Build a product-ID → options map from drawer items.
		 *
		 * @param {Object|null} drawer Drawer payload.
		 * @return {Object}
		 */
		function aveniadpCartItemsFromDrawer(drawer) {
			var map = {};
			if (!drawer || !drawer.items || !drawer.items.length) {
				return map;
			}

			drawer.items.forEach(function(item) {
				if (!item || !item.product_id) {
					return;
				}
				map[String(item.product_id)] = {
					license_quantity: item.license_quantity
				};
			});

			return map;
		}

		/**
		 * Sync the purchase form button/label and selected options from live cart state.
		 *
		 * Required because product pages may be page-cached without cart-specific HTML.
		 *
		 * @param {Object} cartItems Product-ID-keyed cart options.
		 */
		function aveniadpSyncPurchaseFormFromCart(cartItems) {
			var $form = $('#aveniadp-purchase-form');
			if (!$form.length) {
				return;
			}

			var productId = String($form.find('input[name="product_id"]').val() || '');
			if (!productId) {
				return;
			}

			cartItems = cartItems || {};
			var item = cartItems[productId] || cartItems[parseInt(productId, 10)] || null;
			var isUpdate = !!item;
			var labels = (window.aveniadpPublic && aveniadpPublic.labels) ? aveniadpPublic.labels : {};

			if (isUpdate) {
				$form.attr('data-update-cart', '1');
			} else {
				$form.removeAttr('data-update-cart');
			}

			$form.find('.aveniadp-add-to-cart-label').text(
				isUpdate
					? (labels.updateCart || 'Update Cart')
					: (labels.addToCart || 'Add to Cart')
			);

			if (!isUpdate) {
				return;
			}

			var licenseQuantity = item.license_quantity;
			if (licenseQuantity === '' || licenseQuantity === null || typeof licenseQuantity === 'undefined') {
				return;
			}

			var $licenseSelect = $('#aveniadp_license_quantity');
			if ($licenseSelect.length && String($licenseSelect.val()) !== String(licenseQuantity)) {
				$licenseSelect.val(String(licenseQuantity));
				aveniadpSelectOptionButton('aveniadp_license_quantity', licenseQuantity);
			}

			aveniadpRefreshProductPrice();
		}

		/**
		 * Mark the matching option button as selected for a hidden select.
		 *
		 * @param {string} targetId Select element id.
		 * @param {*} value Selected value.
		 */
		function aveniadpSelectOptionButton(targetId, value) {
			var $group = $('.aveniadp-option-group[data-target="' + targetId + '"]');
			if (!$group.length) {
				return;
			}

			$group.find('.aveniadp-option-btn').each(function() {
				var $btn = $(this);
				var selected = String($btn.data('value')) === String(value);
				$btn.toggleClass('is-selected', selected).attr('aria-pressed', selected ? 'true' : 'false');
			});
		}

		/**
		 * Load public session data once per page load.
		 *
		 * @return {JQuery.Promise}
		 */
		function aveniadpEnsureBootstrap() {
			if (!window.aveniadpPublic) {
				return $.Deferred().resolve().promise();
			}

			if (!aveniadpPublic.needsBootstrap && aveniadpPublic.nonce) {
				return $.Deferred().resolve().promise();
			}

			if (aveniadpBootstrapPromise) {
				return aveniadpBootstrapPromise;
			}

			aveniadpBootstrapPromise = $.ajax({
				url: aveniadpPublic.ajaxUrl,
				type: 'POST',
				data: {
					action: 'aveniadp_bootstrap'
				}
			}).then(function(response) {
				if (response && response.success && response.data) {
					aveniadpApplyBootstrapPayload(response.data);
				}
			}, function() {
				aveniadpBootstrapPromise = null;
			});

			return aveniadpBootstrapPromise;
		}

		/**
		 * admin-ajax POST that waits for aveniadp_bootstrap and attaches the public nonce.
		 *
		 * @param {Object} options jQuery.ajax options.
		 * @return {JQuery.Promise}
		 */
		function aveniadpPublicAjax(options) {
			options = $.extend(true, {}, options || {});

			function send() {
				if ($.isPlainObject(options.data)) {
					options.data.nonce = aveniadpPublic.nonce;
				}
				return $.ajax(options);
			}

			return aveniadpEnsureBootstrap().then(send, send);
		}

		aveniadpEnsureBootstrap();

		function aveniadpApplyGalleryImageAttrs($image, $thumb) {
			$image
				.attr('src', $thumb.data('src'))
				.attr('data-image-id', $thumb.data('image-id'));

			if ($thumb.data('srcset')) {
				$image.attr('srcset', $thumb.data('srcset'));
			} else {
				$image.removeAttr('srcset');
			}

			if ($thumb.data('sizes')) {
				$image.attr('sizes', $thumb.data('sizes'));
			} else {
				$image.removeAttr('sizes');
			}
		}

		function aveniadpSwitchGalleryImage($gallery, $thumb, $mainImage) {
			var newSrc = $thumb.data('src');
			var newImageId = $thumb.data('image-id');
			var $stage = $mainImage.closest('.aveniadp-product-image');

			if (!newSrc || $gallery.data('aveniadpGallerySwitching')) {
				return;
			}

			if (String($mainImage.data('image-id')) === String(newImageId)) {
				return;
			}

			var preload = new Image();
			preload.onload = function() {
				var $outgoing = $mainImage;
				var completed = false;

				function finishCrossfade($incoming) {
					if (completed) {
						return;
					}
					completed = true;
					$incoming.removeClass('is-fading-in is-layered is-incoming');
					$outgoing.remove();
					$gallery.removeData('aveniadpGallerySwitching');
				}

				$gallery.data('aveniadpGallerySwitching', true);

				var $incoming = $('<img>', {
					'class': 'aveniadp-product-gallery-main-image is-layered is-incoming',
					'alt': $outgoing.attr('alt') || '',
					'loading': 'eager'
				});
				aveniadpApplyGalleryImageAttrs($incoming, $thumb);
				$stage.append($incoming);

				requestAnimationFrame(function() {
					requestAnimationFrame(function() {
						$incoming.removeClass('is-incoming').addClass('is-fading-in');
						$outgoing.addClass('is-layered is-fading-out');
					});
				});

				$incoming.one('transitionend', function(e) {
					if (e.target === $incoming[0]) {
						finishCrossfade($incoming);
					}
				});
				window.setTimeout(function() {
					finishCrossfade($incoming);
				}, galleryFadeMs + 40);
			};
			preload.src = newSrc;
		}

		// Product gallery lightbox.
		var $galleryLightbox = null;
		var galleryLightboxImages = [];
		var galleryLightboxIndex = 0;
		var galleryLightboxLabels = (window.aveniadpPublic && aveniadpPublic.labels) ? aveniadpPublic.labels : {};

		function aveniadpEnsureGalleryLightbox() {
			if ($galleryLightbox) {
				return $galleryLightbox;
			}

			$galleryLightbox = $('<div>', {
				'class': 'aveniadp-gallery-lightbox',
				'aria-hidden': 'true',
				'role': 'dialog',
				'aria-modal': 'true',
				'aria-label': galleryLightboxLabels.galleryLightbox || 'Image gallery'
			}).append(
				$('<button>', {
					type: 'button',
					'class': 'aveniadp-gallery-lightbox-close',
					'aria-label': galleryLightboxLabels.galleryClose || 'Close'
				}).html('&times;'),
				$('<button>', {
					type: 'button',
					'class': 'aveniadp-gallery-lightbox-nav aveniadp-gallery-lightbox-prev',
					'aria-label': galleryLightboxLabels.galleryPrev || 'Previous image'
				}).html('&#10094;'),
				$('<button>', {
					type: 'button',
					'class': 'aveniadp-gallery-lightbox-nav aveniadp-gallery-lightbox-next',
					'aria-label': galleryLightboxLabels.galleryNext || 'Next image'
				}).html('&#10095;'),
				$('<div>', { 'class': 'aveniadp-gallery-lightbox-content' }).append(
					$('<img>', { 'class': 'aveniadp-gallery-lightbox-image', alt: '' })
				),
				$('<div>', { 'class': 'aveniadp-gallery-lightbox-counter', 'aria-live': 'polite' })
			);

			$('body').append($galleryLightbox);

			$galleryLightbox.on('click', function(e) {
				if (e.target === this) {
					aveniadpCloseGalleryLightbox();
				}
			});

			$galleryLightbox.find('.aveniadp-gallery-lightbox-close').on('click', function(e) {
				e.preventDefault();
				aveniadpCloseGalleryLightbox();
			});

			$galleryLightbox.find('.aveniadp-gallery-lightbox-prev').on('click', function(e) {
				e.preventDefault();
				aveniadpStepGalleryLightbox(-1);
			});

			$galleryLightbox.find('.aveniadp-gallery-lightbox-next').on('click', function(e) {
				e.preventDefault();
				aveniadpStepGalleryLightbox(1);
			});

			$(document).on('keydown.aveniadpGalleryLightbox', function(e) {
				if (!$galleryLightbox || !$galleryLightbox.hasClass('is-open')) {
					return;
				}

				if (e.key === 'Escape') {
					e.preventDefault();
					aveniadpCloseGalleryLightbox();
				} else if (e.key === 'ArrowLeft') {
					e.preventDefault();
					aveniadpStepGalleryLightbox(-1);
				} else if (e.key === 'ArrowRight') {
					e.preventDefault();
					aveniadpStepGalleryLightbox(1);
				}
			});

			return $galleryLightbox;
		}

		function aveniadpGetGalleryLightboxImages($gallery) {
			var images = $gallery.data('aveniadpLightboxImages');

			if (!images) {
				var raw = $gallery.attr('data-aveniadp-lightbox-images');
				if (raw) {
					try {
						images = JSON.parse(raw);
					} catch (err) {
						images = [];
					}
					$gallery.data('aveniadpLightboxImages', images);
				}
			}

			return Array.isArray(images) ? images : [];
		}

		function aveniadpGetGalleryLightboxIndex($gallery) {
			var $mainImage = $gallery.find('.aveniadp-product-gallery-main-image').first();
			var imageId = $mainImage.data('image-id');
			var images = aveniadpGetGalleryLightboxImages($gallery);
			var index = 0;

			if (imageId) {
				for (var i = 0; i < images.length; i++) {
					if (String(images[i].id) === String(imageId)) {
						index = i;
						break;
					}
				}
			} else {
				var $activeThumb = $gallery.find('.aveniadp-product-gallery-thumb.is-active').first();
				if ($activeThumb.length) {
					index = $gallery.find('.aveniadp-product-gallery-thumb').index($activeThumb);
				}
			}

			return index;
		}

		function aveniadpUpdateGalleryLightboxView() {
			var $lightbox = aveniadpEnsureGalleryLightbox();
			var image = galleryLightboxImages[galleryLightboxIndex];
			var hasMultiple = galleryLightboxImages.length > 1;

			if (!image) {
				return;
			}

			$lightbox.find('.aveniadp-gallery-lightbox-image')
				.attr('src', image.src)
				.attr('alt', image.alt || '');

			$lightbox.find('.aveniadp-gallery-lightbox-counter')
				.text(hasMultiple ? (galleryLightboxIndex + 1) + ' / ' + galleryLightboxImages.length : '')
				.toggle(hasMultiple);

			$lightbox.find('.aveniadp-gallery-lightbox-prev, .aveniadp-gallery-lightbox-next')
				.toggle(hasMultiple);
		}

		function aveniadpOpenGalleryLightbox($gallery, index) {
			galleryLightboxImages = aveniadpGetGalleryLightboxImages($gallery);

			if (!galleryLightboxImages.length) {
				return;
			}

			galleryLightboxIndex = typeof index === 'number' ? index : 0;
			if (galleryLightboxIndex < 0) {
				galleryLightboxIndex = 0;
			}
			if (galleryLightboxIndex >= galleryLightboxImages.length) {
				galleryLightboxIndex = galleryLightboxImages.length - 1;
			}

			var $lightbox = aveniadpEnsureGalleryLightbox();
			aveniadpUpdateGalleryLightboxView();
			$lightbox.addClass('is-open').attr('aria-hidden', 'false');
			$('body').addClass('aveniadp-gallery-lightbox-open');
			$lightbox.find('.aveniadp-gallery-lightbox-close').trigger('focus');
		}

		function aveniadpCloseGalleryLightbox() {
			if (!$galleryLightbox) {
				return;
			}

			$galleryLightbox.removeClass('is-open').attr('aria-hidden', 'true');
			$('body').removeClass('aveniadp-gallery-lightbox-open');
			$galleryLightbox.find('.aveniadp-gallery-lightbox-image').attr('src', '');
		}

		function aveniadpStepGalleryLightbox(step) {
			if (!galleryLightboxImages.length) {
				return;
			}

			galleryLightboxIndex += step;

			if (galleryLightboxIndex < 0) {
				galleryLightboxIndex = galleryLightboxImages.length - 1;
			} else if (galleryLightboxIndex >= galleryLightboxImages.length) {
				galleryLightboxIndex = 0;
			}

			aveniadpUpdateGalleryLightboxView();
		}

		$(document).on('click', '.aveniadp-product-gallery-lightbox-trigger', function(e) {
			e.preventDefault();

			var $gallery = $(this).closest('.aveniadp-product-gallery');
			aveniadpOpenGalleryLightbox($gallery, aveniadpGetGalleryLightboxIndex($gallery));
		});

		// Product gallery thumbnails.
		$(document).on('click', '.aveniadp-product-gallery-thumb', function(e) {
			e.preventDefault();

			var $thumb = $(this);
			if ($thumb.hasClass('is-active')) {
				return;
			}

			var $gallery = $thumb.closest('.aveniadp-product-gallery');
			var $mainImage = $gallery.find('.aveniadp-product-gallery-main-image');

			if (!$mainImage.length) {
				return;
			}

			$gallery.find('.aveniadp-product-gallery-thumb')
				.removeClass('is-active')
				.attr('aria-current', 'false');

			$thumb
				.addClass('is-active')
				.attr('aria-current', 'true');

			aveniadpSwitchGalleryImage($gallery, $thumb, $mainImage);
		});

		// Option button groups (site quantity).
		$(document).on('click', '.aveniadp-option-buttons .aveniadp-option-btn', function(e) {
			e.preventDefault();

			var $btn = $(this);
			if ($btn.hasClass('is-selected')) {
				return;
			}

			var $group = $btn.closest('.aveniadp-option-group');
			var targetId = $group.data('target');

			$group.find('.aveniadp-option-btn').removeClass('is-selected').attr('aria-pressed', 'false');
			$btn.addClass('is-selected').attr('aria-pressed', 'true');
			$('#' + targetId).val($btn.data('value')).trigger('change');
		});

		// Update price display when selections change.
		function aveniadpUpdateProductPrice(priceHtml) {
			$('#aveniadp-selected-price').html(priceHtml);
			$('.aveniadp-add-to-cart-price').html(priceHtml);
		}

		function aveniadpRefreshProductPrice() {
			var productId = $('input[name="product_id"]').val();
			var licenseQuantity = $('#aveniadp_license_quantity').val();
			if (licenseQuantity === '' || licenseQuantity === null || typeof licenseQuantity === 'undefined') {
				licenseQuantity = 1;
			}
			if (!productId) {
				return;
			}

			aveniadpPublicAjax({
				url: aveniadpPublic.ajaxUrl,
				type: 'POST',
				data: {
					action: 'aveniadp_get_price',
					product_id: productId,
					license_quantity: licenseQuantity
				},
				success: function(response) {
					if (response.success) {
						aveniadpUpdateProductPrice(response.data.price);
					}
				}
			});
		}

		$('#aveniadp_license_quantity').on('change', function() {
			aveniadpRefreshProductPrice();
		});

		if ($('#aveniadp_license_quantity').length) {
			aveniadpEnsureBootstrap().always(function() {
				aveniadpRefreshProductPrice();
			});
		}

		// Free catalog download (no cart/checkout).
		function aveniadpOpenFreeDownloadModal(productId) {
			var $modal = $('#aveniadp-free-download-modal');
			if (!$modal.length) {
				return false;
			}

			$modal.data('product-id', productId);
			$modal.find('.aveniadp-free-download-modal-form-wrap').prop('hidden', false).show();
			$modal.find('.aveniadp-free-download-modal-success').prop('hidden', true).hide();
			$modal.find('.aveniadp-free-download-modal-message').prop('hidden', true).hide().text('');
			$modal.find('#aveniadp-free-download-email').val('').prop('disabled', false);
			$modal.find('.aveniadp-free-download-submit').prop('disabled', false);
			$modal.removeAttr('hidden').attr('aria-hidden', 'false').addClass('is-open');
			$('body').addClass('aveniadp-free-download-modal-open');
			$modal.find('#aveniadp-free-download-email').trigger('focus');
			return true;
		}

		function aveniadpCloseFreeDownloadModal() {
			var $modal = $('#aveniadp-free-download-modal');
			if (!$modal.length) {
				return;
			}

			$modal.removeClass('is-open').attr('aria-hidden', 'true').attr('hidden', 'hidden');
			$('body').removeClass('aveniadp-free-download-modal-open');
		}

		function aveniadpSubmitFreeDownload(productId, email, $btn, $messageEl) {
			if ($btn && $btn.length) {
				$btn.prop('disabled', true);
			}

			aveniadpPublicAjax({
				url: aveniadpPublic.ajaxUrl,
				type: 'POST',
				data: {
					action: 'aveniadp_request_free_download',
					product_id: productId,
					email: email || ''
				},
				success: function(response) {
					if (response.success && response.data && response.data.redirect) {
						window.location.href = response.data.redirect;
						return;
					}

					if (response.success && response.data && response.data.gated) {
						var successMessage = response.data.message || '';
						var $modal = $('#aveniadp-free-download-modal');

						if ($modal.length && $modal.hasClass('is-open')) {
							$modal.find('.aveniadp-free-download-modal-form-wrap').hide().prop('hidden', true);
							$modal.find('.aveniadp-free-download-modal-success').show().prop('hidden', false);
							$modal.find('.aveniadp-free-download-modal-success-message').text(successMessage);
							$modal.find('.aveniadp-free-download-account-link').prop('hidden', !aveniadpPublic.isLoggedIn);
						} else if ($messageEl && $messageEl.length) {
							aveniadpShowInlineNotice($messageEl, successMessage, 'success');
						}

						if ($btn && $btn.length) {
							$btn.prop('disabled', false);
						}
						return;
					}

					if ($btn && $btn.length) {
						$btn.prop('disabled', false);
					}
					var msg = (response.data && response.data.message) ? response.data.message : (aveniadpPublic.checkoutError || 'Something went wrong.');
					if ($messageEl && $messageEl.length) {
						aveniadpShowInlineNotice($messageEl, msg, 'error');
					}
				},
				error: function() {
					if ($btn && $btn.length) {
						$btn.prop('disabled', false);
					}
					var errorMessage = aveniadpPublic.checkoutError || 'Something went wrong.';
					if ($messageEl && $messageEl.length) {
						aveniadpShowInlineNotice($messageEl, errorMessage, 'error');
					}
				}
			});
		}

		$(document).on('click', '.aveniadp-free-download', function(e) {
			e.preventDefault();
			var $btn = $(this);
			var productId = $btn.data('product-id');
			var $messageEl = $btn.closest('.aveniadp-product-purchase').find('.aveniadp-free-download-message');

			aveniadpEnsureBootstrap().always(function() {
				var requireEmail = !!(window.aveniadpPublic && aveniadpPublic.freeDownloadRequireEmail);
				var isLoggedIn = !!(window.aveniadpPublic && aveniadpPublic.isLoggedIn);

				if (requireEmail && !isLoggedIn) {
					if (aveniadpOpenFreeDownloadModal(productId)) {
						return;
					}
				}

				var email = isLoggedIn ? (aveniadpPublic.userEmail || '') : '';
				aveniadpSubmitFreeDownload(productId, email, $btn, $messageEl);
			});
		});

		$(document).on('submit', '#aveniadp-free-download-form', function(e) {
			e.preventDefault();

			var $form = $(this);
			var $modal = $('#aveniadp-free-download-modal');
			var productId = $modal.data('product-id');
			var $emailInput = $form.find('#aveniadp-free-download-email');
			var email = $.trim($emailInput.val());
			var $messageEl = $form.find('.aveniadp-free-download-modal-message');
			var labels = (window.aveniadpPublic && aveniadpPublic.labels) ? aveniadpPublic.labels : {};

			if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
				aveniadpShowInlineNotice($messageEl, labels.freeDownloadInvalidEmail || 'Please enter a valid email address.', 'error');
				$emailInput.trigger('focus');
				return;
			}

			aveniadpSubmitFreeDownload(productId, email, $form.find('.aveniadp-free-download-submit'), $messageEl);
		});

		$(document).on('click', '[data-aveniadp-close-free-download-modal]', function(e) {
			e.preventDefault();
			aveniadpCloseFreeDownloadModal();
		});

		$(document).on('keydown', function(e) {
			if (e.key === 'Escape' && $('#aveniadp-free-download-modal').hasClass('is-open')) {
				aveniadpCloseFreeDownloadModal();
			}
			if (e.key === 'Escape' && $('#aveniadp-cart-drawer').hasClass('is-open')) {
				aveniadpCloseCartDrawer();
			}
		});

		function aveniadpUpdateCartIconCount(count) {
			var safeCount = parseInt(count, 10);
			if (isNaN(safeCount) || safeCount < 0) {
				safeCount = 0;
			}

			$('[data-aveniadp-cart-icon-count]').each(function() {
				var $badge = $(this);
				var hideWhenEmpty = $badge.closest('[data-aveniadp-hide-count-when-empty="1"]').length > 0;

				$badge.text(safeCount);

				if (hideWhenEmpty && safeCount === 0) {
					$badge.prop('hidden', true);
				} else {
					$badge.prop('hidden', false);
				}
			});
		}

		function aveniadpGetCartDrawerCountLabel(count) {
			var labels = aveniadpPublic.labels || {};

			if (!count) {
				return labels.cartDrawerTitle || 'Your cart';
			}

			var template = count === 1
				? (labels.cartDrawerCountSingular || '%d item in cart')
				: (labels.cartDrawerCountPlural || '%d items in cart');

			return template.replace('%d', count);
		}

		function aveniadpRenderCartDrawer(drawer) {
			if (!drawer) {
				return;
			}

			var $drawer = $('#aveniadp-cart-drawer');
			if (!$drawer.length) {
				return;
			}

			var labels = aveniadpPublic.labels || {};
			var count = drawer.count || 0;
			var items = drawer.items || [];
			var totals = drawer.totals || {};

			$('[data-aveniadp-cart-drawer-count-label]').text(aveniadpGetCartDrawerCountLabel(count));
			aveniadpUpdateCartIconCount(count);

			var $body = $('[data-aveniadp-cart-drawer-body]');
			$body.empty();

			items.forEach(function(item) {
				var $article = $('<article>', {
					class: 'aveniadp-cart-drawer-item',
					'data-product-id': item.product_id
				});

				if (item.thumbnail) {
					var $imageLink = $('<a>', {
						class: 'aveniadp-cart-drawer-item-image',
						href: item.product_url
					});
					$imageLink.append($('<img>', {
						src: item.thumbnail,
						alt: '',
						loading: 'lazy',
						decoding: 'async'
					}));
					$article.append($imageLink);
				}

				var $details = $('<div>', { class: 'aveniadp-cart-drawer-item-details' });
				var $head = $('<div>', { class: 'aveniadp-cart-drawer-item-head' });
				$head.append($('<a>', {
					class: 'aveniadp-cart-drawer-item-title',
					href: item.product_url,
					text: item.title
				}));
				$head.append($('<span>', { class: 'aveniadp-cart-drawer-item-price' }).html(item.price_html));
				$details.append($head);

				if (item.details) {
					$details.append($('<p>', {
						class: 'aveniadp-cart-drawer-item-meta',
						text: item.details
					}));
				}

				var $actions = $('<div>', { class: 'aveniadp-cart-drawer-item-actions' });
				if (item.edit_url) {
					$actions.append($('<a>', {
						class: 'aveniadp-cart-drawer-item-edit',
						href: item.edit_url,
						text: labels.cartDrawerChangeOptions || 'Change options'
					}));
				}
				$actions.append($('<button>', {
					type: 'button',
					class: 'aveniadp-cart-drawer-item-remove aveniadp-remove-from-cart',
					'data-product-id': item.product_id,
					'aria-label': labels.cartDrawerRemove || 'Remove item'
				}).append($('<span>', { 'aria-hidden': 'true', html: '&times;' })));

				$details.append($actions);
				$article.append($details);
				$body.append($article);
			});

			$('[data-aveniadp-cart-drawer-footer]').prop('hidden', !count);
			$('[data-aveniadp-cart-drawer-empty]').prop('hidden', !!count);

			aveniadpRenderCartDrawerDiscount(totals);
			aveniadpRenderCartDrawerTotals(totals);
			aveniadpSyncPurchaseFormFromCart(aveniadpCartItemsFromDrawer(drawer));
		}

		function aveniadpRenderCartDrawerTotals(totals) {
			totals = totals || {};
			var labels = aveniadpPublic.labels || {};

			$('[data-aveniadp-cart-drawer-subtotal]').text(totals.subtotal || '');
			$('[data-aveniadp-cart-drawer-total]').text(totals.total || '');
			$('[data-aveniadp-cart-drawer-total-label]').text(labels.total || 'Total');

			var $discountRow = $('[data-aveniadp-cart-drawer-discount-row]');
			if (totals.has_discount) {
				var discountLabel = (labels.discount || 'Discount') + ' (' + totals.discount_code + ')';
				$('[data-aveniadp-cart-drawer-discount-label]').text(discountLabel);
				$('[data-aveniadp-cart-drawer-discount-amount]').html('&minus;' + totals.discount_amount);
				$discountRow.prop('hidden', false);
			} else {
				$discountRow.prop('hidden', true);
			}

			var $taxRow = $('[data-aveniadp-cart-drawer-tax-row]');
			if (totals.has_tax) {
				$('[data-aveniadp-cart-drawer-tax-label]').text(totals.tax_label || labels.tax || 'Tax');
				$('[data-aveniadp-cart-drawer-tax-amount]').text(totals.tax_amount);
				$taxRow.prop('hidden', false);
			} else {
				$taxRow.prop('hidden', true);
			}

			var $taxNote = $('[data-aveniadp-cart-drawer-tax-note]');
			if (totals.show_tax_inclusive_note) {
				$taxNote.prop('hidden', false);
			} else {
				$taxNote.prop('hidden', true);
			}
		}

		function aveniadpRenderCartDrawerDiscount(totals) {
			var $wrap = $('[data-aveniadp-cart-drawer-discount]');
			if (!$wrap.length) {
				return;
			}

			totals = totals || {};
			var labels = aveniadpPublic.labels || {};
			var removeLabel = labels.removeDiscount || 'Remove';
			var appliedLabel = labels.discountApplied || 'Discount applied:';

			if (totals.has_discount) {
				$wrap.html(
					'<p class="aveniadp-applied-discount aveniadp-notice is-success">' +
					appliedLabel + ' <strong></strong> ' +
					'<a href="#" class="aveniadp-cart-item-edit aveniadp-remove-discount">' + removeLabel + '</a>' +
					'</p>'
				);
				$wrap.find('strong').text(totals.discount_code);
				return;
			}

			var applyLabel = labels.apply || 'Apply';
			var codeLabel = labels.discountCode || 'Discount Code';
			var toggleLabel = labels.haveDiscountCode || 'Have a discount code?';

			$wrap.html(
				'<details class="aveniadp-cart-drawer-discount-details">' +
				'<summary class="aveniadp-cart-drawer-discount-toggle">' + toggleLabel + '</summary>' +
				'<div class="aveniadp-discount-form aveniadp-cart-drawer-slide">' +
				'<div class="aveniadp-cart-drawer-slide-inner">' +
				'<label class="screen-reader-text" for="aveniadp_drawer_discount_code">' + codeLabel + '</label>' +
				'<div class="aveniadp-discount-form-row">' +
				'<input type="text" id="aveniadp_drawer_discount_code" name="discount_code" autocomplete="off" />' +
				'<button type="button" class="button aveniadp-option-btn aveniadp-apply-discount">' + applyLabel + '</button>' +
				'</div>' +
				'<p class="aveniadp-notice aveniadp-discount-message" aria-live="polite" hidden></p>' +
				'</div>' +
				'</div>' +
				'</details>'
			);
		}

		var aveniadpCartDrawerCloseTimer = null;

		function aveniadpOpenCartDrawer() {
			var $drawer = $('#aveniadp-cart-drawer');
			if (!$drawer.length) {
				return;
			}

			clearTimeout(aveniadpCartDrawerCloseTimer);
			$drawer.find('.aveniadp-cart-drawer-panel').off('transitionend.aveniadpCartDrawer');

			$drawer.removeAttr('hidden').attr('aria-hidden', 'false');
			$('body').addClass('aveniadp-cart-drawer-open');

			requestAnimationFrame(function() {
				requestAnimationFrame(function() {
					$drawer.addClass('is-open');
					$drawer.find('.aveniadp-cart-drawer-panel').trigger('focus');
				});
			});
		}

		function aveniadpCloseCartDrawer() {
			var $drawer = $('#aveniadp-cart-drawer');
			if (!$drawer.length || !$drawer.hasClass('is-open')) {
				return;
			}

			var $panel = $drawer.find('.aveniadp-cart-drawer-panel');

			$drawer.removeClass('is-open').attr('aria-hidden', 'true');
			$('body').removeClass('aveniadp-cart-drawer-open');

			$panel.one('transitionend.aveniadpCartDrawer', function(e) {
				if (e.originalEvent && e.originalEvent.propertyName !== 'transform') {
					return;
				}

				if (!$drawer.hasClass('is-open')) {
					$drawer.attr('hidden', 'hidden');
				}
			});

			clearTimeout(aveniadpCartDrawerCloseTimer);
			aveniadpCartDrawerCloseTimer = setTimeout(function() {
				if (!$drawer.hasClass('is-open')) {
					$drawer.attr('hidden', 'hidden');
				}
			}, 320);
		}

		$(document).on('click', '[data-aveniadp-close-cart-drawer]', function(e) {
			e.preventDefault();
			aveniadpCloseCartDrawer();
		});

		$(document).on('click', '.aveniadp-cart-drawer-item-edit', function() {
			aveniadpCloseCartDrawer();
		});

		var aveniadpCartDrawerSlideDuration = 400;
		var aveniadpCartDrawerSlideEaseOpen = 'cubic-bezier(0.22, 1, 0.36, 1)';
		var aveniadpCartDrawerSlideEaseClose = 'cubic-bezier(0.4, 0, 0.2, 1)';

		function aveniadpMeasureSlideInnerHeight($slide) {
			var $inner = $slide.find('.aveniadp-cart-drawer-slide-inner').first();
			var inner = $inner[0];

			if (!inner) {
				return 0;
			}

			var previousMaxHeight = $inner.css('max-height');
			var previousOverflow = $inner.css('overflow-y');

			$inner.css({ maxHeight: 'none', overflowY: 'visible' });
			var height = inner.scrollHeight;
			$inner.css({ maxHeight: previousMaxHeight, overflowY: previousOverflow });

			return height;
		}

		function aveniadpSetSlideInnerOverflow($slide) {
			$slide.find('.aveniadp-cart-drawer-slide-inner').first().css('overflowY', 'hidden');
		}

		function aveniadpGetCartDrawerSlide($details) {
			return $details.children('.aveniadp-cart-drawer-slide').first();
		}

		function aveniadpExpandCartDrawerSlide($slide) {
			var height = aveniadpMeasureSlideInnerHeight($slide);
			var transition = 'grid-template-rows ' + aveniadpCartDrawerSlideDuration + 'ms ' + aveniadpCartDrawerSlideEaseOpen;

			$slide.removeClass('is-collapsing').css({
				transition: transition,
				gridTemplateRows: '0px'
			});

			window.requestAnimationFrame(function() {
				window.requestAnimationFrame(function() {
					$slide.addClass('is-expanded').css('grid-template-rows', height + 'px');
				});
			});

			$slide.off('transitionend.aveniadpCartDrawerSlideExpand').one('transitionend.aveniadpCartDrawerSlideExpand', function(e) {
				if (e.originalEvent && e.originalEvent.propertyName !== 'grid-template-rows') {
					return;
				}

				aveniadpSetSlideInnerOverflow($slide);
			});
		}

		function aveniadpCollapseCartDrawerSlide($details, $slide) {
			var height = aveniadpMeasureSlideInnerHeight($slide);
			var $inner = $slide.find('.aveniadp-cart-drawer-slide-inner').first();
			var transition = 'grid-template-rows ' + aveniadpCartDrawerSlideDuration + 'ms ' + aveniadpCartDrawerSlideEaseClose;

			$inner.css('overflowY', 'hidden');
			$slide.addClass('is-collapsing').removeClass('is-expanded').css({
				transition: transition,
				gridTemplateRows: height + 'px'
			});

			if ($slide[0]) {
				void $slide[0].offsetHeight;
			}

			$slide.css('grid-template-rows', '0px');

			var finished = false;
			var complete = function(e) {
				if (e && e.originalEvent && e.originalEvent.propertyName !== 'grid-template-rows') {
					return;
				}
				if (finished) {
					return;
				}
				finished = true;
				$slide.off('transitionend.aveniadpCartDrawerSlide');
				$details.prop('open', false);
				$slide.removeClass('is-collapsing');
			};

			$slide.on('transitionend.aveniadpCartDrawerSlide', complete);
			window.setTimeout(complete, aveniadpCartDrawerSlideDuration + 50);
		}

		$(document).on('click', '.aveniadp-cart-drawer-discount-details > summary', function(e) {
			e.preventDefault();

			var details = this.parentNode;
			var $details = $(details);
			var $slide = aveniadpGetCartDrawerSlide($details);

			if (!$slide.length) {
				return;
			}

			if (details.open) {
				aveniadpCollapseCartDrawerSlide($details, $slide);
				return;
			}

			$details.prop('open', true);
			aveniadpExpandCartDrawerSlide($slide);
		});

		function aveniadpRefreshAndOpenCartDrawer() {
			var $drawer = $('#aveniadp-cart-drawer');
			if (!$drawer.length) {
				if (aveniadpPublic.cartUrl) {
					window.location.href = aveniadpPublic.cartUrl;
				}
				return;
			}

			aveniadpPublicAjax({
				url: aveniadpPublic.ajaxUrl,
				type: 'POST',
				data: {
					action: 'aveniadp_get_cart_drawer'
				},
				success: function(response) {
					if (response.success && response.data.drawer) {
						aveniadpRenderCartDrawer(response.data.drawer);
						aveniadpOpenCartDrawer();
					}
				}
			});
		}

		$(document).on('click', '[data-aveniadp-open-cart-drawer]', function(e) {
			e.preventDefault();
			aveniadpRefreshAndOpenCartDrawer();
		});

		// Add to cart.
		function showAddToCartMessage($button, message, isSuccess) {
			aveniadpShowInlineNotice(
				$button.closest('.aveniadp-add-to-cart-actions').find('.aveniadp-add-to-cart-message'),
				message,
				isSuccess ? 'success' : 'error',
				5000
			);
		}

		$('.aveniadp-add-to-cart').on('click', function(e) {
			e.preventDefault();

			var button = $(this);
			var productId = button.data('product-id');
			var licenseQuantity = $('#aveniadp_license_quantity').val();
			if (licenseQuantity === '' || licenseQuantity === null || typeof licenseQuantity === 'undefined') {
				licenseQuantity = 1;
			}
			var isUpdate = $('#aveniadp-purchase-form').attr('data-update-cart') === '1';

			button.prop('disabled', true);

			aveniadpPublicAjax({
				url: aveniadpPublic.ajaxUrl,
				type: 'POST',
				data: {
					action: isUpdate ? 'aveniadp_update_cart' : 'aveniadp_add_to_cart',
					product_id: productId,
					license_quantity: licenseQuantity
				},
				success: function(response) {
					button.prop('disabled', false);

					if (response.success) {
						if (response.data.redirect) {
							window.location.href = response.data.redirect;
							return;
						}

						if (typeof response.data.cart_count !== 'undefined') {
							aveniadpUpdateCartIconCount(response.data.cart_count);
						}

						if (response.data.drawer) {
							aveniadpRenderCartDrawer(response.data.drawer);
							if (response.data.open_drawer) {
								aveniadpOpenCartDrawer();
							}
							return;
						}

						showAddToCartMessage(button, response.data.message, true);
					} else {
						showAddToCartMessage(button, response.data.message, false);
					}
				},
				error: function() {
					button.prop('disabled', false);
					showAddToCartMessage(button, aveniadpPublic.checkoutError || 'Something went wrong.', false);
				}
			});
		});

		$('#aveniadp-purchase-form').on('submit', function(e) {
			e.preventDefault();
			$(this).find('.aveniadp-add-to-cart').trigger('click');
		});

		// Remove from cart.
		$(document).on('click', '.aveniadp-remove-from-cart', function(e) {
			e.preventDefault();

			var button = $(this);
			var productId = button.data('product-id');

			aveniadpPublicAjax({
				url: aveniadpPublic.ajaxUrl,
				type: 'POST',
				data: {
					action: 'aveniadp_remove_from_cart',
					product_id: productId
				},
				success: function(response) {
					if (response.success) {
						if (typeof response.data.cart_count !== 'undefined') {
							aveniadpUpdateCartIconCount(response.data.cart_count);
						}

						if (button.closest('#aveniadp-cart-drawer').length && response.data.drawer) {
							aveniadpRenderCartDrawer(response.data.drawer);
							return;
						}

						location.reload();
					}
				}
			});
		});

		function showDiscountMessage($root, message, isSuccess) {
			var $message = $root && $root.length
				? $root.find('.aveniadp-discount-message').first()
				: $('.aveniadp-discount-message').first();
			aveniadpShowInlineNotice($message, message, isSuccess ? 'success' : 'error');
		}

		function renderCartTotals(totals) {
			var labels = aveniadpPublic.labels || {};
			var html = '<div class="aveniadp-cart-totals-row">' +
				'<span>' + (labels.subtotal || 'Subtotal') + '</span>' +
				'<span class="aveniadp-cart-subtotal">' + totals.subtotal + '</span>' +
				'</div>';

			if (totals.has_discount) {
				html += '<div class="aveniadp-cart-totals-row aveniadp-cart-discount-row">' +
					'<span>' + (labels.discount || 'Discount') + ' (' + totals.discount_code + ')</span>' +
					'<span class="aveniadp-cart-discount-amount">&minus;' + totals.discount_amount + '</span>' +
					'</div>';
			}

			if (totals.has_tax) {
				html += '<div class="aveniadp-cart-totals-row aveniadp-cart-tax-row">' +
					'<span>' + (totals.tax_label || labels.tax || 'Tax') + '</span>' +
					'<span class="aveniadp-cart-tax-amount">' + totals.tax_amount + '</span>' +
					'</div>';
			}

			if (totals.show_tax_inclusive_note) {
				html += '<p class="description aveniadp-cart-tax-inclusive-note">' + (labels.pricesIncludeTax || 'Prices include tax.') + '</p>';
			}

			html += '<div class="aveniadp-cart-totals-row aveniadp-cart-total-row">' +
				'<strong>' + (labels.total || 'Total') + '</strong>' +
				'<strong class="aveniadp-cart-total">' + totals.total + '</strong>' +
				'</div>';

			$('#aveniadp-cart-totals').html(html);
			$('.aveniadp-proceed-checkout .aveniadp-add-to-cart-price').text(totals.total);
		}

		function renderAppliedDiscount(totals) {
			var removeLabel = aveniadpPublic.labels.removeDiscount || 'Remove';
			var appliedLabel = aveniadpPublic.labels.discountApplied || 'Discount applied:';

			if (totals.has_discount) {
				$('#aveniadp-cart-discount').html(
					'<p class="aveniadp-applied-discount aveniadp-notice is-success">' +
					appliedLabel + ' <strong></strong> ' +
					'<a href="#" class="aveniadp-cart-item-edit aveniadp-remove-discount">' + removeLabel + '</a>' +
					'</p>'
				);
				$('#aveniadp-cart-discount strong').text(totals.discount_code);
				return;
			}

			var applyLabel = aveniadpPublic.labels.apply || 'Apply';
			var codeLabel = aveniadpPublic.labels.discountCode || 'Discount Code';

			$('#aveniadp-cart-discount').html(
				'<div class="aveniadp-discount-form">' +
				'<label for="aveniadp_discount_code">' + codeLabel + '</label>' +
				'<div class="aveniadp-discount-form-row">' +
				'<input type="text" id="aveniadp_discount_code" name="discount_code" autocomplete="off" />' +
				'<button type="button" class="button aveniadp-option-btn aveniadp-apply-discount">' + applyLabel + '</button>' +
				'</div>' +
				'<p class="aveniadp-notice aveniadp-discount-message" aria-live="polite" hidden></p>' +
				'</div>'
			);
		}

		function getDiscountCodeInput($button) {
			var $scope = $button.closest('.aveniadp-discount-form, #aveniadp-cart-discount, [data-aveniadp-cart-drawer-discount]');
			var $input = $scope.find('input[name="discount_code"]').first();
			if ($input.length) {
				return $input;
			}
			return $('#aveniadp_discount_code, #aveniadp_drawer_discount_code').first();
		}

		function applyDiscountResponse(response, $root) {
			if (!response.success) {
				var msg = (response.data && response.data.message) ? response.data.message : (aveniadpPublic.checkoutError || 'Something went wrong.');
				showDiscountMessage($root, msg, false);
				return;
			}

			if (response.data && response.data.drawer) {
				aveniadpRenderCartDrawer(response.data.drawer);
			}

			if (response.data && response.data.totals && $('#aveniadp-cart-totals').length) {
				renderCartTotals(response.data.totals);
				renderAppliedDiscount(response.data.totals);
			}
		}

		$(document).on('click', '.aveniadp-apply-discount', function(e) {
			e.preventDefault();

			var $button = $(this);
			var $root = $button.closest('.aveniadp-discount-form, #aveniadp-cart-discount, [data-aveniadp-cart-drawer-discount]');
			var code = getDiscountCodeInput($button).val();

			$button.prop('disabled', true);

			aveniadpPublicAjax({
				url: aveniadpPublic.ajaxUrl,
				type: 'POST',
				data: {
					action: 'aveniadp_apply_discount',
					discount_code: code
				},
				success: function(response) {
					$button.prop('disabled', false);
					applyDiscountResponse(response, $root);
				},
				error: function() {
					$button.prop('disabled', false);
					showDiscountMessage($root, aveniadpPublic.checkoutError || 'Something went wrong.', false);
				}
			});
		});

		$(document).on('keydown', '#aveniadp_discount_code, #aveniadp_drawer_discount_code', function(e) {
			if (e.key !== 'Enter') {
				return;
			}
			e.preventDefault();
			$(this).closest('.aveniadp-discount-form').find('.aveniadp-apply-discount').trigger('click');
		});

		$(document).on('click', '.aveniadp-remove-discount', function(e) {
			e.preventDefault();

			var $link = $(this);
			var $root = $link.closest('#aveniadp-cart-discount, [data-aveniadp-cart-drawer-discount]');
			if ($link.attr('aria-disabled') === 'true') {
				return;
			}
			$link.attr('aria-disabled', 'true');

			aveniadpPublicAjax({
				url: aveniadpPublic.ajaxUrl,
				type: 'POST',
				data: {
					action: 'aveniadp_remove_discount'
				},
				success: function(response) {
					$link.attr('aria-disabled', 'false');

					if (response.success && response.data && response.data.drawer) {
						aveniadpRenderCartDrawer(response.data.drawer);
					}

					if (response.success && response.data && response.data.totals) {
						if ($('#aveniadp-cart-totals').length) {
							renderCartTotals(response.data.totals);
							renderAppliedDiscount(response.data.totals);
						}
					}
				},
				error: function() {
					$link.attr('aria-disabled', 'false');
					aveniadpShowToastNotice(aveniadpPublic.checkoutError || 'Something went wrong.', 'error');
				}
			});
		});

		// Product info accordion sections.
		$(document).on('click', '.aveniadp-accordion-trigger', function() {
			var $trigger = $(this);
			var expanded = $trigger.attr('aria-expanded') === 'true';
			var $panel = $('#' + $trigger.attr('aria-controls'));
			var willExpand = !expanded;

			$trigger.attr('aria-expanded', willExpand ? 'true' : 'false');
			$trigger.find('.aveniadp-accordion-icon').text('+');
			$panel.toggleClass('is-open', willExpand);
			$panel.attr('aria-hidden', willExpand ? 'false' : 'true');
		});

		// Account tabs.
		$('.aveniadp-tab-link').on('click', function(e) {
			e.preventDefault();
			var target = $(this).attr('href');
			$('.aveniadp-tab-link').removeClass('active').attr('aria-selected', 'false');
			$('.aveniadp-tab-content').removeClass('active');
			$(this).addClass('active').attr('aria-selected', 'true');
			$(target).addClass('active');
		});

		// Checkout form: submit via AJAX and handle redirect or thank-you.
		$('#aveniadp-checkout-form').on('submit', function(e) {
			e.preventDefault();
			var $form = $(this);
			var $btn = $form.find('button[type="submit"]');
			var originalText = $btn.text();
			var $emailError = $('#aveniadp-email-error');
			var $emailConfirmError = $('#aveniadp-email-confirm-error');

			$emailError.attr('hidden', 'hidden').text('');
			$emailConfirmError.attr('hidden', 'hidden').text('');
			$('#aveniadp-checkout-notice').attr('hidden', 'hidden').text('');

			var email = $.trim($form.find('#aveniadp_email').val());
			var emailConfirm = $.trim($form.find('#aveniadp_email_confirm').val());
			var hasEmailConfirm = $form.find('#aveniadp_email_confirm').length > 0;

			if (hasEmailConfirm) {
				if (!email) {
					$emailError.text(aveniadpPublic.emailRequired || 'Email is required.').removeAttr('hidden');
					return;
				}
				if (!emailConfirm) {
					$emailConfirmError.text(aveniadpPublic.emailConfirmRequired || 'Please confirm your email address.').removeAttr('hidden');
					return;
				}
				if (email.toLowerCase() !== emailConfirm.toLowerCase()) {
					$emailConfirmError.text(aveniadpPublic.emailMismatch || 'Email addresses do not match.').removeAttr('hidden');
					return;
				}
			}

			$btn.prop('disabled', true).text(aveniadpPublic.checkoutProcessing || 'Processing...');

			aveniadpPublicAjax({
				url: aveniadpPublic.ajaxUrl,
				type: 'POST',
				data: {
					action: 'aveniadp_process_checkout',
					name: $form.find('#aveniadp_name').val(),
					email: email,
					email_confirm: emailConfirm,
					payment_method: $form.find('#aveniadp_payment_method').length
						? $form.find('#aveniadp_payment_method').val()
						: $form.find('input[name="payment_method"]').val()
				},
				success: function(response) {
					if (response.success && response.data) {
						if (response.data.redirect) {
							window.location.href = response.data.redirect;
							return;
						}
						if (response.data.thank_you_url) {
							window.location.href = response.data.thank_you_url;
							return;
						}
					}
					$btn.prop('disabled', false).text(originalText);
					if (response.data && response.data.code === 'invalid_email') {
						$emailError.text(response.data.message).removeAttr('hidden');
						return;
					}
					if (response.data && (response.data.code === 'email_mismatch' || response.data.code === 'invalid_email_confirm')) {
						$emailConfirmError.text(response.data.message).removeAttr('hidden');
						return;
					}
					if (response.data && response.data.message) {
						aveniadpShowInlineNotice($('#aveniadp-checkout-notice'), response.data.message, 'error');
					} else if (!response.success && response.data && response.data.message) {
						aveniadpShowInlineNotice($('#aveniadp-checkout-notice'), response.data.message, 'error');
					}
				},
				error: function() {
					$btn.prop('disabled', false).text(originalText);
					aveniadpShowInlineNotice($('#aveniadp-checkout-notice'), aveniadpPublic.checkoutError || 'Something went wrong. Please try again.', 'error');
				}
			});
		});
	});

})(jQuery);
