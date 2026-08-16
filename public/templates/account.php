<?php
/**
 * Account dashboard template.
 *
 * @package AveniaDigitalProducts
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Included from a function; variables stay in local scope.
if ( ! is_user_logged_in() ) {
	$login_url = function_exists( 'aveniadp_get_login_page_url' ) ? aveniadp_get_login_page_url() : wp_login_url();
	wp_safe_redirect( $login_url );
	exit;
}

$user = wp_get_current_user();
$customers = function_exists( 'aveniadp_customers' ) ? aveniadp_customers() : new AVENIADP_Customers();
$customer = $customers->get_customer_by_email( $user->user_email );

if ( ! $customer ) {
	echo '<p class="aveniadp-notice is-error">' . esc_html__( 'No customer account found.', 'avenia-digital-products' ) . '</p>';
	return;
}

$orders = function_exists( 'aveniadp_orders' ) ? aveniadp_orders() : new AVENIADP_Orders();
$customer_orders = $customers->get_customer_orders( $customer->customer_id );
$access          = function_exists( 'aveniadp_access' ) ? aveniadp_access() : new AVENIADP_Access();

// Exclude pending/failed checkouts and revoked access from Purchased Products.
$purchased_orders = array();
foreach ( $customer_orders as $order ) {
	if ( $access->order_grants_access( $order ) ) {
		$purchased_orders[] = $order;
	}
}

global $wpdb;

// phpcs:disable WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL -- Plugin-owned tables use $wpdb->prefix; SQL identifiers cannot be placeholders.

$downloads_table = $wpdb->prefix . 'aveniadp_downloads';
$customer_downloads = array();
foreach ( $customer_orders as $order ) {
	$order_downloads = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT * FROM {$downloads_table} WHERE order_id = %d",
			$order->order_id
		)
	);
	$customer_downloads = array_merge( $customer_downloads, $order_downloads );
}

?>

<section class="aveniadp-account">
	<div class="entry-content">
	<div class="aveniadp-account-tabs">
		<nav class="aveniadp-account-tab-nav" aria-label="<?php esc_attr_e( 'Account sections', 'avenia-digital-products' ); ?>">
			<ul class="aveniadp-account-tab-list" role="tablist">
				<li role="presentation">
					<a href="#products" class="aveniadp-tab-link active" role="tab" aria-selected="true" aria-controls="products"><?php esc_html_e( 'Products', 'avenia-digital-products' ); ?></a>
				</li>
				<li role="presentation">
					<a href="#downloads" class="aveniadp-tab-link" role="tab" aria-selected="false" aria-controls="downloads"><?php esc_html_e( 'Downloads', 'avenia-digital-products' ); ?></a>
				</li>
			</ul>
		</nav>

		<div id="products" class="aveniadp-tab-content active" role="tabpanel" aria-labelledby="products">
			<h2 class="aveniadp-section-heading"><?php esc_html_e( 'Purchased Products', 'avenia-digital-products' ); ?></h2>
			<?php if ( ! empty( $purchased_orders ) ) : ?>
				<div class="aveniadp-products-grid aveniadp-account-products-grid">
				<?php foreach ( $purchased_orders as $order ) : ?>
					<?php
					$order_items = $orders->get_order_items( $order->order_id );
					foreach ( $order_items as $item ) :
						$product = get_post( $item->product_id );
						?>
						<article class="aveniadp-product-card aveniadp-account-product-card">
							<?php if ( has_post_thumbnail( $product->ID ) ) : ?>
								<div class="aveniadp-product-image-wrap">
									<a class="aveniadp-product-image" href="<?php echo esc_url( get_permalink( $product->ID ) ); ?>">
										<?php echo get_the_post_thumbnail( $product->ID, 'aveniadp-product-card' ); ?>
									</a>
								</div>
							<?php endif; ?>
							<div class="aveniadp-product-card-details">
								<header class="entry-header">
									<h3 class="entry-title">
										<a href="<?php echo esc_url( get_permalink( $product->ID ) ); ?>"><?php echo esc_html( $product->post_title ); ?></a>
									</h3>
								</header>
								<p class="aveniadp-account-product-meta"><?php
								/* translators: %s: purchase date */
								printf( esc_html__( 'Purchased on %s', 'avenia-digital-products' ), esc_html( date_i18n( get_option( 'date_format' ), strtotime( $order->created_at ) ) ) );
								?></p>
								<p class="aveniadp-account-product-meta"><?php
								/* translators: %d: order ID */
								printf( esc_html__( 'Order #%d', 'avenia-digital-products' ), (int) $order->order_id );
								?></p>
							</div>
						</article>
					<?php endforeach; ?>
				<?php endforeach; ?>
				</div>
			<?php else : ?>
				<p><?php esc_html_e( 'No products purchased yet.', 'avenia-digital-products' ); ?></p>
			<?php endif; ?>
		</div>

		<div id="downloads" class="aveniadp-tab-content" role="tabpanel" aria-labelledby="downloads">
			<h2 class="aveniadp-section-heading"><?php esc_html_e( 'Downloads', 'avenia-digital-products' ); ?></h2>
			<?php if ( ! empty( $customer_downloads ) ) : ?>
				<table class="aveniadp-downloads-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Product', 'avenia-digital-products' ); ?></th>
							<th><?php esc_html_e( 'Downloads', 'avenia-digital-products' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'avenia-digital-products' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $customer_downloads as $download ) : ?>
							<?php
							$product = get_post( $download->product_id );
							$download_url = function_exists( 'aveniadp_get_account_download_url' ) ? aveniadp_get_account_download_url( $download->download_id ) : '';
							?>
							<tr>
								<td><?php echo $product ? esc_html( $product->post_title ) : esc_html__( 'Unknown product', 'avenia-digital-products' ); ?></td>
								<td><?php
								/* translators: %d: download count */
								printf( esc_html__( '%d times', 'avenia-digital-products' ), (int) $download->download_count );
								?></td>
								<td>
									<?php if ( $download_url ) : ?>
									<a href="<?php echo esc_url( $download_url ); ?>" class="aveniadp-account-btn aveniadp-account-btn--primary aveniadp-download-btn">
										<?php esc_html_e( 'Download', 'avenia-digital-products' ); ?>
									</a>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php else : ?>
				<p><?php esc_html_e( 'No downloads available.', 'avenia-digital-products' ); ?></p>
			<?php endif; ?>
		</div>
	</div>
	<p class="aveniadp-account-toolbar">
		<a class="aveniadp-account-btn aveniadp-account-btn--secondary aveniadp-account-logout" href="<?php echo esc_url( wp_logout_url( function_exists( 'aveniadp_get_login_page_url' ) ? aveniadp_get_login_page_url() : home_url( '/' ) ) ); ?>">
			<?php esc_html_e( 'Log out', 'avenia-digital-products' ); ?>
		</a>
	</p>
	</div>
</section>

<?php
// phpcs:enable WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL
