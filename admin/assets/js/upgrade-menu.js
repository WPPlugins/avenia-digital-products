/**
 * Lite Upgrade to Full menu link: open in a new tab.
 *
 * @package AveniaDigitalProducts
 * @since 1.0.0
 */

(function () {
	'use strict';

	var labels = document.querySelectorAll('#adminmenu .aveniadp-upgrade-menu-item');
	for (var i = 0; i < labels.length; i++) {
		var link = labels[i].closest('a');
		if (link) {
			link.setAttribute('target', '_blank');
			link.setAttribute('rel', 'noopener noreferrer');
		}
	}
})();
