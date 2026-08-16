/**
 * Settings admin page (payments + tax UI).
 *
 * @package AveniaDigitalProducts
 * @since 1.0.0
 */

(function ($) {
	'use strict';

	$(document).ready(function () {
		var $card = $('#aveniadp-payment-status-card');
		var $providersBody = $('#aveniadp-payment-providers-body');
		var $pausedNotice = $('#aveniadp-payment-providers-paused-notice');

		function getSelectedEnvironment() {
			return $('input[name="aveniadp_payment_environment"]:checked').val() || 'sandbox';
		}

		function isSimulating() {
			return $('#aveniadp_simulate_payments').is(':checked');
		}

		function updatePaymentStatusCard() {
			if (!$card.length) {
				return;
			}

			var simulating = isSimulating();
			var environment = getSelectedEnvironment();
			var titleKey = simulating ? 'simulate' : environment;
			var title = $card.data('title-' + titleKey);
			var desc = $card.data('desc-' + titleKey);

			$('#aveniadp-payment-status-title').text(title);
			$('#aveniadp-payment-status-desc').text(desc);

			$card.toggleClass('aveniadp-payment-status-card--simulate', simulating);
			$card.toggleClass('aveniadp-payment-status-card--live', !simulating && environment === 'live');
		}

		function updateCredentialHighlights() {
			var simulating = isSimulating();
			var environment = getSelectedEnvironment();

			$('.aveniadp-payment-env-group').each(function () {
				var $group = $(this);
				var groupEnv = $group.data('env');
				var isActive = !simulating && groupEnv === environment;

				$group.toggleClass('is-active', isActive);
				$group.toggleClass('is-inactive', !simulating && groupEnv !== environment);
			});
		}

		function updatePaymentUI() {
			if (!$card.length) {
				return;
			}

			var simulating = isSimulating();

			if (simulating) {
				$('.aveniadp-payment-environment-row').hide();
				$providersBody.addClass('is-paused');
				$pausedNotice.removeAttr('hidden');
			} else {
				$('.aveniadp-payment-environment-row').show();
				$providersBody.removeClass('is-paused');
				$pausedNotice.attr('hidden', 'hidden');
			}

			updatePaymentStatusCard();
			updateCredentialHighlights();
		}

		$('#aveniadp_simulate_payments').on('change', updatePaymentUI);
		$('input[name="aveniadp_payment_environment"]').on('change', function () {
			updatePaymentStatusCard();
			updateCredentialHighlights();
		});
		updatePaymentUI();

		$('#aveniadp_stripe_enabled').on('change', function () {
			if ($(this).is(':checked')) {
				$('.aveniadp-stripe-settings').show();
			} else {
				$('.aveniadp-stripe-settings').hide();
			}
			updateCredentialHighlights();
		});

		$('#aveniadp_paypal_enabled').on('change', function () {
			if ($(this).is(':checked')) {
				$('.aveniadp-paypal-settings').show();
			} else {
				$('.aveniadp-paypal-settings').hide();
			}
			updateCredentialHighlights();
		});

		function updateTaxModeUi() {
			if (!$('#aveniadp_tax_mode').length) {
				return;
			}

			var mode = $('#aveniadp_tax_mode').val();
			$('.aveniadp-tax-mode-desc').each(function () {
				$(this).prop('hidden', $(this).data('tax-mode') !== mode);
			});
			$('.aveniadp-tax-rate-row').toggle(mode === 'exclusive');
		}

		updateTaxModeUi();
		$('#aveniadp_tax_mode').on('change', updateTaxModeUi);
	});
})(jQuery);
