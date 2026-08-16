/**
 * Admin JavaScript.
 *
 * @package AveniaDigitalProducts
 * @since 1.0.0
 */

(function($) {
	'use strict';

	$(document).ready(function() {
		if ($.fn.wpColorPicker) {
			$('.aveniadp-color-picker').wpColorPicker();
		}

		// File upload handler.
		$(document).on('click', '.aveniadp-upload-file-button', function(e) {
			e.preventDefault();

			var fileInput = $('#aveniadp_file_id');

			var fileFrame = wp.media({
				title: 'Select File',
				button: {
					text: 'Use this file'
				},
				multiple: false
			});

			fileFrame.on('open', function() {
				var bindProtectedUpload = function(uploader) {
					if (!uploader || !uploader.uploader) {
						return;
					}

					uploader.uploader.bind('BeforeUpload', function(up) {
						up.settings.multipart_params = up.settings.multipart_params || {};
						up.settings.multipart_params.aveniadp_protected_upload = '1';
					});
				};

				bindProtectedUpload(fileFrame.uploader);

				fileFrame.on('uploader:ready', function(uploader) {
					bindProtectedUpload(uploader);
				});
			});

			fileFrame.on('select', function() {
				var attachment = fileFrame.state().get('selection').first().toJSON();
				fileInput.val(attachment.id);
				$('#aveniadp-file-preview').html('<p><strong>' + attachment.filename + '</strong></p>');
				$('.aveniadp-remove-file-button').show();
			});

			fileFrame.open();
		});

		// Email logo upload handler.
		$(document).on('click', '.aveniadp-upload-email-logo-button', function(e) {
			e.preventDefault();

			var fileFrame = wp.media({
				title: 'Select Email Logo',
				button: {
					text: 'Use this logo'
				},
				library: {
					type: 'image'
				},
				multiple: false
			});

			fileFrame.on('select', function() {
				var attachment = fileFrame.state().get('selection').first().toJSON();
				$('#aveniadp_email_logo').val(attachment.id);
				$('#aveniadp-email-logo-preview').html('<img src="' + attachment.url + '" alt="" style="max-width:160px;height:auto;" />');
				$('.aveniadp-remove-email-logo-button').show();
			});

			fileFrame.open();
		});

		$(document).on('click', '.aveniadp-remove-email-logo-button', function(e) {
			e.preventDefault();
			$('#aveniadp_email_logo').val('');
			$('#aveniadp-email-logo-preview').html('');
			$(this).hide();
		});

		// Sync TinyMCE editors before saving email settings.
		$('form').has('#aveniadp_email_order_confirmation_message').on('submit', function() {
			if (typeof window.tinymce !== 'undefined') {
				window.tinymce.triggerSave();
			}
		});

		// Send test email from settings.
		$('.aveniadp-send-test-email').on('click', function(e) {
			e.preventDefault();

			if (typeof aveniadpAdmin === 'undefined' || !aveniadpAdmin.ajaxUrl || !aveniadpAdmin.nonce) {
				return;
			}

			var $button = $(this);
			var emailType = $button.data('email-type');
			var subjectField = $button.data('subject-field');
			var messageField = $button.data('message-field');
			var $status = $button.siblings('.aveniadp-send-test-email-status');
			var i18n = aveniadpAdmin.i18n || {};

			if (typeof window.tinymce !== 'undefined' && messageField) {
				var editor = window.tinymce.get(messageField);
				if (editor && !editor.isHidden()) {
					editor.save();
				}
			}

			var subject = subjectField ? $('#' + subjectField).val() : '';
			var message = messageField ? $('[name="' + messageField + '"]').val() : '';

			$button.prop('disabled', true);
			$status.removeClass('is-error is-success').text(i18n.sendTestEmailSending || 'Sending…');

			$.ajax({
				url: aveniadpAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'aveniadp_send_test_email',
					nonce: aveniadpAdmin.nonce,
					email_type: emailType,
					subject: subject,
					message: message
				}
			}).done(function(response) {
				if (response && response.success) {
					var successMessage = (response.data && response.data.message)
						? response.data.message
						: (i18n.sendTestEmailSuccess || 'Test email sent.');
					$status.addClass('is-success').text(successMessage);
					return;
				}

				var errorMessage = (response && response.data && response.data.message)
					? response.data.message
					: (i18n.sendTestEmailFailed || 'Failed to send test email. Check that wp_mail is configured.');
				$status.addClass('is-error').text(errorMessage);
			}).fail(function() {
				$status.addClass('is-error').text(i18n.sendTestEmailFailed || 'Failed to send test email. Check that wp_mail is configured.');
			}).always(function() {
				$button.prop('disabled', false);
			});
		});

		// Remove file handler.
		$(document).on('click', '.aveniadp-remove-file-button', function(e) {
			e.preventDefault();
			$('#aveniadp_file_id').val('');
			$('#aveniadp-file-preview').html('');
			$(this).hide();
		});

		function aveniadpToggleFileSourceFields() {
			var source = $('input[name="aveniadp_file_source"]:checked').val() || 'upload';

			$('.aveniadp-file-source-upload').toggle(source === 'upload');
			$('.aveniadp-file-source-external').toggle(source === 'external');
		}

		$(document).on('change', 'input[name="aveniadp_file_source"]', aveniadpToggleFileSourceFields);
		aveniadpToggleFileSourceFields();

		/**
		 * Hide paid-product fields when free download is enabled.
		 */
		function aveniadpToggleFreeDownloadFields() {
			var isFree = $('#aveniadp_free_download').is(':checked');

			$('#aveniadp_product_pricing').toggle(!isFree);
		}

		$(document).on('change', '#aveniadp_free_download', aveniadpToggleFreeDownloadFields);
		aveniadpToggleFreeDownloadFields();

		/**
		 * Direct checkout link generator (product pricing meta box).
		 */
		function aveniadpFormatDirectLinkLicenseLabel(quantity) {
			var qty = String(quantity);
			var i18n = (window.aveniadpAdmin && aveniadpAdmin.i18n) ? aveniadpAdmin.i18n : {};

			if (qty === '0') {
				return i18n.directCheckoutUnlimitedSites || 'Unlimited sites';
			}
			if (qty === '1') {
				return (i18n.directCheckoutSite || '%s site').replace('%s', '1');
			}
			return (i18n.directCheckoutSites || '%s sites').replace('%s', qty);
		}

		function aveniadpRefreshDirectLinkLicenseOptions() {
			var $select = $('#aveniadp_direct_link_license');
			if (!$select.length) {
				return;
			}

			var current = $select.val();
			$select.empty();

			$('input[name="pricing_quantity[]"]').each(function() {
				var quantity = $(this).val();
				if (quantity === '' || quantity === null || typeof quantity === 'undefined') {
					return;
				}

				$select.append(
					$('<option>', {
						value: quantity,
						text: aveniadpFormatDirectLinkLicenseLabel(quantity)
					})
				);
			});

			if (current && $select.find('option[value="' + current + '"]').length) {
				$select.val(current);
			}
		}

		function aveniadpBuildDirectCheckoutUrl() {
			var $container = $('#aveniadp-direct-checkout-link');
			if (!$container.length) {
				return;
			}

			var checkoutUrl = $container.attr('data-checkout-url') || (window.aveniadpAdmin && aveniadpAdmin.checkoutUrl) || '';
			var productId = $container.data('product-id');
			if (!checkoutUrl || !productId) {
				return;
			}

			var params = {
				aveniadp_buy: productId
			};

			var $license = $('#aveniadp_direct_link_license');
			if ($license.length) {
				params.license_quantity = $license.val();
			} else {
				var firstQuantity = $('input[name="pricing_quantity[]"]').first().val();
				if (firstQuantity !== '' && firstQuantity !== null && typeof firstQuantity !== 'undefined') {
					params.license_quantity = firstQuantity;
				}
			}

			var url = checkoutUrl;
			var query = $.param(params);
			url += (url.indexOf('?') === -1 ? '?' : '&') + query;

			$('#aveniadp_direct_checkout_url').val(url);
		}

		function aveniadpCopyDirectCheckoutUrl() {
			var $input = $('#aveniadp_direct_checkout_url');
			var $copied = $('.aveniadp-direct-checkout-link-copied');
			var message = (window.aveniadpAdmin && aveniadpAdmin.i18n && aveniadpAdmin.i18n.directCheckoutLinkCopied)
				? aveniadpAdmin.i18n.directCheckoutLinkCopied
				: 'Link copied to clipboard.';

			if (!$input.length || !$input.val()) {
				return;
			}

			var value = $input.val();

			function showCopied() {
				$copied.text(message).prop('hidden', false);
				window.setTimeout(function() {
					$copied.prop('hidden', true).text('');
				}, 2500);
			}

			if (navigator.clipboard && navigator.clipboard.writeText) {
				navigator.clipboard.writeText(value).then(showCopied).catch(function() {
					$input.trigger('focus').trigger('select');
					document.execCommand('copy');
					showCopied();
				});
				return;
			}

			$input.trigger('focus').trigger('select');
			document.execCommand('copy');
			showCopied();
		}

		$(document).on('change', '#aveniadp_direct_link_license', aveniadpBuildDirectCheckoutUrl);
		$(document).on('click', '.aveniadp-copy-direct-checkout-url', aveniadpCopyDirectCheckoutUrl);
		$(document).on('input change', 'input[name="pricing_quantity[]"]', function() {
			aveniadpRefreshDirectLinkLicenseOptions();
			aveniadpBuildDirectCheckoutUrl();
		});

		aveniadpBuildDirectCheckoutUrl();

		/**
		 * Get the next unused license quantity for a new pricing row.
		 */
		function aveniadpGetNextPricingQuantity() {
			var max = 0;
			$('.aveniadp-pricing-row input[name="pricing_quantity[]"]').each(function() {
				var val = parseInt($(this).val(), 10);
				if (!isNaN(val) && val > max) {
					max = val;
				}
			});
			return Math.max(1, max + 1);
		}

		/**
		 * Clone the first pricing row with cleared price fields.
		 *
		 * @param {number} quantity License quantity for the new row.
		 * @return {jQuery|null}
		 */
		function aveniadpCreatePricingRow(quantity) {
			var $template = $('.aveniadp-pricing-row').first();
			if (!$template.length) {
				return null;
			}

			var $row = $template.clone();
			$row.find('input[name="pricing_quantity[]"]').val(quantity);
			$row.find('input[name="pricing_one_time[]"]').val('0');
			return $row;
		}

		$(document).on('click', '.aveniadp-add-pricing-row', function(e) {
			e.preventDefault();

			var $row = aveniadpCreatePricingRow(aveniadpGetNextPricingQuantity());
			if (!$row) {
				return;
			}

			$('#aveniadp-pricing-rows').append($row);
			aveniadpSyncPricingMatrix();
		});

		$(document).on('click', '.aveniadp-remove-pricing-row', function(e) {
			e.preventDefault();

			if ($('.aveniadp-pricing-row').length <= 1) {
				return;
			}

			$(this).closest('.aveniadp-pricing-row').remove();
			aveniadpSyncPricingMatrix();
		});

		/**
		 * Sync pricing table inputs into the hidden JSON field before save.
		 */
		function aveniadpSyncPricingMatrix() {
			if (!$('#aveniadp_pricing_matrix').length) {
				return;
			}

			var matrix = {};

			$('.aveniadp-pricing-row').each(function() {
				var quantity = $(this).find('input[name="pricing_quantity[]"]').val();
				if (quantity === '' || quantity === null || typeof quantity === 'undefined') {
					return;
				}

				matrix[quantity] = {
					'one-time': $(this).find('input[name="pricing_one_time[]"]').val()
				};
			});

			$('#aveniadp_pricing_matrix').val(JSON.stringify(matrix));

			aveniadpRefreshDirectLinkLicenseOptions();
			aveniadpBuildDirectCheckoutUrl();
		}

		$('form#post').on('submit', aveniadpSyncPricingMatrix);

		/**
		 * Product gallery (admin meta box).
		 */
		function aveniadpSyncProductGallery() {
			var ids = [];
			$('#aveniadp-product-gallery-images .aveniadp-product-gallery-image').each(function() {
				var id = parseInt($(this).data('attachment-id'), 10);
				if (!isNaN(id) && id > 0) {
					ids.push(id);
				}
			});
			$('#aveniadp_product_gallery').val(ids.join(','));
		}

		function aveniadpRenderGalleryImage(attachment) {
			var thumbUrl = attachment.sizes && attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url;
			var removeLabel = (window.aveniadpAdmin && aveniadpAdmin.i18n && aveniadpAdmin.i18n.removeImage) ? aveniadpAdmin.i18n.removeImage : 'Remove image';

			return $('<li>', {
				'class': 'aveniadp-product-gallery-image',
				'data-attachment-id': attachment.id
			}).append(
				$('<img>', { src: thumbUrl, alt: '' }),
				$('<button>', {
					type: 'button',
					'class': 'aveniadp-remove-gallery-image',
					'aria-label': removeLabel,
					text: '\u00d7'
				})
			);
		}

		if ($('#aveniadp-product-gallery-images').length) {
			$('#aveniadp-product-gallery-images').sortable({
				items: '.aveniadp-product-gallery-image',
				cursor: 'move',
				stop: aveniadpSyncProductGallery
			});

			$('.aveniadp-add-gallery-images').on('click', function(e) {
				e.preventDefault();

				var frame = wp.media({
					title: (window.aveniadpAdmin && aveniadpAdmin.i18n && aveniadpAdmin.i18n.galleryTitle) ? aveniadpAdmin.i18n.galleryTitle : 'Add images to gallery',
					button: {
						text: (window.aveniadpAdmin && aveniadpAdmin.i18n && aveniadpAdmin.i18n.galleryButton) ? aveniadpAdmin.i18n.galleryButton : 'Add to gallery'
					},
					library: {
						type: 'image'
					},
					multiple: true
				});

				frame.on('select', function() {
					var selection = frame.state().get('selection');
					var existing = ($('#aveniadp_product_gallery').val() || '').split(',').filter(Boolean).map(function(id) {
						return parseInt(id, 10);
					});

					selection.each(function(attachment) {
						attachment = attachment.toJSON();
						if (existing.indexOf(attachment.id) !== -1) {
							return;
						}
						existing.push(attachment.id);
						$('#aveniadp-product-gallery-images').append(aveniadpRenderGalleryImage(attachment));
					});

					aveniadpSyncProductGallery();
				});

				frame.open();
			});

			$(document).on('click', '.aveniadp-remove-gallery-image', function(e) {
				e.preventDefault();
				$(this).closest('.aveniadp-product-gallery-image').remove();
				aveniadpSyncProductGallery();
			});
		}

		$('form#post').on('submit', aveniadpSyncProductGallery);

		/**
		 * Related products (admin meta box).
		 */
		function aveniadpGetRelatedProductsMax() {
			return (window.aveniadpAdmin && aveniadpAdmin.relatedMax) ? parseInt(aveniadpAdmin.relatedMax, 10) : 6;
		}

		function aveniadpParseRelatedProductIds(value) {
			return (value || '').split(',').filter(Boolean).map(function(id) {
				return parseInt(id, 10);
			}).filter(function(id) {
				return !isNaN(id) && id > 0;
			});
		}

		function aveniadpUpdateRelatedProductsUI() {
			var max = aveniadpGetRelatedProductsMax();
			var ids = aveniadpParseRelatedProductIds($('#aveniadp_related_products').val());
			var countLabel = (window.aveniadpAdmin && aveniadpAdmin.i18n && aveniadpAdmin.i18n.relatedCount)
				? aveniadpAdmin.i18n.relatedCount
				: '%1$d of %2$d selected';

			$('.aveniadp-related-products-count').text(
				countLabel.replace('%1$d', ids.length).replace('%2$d', max)
			);

			$('#aveniadp-related-products-list .aveniadp-related-product-checkbox').each(function() {
				var $checkbox = $(this);
				var isChecked = $checkbox.is(':checked');
				var isDisabled = !isChecked && ids.length >= max;
				$checkbox.prop('disabled', isDisabled);
				$checkbox.closest('.aveniadp-related-product-item').toggleClass('is-disabled', isDisabled);
			});
		}

		function aveniadpSetRelatedProductIds(ids) {
			var max = aveniadpGetRelatedProductsMax();
			ids = ids.slice(0, max);
			$('#aveniadp_related_products').val(ids.join(','));
			aveniadpUpdateRelatedProductsUI();
		}

		if ($('#aveniadp-related-products-list').length) {
			aveniadpUpdateRelatedProductsUI();

			$('#aveniadp-related-products-search').on('input', function() {
				var query = ($(this).val() || '').toLowerCase().trim();
				$('#aveniadp-related-products-list .aveniadp-related-product-item').each(function() {
					var title = String($(this).data('title') || '');
					$(this).toggleClass('is-hidden', query && title.indexOf(query) === -1);
				});
			});

			$('#aveniadp-related-products-list').on('change', '.aveniadp-related-product-checkbox', function() {
				var $checkbox = $(this);
				var id = parseInt($checkbox.val(), 10);
				var ids = aveniadpParseRelatedProductIds($('#aveniadp_related_products').val());

				if ($checkbox.is(':checked')) {
					if (ids.indexOf(id) === -1) {
						if (ids.length >= aveniadpGetRelatedProductsMax()) {
							$checkbox.prop('checked', false);
							return;
						}
						ids.push(id);
					}
				} else {
					ids = ids.filter(function(existingId) {
						return existingId !== id;
					});
				}

				aveniadpSetRelatedProductIds(ids);
			});

			$('form#post').on('submit', function() {
				var ids = aveniadpParseRelatedProductIds($('#aveniadp_related_products').val());
				var checkedIds = {};

				$('#aveniadp-related-products-list .aveniadp-related-product-checkbox:checked').each(function() {
					checkedIds[parseInt($(this).val(), 10)] = true;
				});

				ids = ids.filter(function(id) {
					return checkedIds[id];
				});

				aveniadpSetRelatedProductIds(ids);
			});
		}

		/**
		 * Product additional info sections (Markdown accordion).
		 */
		function aveniadpCreateInfoSectionRow() {
			var $template = $('.aveniadp-info-section-row').first();
			if (!$template.length) {
				return null;
			}

			var $row = $template.clone();
			$row.find('.aveniadp-info-section-title').val('');
			$row.find('.aveniadp-info-section-content').val('');
			return $row;
		}

		if ($('#aveniadp-info-section-rows').length) {
			$('.aveniadp-add-info-section').on('click', function(e) {
				e.preventDefault();
				var $row = aveniadpCreateInfoSectionRow();
				if ($row) {
					$('#aveniadp-info-section-rows').append($row);
				}
			});

			$(document).on('click', '.aveniadp-remove-info-section', function(e) {
				e.preventDefault();
				var $rows = $('#aveniadp-info-section-rows .aveniadp-info-section-row');
				if ($rows.length <= 1) {
					$rows.find('.aveniadp-info-section-title').val('');
					$rows.find('.aveniadp-info-section-content').val('');
					return;
				}
				$(this).closest('.aveniadp-info-section-row').remove();
			});
		}

		if (window.wp && wp.data && wp.data.subscribe) {
			var aveniadpWasSavingPost = false;
			wp.data.subscribe(function() {
				var editor = wp.data.select('core/editor');
				if (!editor || typeof editor.isSavingPost !== 'function') {
					return;
				}
				var isSaving = editor.isSavingPost() && !editor.isAutosavingPost();
				if ( isSaving && ! aveniadpWasSavingPost ) {
					aveniadpSyncPricingMatrix();
					if ( document.getElementById( 'aveniadp_product_gallery' ) ) {
						aveniadpSyncProductGallery();
					}
					if ( document.getElementById( 'aveniadp_related_products' ) ) {
						var relatedIds = [];
						$('#aveniadp-related-products-list .aveniadp-related-product-checkbox:checked').each(function() {
							var relatedId = parseInt($(this).val(), 10);
							if (!isNaN(relatedId) && relatedId > 0 && relatedIds.indexOf(relatedId) === -1) {
								relatedIds.push(relatedId);
							}
						});
						aveniadpSetRelatedProductIds(relatedIds);
					}
				}
				aveniadpWasSavingPost = isSaving;
			});
		}
	});

	// Classic editor: keep product meta boxes expanded (not collapsed) on load.
	if (typeof aveniadpAdmin !== 'undefined' && aveniadpAdmin.isProductEdit) {
		$('#aveniadp_product_details, #aveniadp_product_pricing, #aveniadp_product_gallery, #aveniadp_product_demo, #aveniadp_product_related, #aveniadp_product_catalog, #aveniadp_product_info_sections').removeClass('closed');
	}

	$('.aveniadp-revoke-access-form').on('submit', function(e) {
		var $form = $(this);
		var message = (typeof aveniadpAdmin !== 'undefined' && aveniadpAdmin.i18n) ? aveniadpAdmin.i18n.revokeAccessConfirm : 'Revoke product access for this order?';

		if ($form.hasClass('aveniadp-revoke-all-access-form') && typeof aveniadpAdmin !== 'undefined' && aveniadpAdmin.i18n) {
			message = aveniadpAdmin.i18n.revokeAllAccessConfirm;
		}

		if (!window.confirm(message)) {
			e.preventDefault();
		}
	});

})(jQuery);
