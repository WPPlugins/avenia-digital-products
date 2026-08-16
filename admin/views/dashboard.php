<?php
/**
 * Admin dashboard view.
 *
 * @package AveniaDigitalProducts
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Included from a function; variables stay in local scope.
global $wpdb;

$orders_table    = $wpdb->prefix . 'aveniadp_orders';
$customers_table = $wpdb->prefix . 'aveniadp_customers';

// phpcs:disable WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL -- Plugin-owned tables use $wpdb->prefix; SQL identifiers cannot be placeholders.

$total_sales = (float) $wpdb->get_var(
	"SELECT COALESCE( SUM( total ), 0 ) FROM {$orders_table} WHERE status = 'completed'"
);

$recent_orders = $wpdb->get_results(
	"SELECT o.*, c.email AS customer_email
	FROM {$orders_table} o
	LEFT JOIN {$customers_table} c ON o.customer_id = c.customer_id
	ORDER BY o.created_at DESC
	LIMIT 5"
);

?>

<div class="wrap">
	<h1><?php esc_html_e( 'Avenia Digital Products', 'avenia-digital-products' ); ?></h1>

	<div class="aveniadp-dashboard-widgets">
		<div class="aveniadp-widget">
			<h2><?php esc_html_e( 'Total Sales', 'avenia-digital-products' ); ?></h2>
			<p class="aveniadp-stat"><?php echo esc_html( aveniadp_format_price( $total_sales ) ); ?></p>
			<p class="description"><?php esc_html_e( 'Sum of completed orders.', 'avenia-digital-products' ); ?></p>
		</div>

		<div class="aveniadp-widget">
			<h2><?php esc_html_e( 'Recent Orders', 'avenia-digital-products' ); ?></h2>
			<?php if ( ! empty( $recent_orders ) ) : ?>
				<ul class="aveniadp-dashboard-recent-orders">
					<?php foreach ( $recent_orders as $order ) : ?>
						<li>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=aveniadp-orders&view=' . absint( $order->order_id ) ) ); ?>">
								<?php
								printf(
									/* translators: %d: order ID */
									esc_html__( 'Order #%d', 'avenia-digital-products' ),
									absint( $order->order_id )
								);
								?>
							</a>
							<span class="aveniadp-dashboard-order-meta">
								<?php echo esc_html( $order->customer_email ? $order->customer_email : __( 'Unknown customer', 'avenia-digital-products' ) ); ?>
								&middot;
								<?php echo esc_html( aveniadp_format_price( $order->total ) ); ?>
								&middot;
								<?php echo esc_html( ucfirst( $order->status ) ); ?>
							</span>
						</li>
					<?php endforeach; ?>
				</ul>
				<p>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=aveniadp-orders' ) ); ?>">
						<?php esc_html_e( 'View all orders', 'avenia-digital-products' ); ?>
					</a>
				</p>
			<?php else : ?>
				<p><?php esc_html_e( 'No orders yet.', 'avenia-digital-products' ); ?></p>
			<?php endif; ?>
		</div>
	</div>
</div>
