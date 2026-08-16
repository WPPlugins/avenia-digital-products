<?php
/**
 * Admin orders view.
 *
 * @package AveniaDigitalProducts
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Included from a function; variables stay in local scope.
$view_id = isset( $_GET['view'] ) ? absint( $_GET['view'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin order detail navigation; capability checked in admin bootstrap.

if ( $view_id ) {
	include AVENIADP_PLUGIN_DIR . 'admin/views/order-view.php';
	return;
}

global $wpdb;
$orders_table    = $wpdb->prefix . 'aveniadp_orders';
$customers_table = $wpdb->prefix . 'aveniadp_customers';
$search          = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin list search; capability checked in admin bootstrap.
$search          = trim( $search );

// phpcs:disable WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL -- Plugin-owned tables use $wpdb->prefix; SQL identifiers cannot be placeholders.

$select_sql = "SELECT o.*, c.email as customer_email, c.name as customer_name
	FROM {$orders_table} o
	LEFT JOIN {$customers_table} c ON o.customer_id = c.customer_id";

if ( '' !== $search ) {
	$like       = '%' . $wpdb->esc_like( $search ) . '%';
	$order_id   = absint( ltrim( $search, '#' ) );
	$search_sql = "{$select_sql}
		WHERE (
			c.email LIKE %s
			OR c.name LIKE %s
			OR o.transaction_id LIKE %s
			OR o.payment_method LIKE %s
			OR o.status LIKE %s
			OR CAST( o.order_id AS CHAR ) LIKE %s";
	$params     = array( $like, $like, $like, $like, $like, $like );

	if ( $order_id > 0 ) {
		$search_sql .= ' OR o.order_id = %d';
		$params[]    = $order_id;
	}

	$search_sql .= ' )
		ORDER BY o.created_at DESC
		LIMIT 100';

	$orders = $wpdb->get_results( $wpdb->prepare( $search_sql, $params ) );
} else {
	$orders = $wpdb->get_results(
		"{$select_sql}
		ORDER BY o.created_at DESC
		LIMIT 100"
	);
}
?>

<div class="wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Orders', 'avenia-digital-products' ); ?></h1>
	<hr class="wp-header-end" />

	<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="search-box aveniadp-admin-search-box">
		<input type="hidden" name="page" value="aveniadp-orders" />
		<label class="screen-reader-text" for="aveniadp-orders-search"><?php esc_html_e( 'Search orders', 'avenia-digital-products' ); ?></label>
		<input type="search" id="aveniadp-orders-search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php echo esc_attr__( 'Order ID, email, name, transaction...', 'avenia-digital-products' ); ?>" />
		<?php submit_button( __( 'Search orders', 'avenia-digital-products' ), '', '', false ); ?>
		<?php if ( '' !== $search ) : ?>
			<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=aveniadp-orders' ) ); ?>"><?php esc_html_e( 'Clear', 'avenia-digital-products' ); ?></a>
		<?php endif; ?>
	</form>

	<?php if ( ! empty( $orders ) ) : ?>
		<?php if ( '' !== $search || 100 === count( $orders ) ) : ?>
			<p class="description">
				<?php
				if ( '' !== $search ) {
					printf(
						/* translators: %d: number of matching orders shown */
						esc_html( _n( 'Showing %d matching order (max 100).', 'Showing %d matching orders (max 100).', count( $orders ), 'avenia-digital-products' ) ),
						(int) count( $orders )
					);
				} else {
					esc_html_e( 'Showing the 100 most recent orders. Use search to find older orders.', 'avenia-digital-products' );
				}
				?>
			</p>
		<?php endif; ?>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Order ID', 'avenia-digital-products' ); ?></th>
					<th><?php esc_html_e( 'Customer', 'avenia-digital-products' ); ?></th>
					<th><?php esc_html_e( 'Total', 'avenia-digital-products' ); ?></th>
					<th><?php esc_html_e( 'Status', 'avenia-digital-products' ); ?></th>
					<th><?php esc_html_e( 'Payment Method', 'avenia-digital-products' ); ?></th>
					<th><?php esc_html_e( 'Date', 'avenia-digital-products' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'avenia-digital-products' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $orders as $order ) : ?>
					<tr>
						<td><strong>#<?php echo esc_html( $order->order_id ); ?></strong></td>
						<td><?php echo esc_html( $order->customer_email ); ?></td>
						<td><?php echo esc_html( aveniadp_format_price( $order->total ) ); ?></td>
						<td>
							<span class="aveniadp-status-<?php echo esc_attr( $order->status ); ?>">
								<?php echo esc_html( aveniadp_format_order_status( $order->status ) ); ?>
							</span>
						</td>
						<td><?php echo esc_html( aveniadp_format_payment_method( $order->payment_method ) ); ?></td>
						<td><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $order->created_at ) ) ); ?></td>
						<td>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=aveniadp-orders&view=' . $order->order_id ) ); ?>" class="button button-small">
								<?php esc_html_e( 'View', 'avenia-digital-products' ); ?>
							</a>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php else : ?>
		<p>
			<?php
			if ( '' !== $search ) {
				esc_html_e( 'No orders matched your search.', 'avenia-digital-products' );
			} else {
				esc_html_e( 'No orders found.', 'avenia-digital-products' );
			}
			?>
		</p>
	<?php endif; ?>
</div>
