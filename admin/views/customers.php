<?php
/**
 * Admin customers view.
 *
 * @package AveniaDigitalProducts
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Included from a function; variables stay in local scope.
$view_id = isset( $_GET['view'] ) ? absint( $_GET['view'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin customer detail navigation; capability checked in admin bootstrap.

if ( $view_id ) {
	include AVENIADP_PLUGIN_DIR . 'admin/views/customer-view.php';
	return;
}

global $wpdb;
$customers_table = $wpdb->prefix . 'aveniadp_customers';
$orders_table    = $wpdb->prefix . 'aveniadp_orders';
$search          = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin list search; capability checked in admin bootstrap.
$search          = trim( $search );

// phpcs:disable WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL -- Plugin-owned tables use $wpdb->prefix; SQL identifiers cannot be placeholders.

$select_sql = "SELECT c.*,
	(SELECT COUNT(*) FROM {$orders_table} WHERE customer_id = c.customer_id) as order_count
	FROM {$customers_table} c";

if ( '' !== $search ) {
	$like       = '%' . $wpdb->esc_like( $search ) . '%';
	$customer_id = absint( $search );
	$search_sql  = "{$select_sql}
		WHERE (
			c.email LIKE %s
			OR c.name LIKE %s
			OR CAST( c.customer_id AS CHAR ) LIKE %s";
	$params      = array( $like, $like, $like );

	if ( $customer_id > 0 ) {
		$search_sql .= ' OR c.customer_id = %d';
		$params[]    = $customer_id;
	}

	$search_sql .= ' )
		ORDER BY c.created_at DESC
		LIMIT 100';

	$customers = $wpdb->get_results( $wpdb->prepare( $search_sql, $params ) );
} else {
	$customers = $wpdb->get_results(
		"{$select_sql}
		ORDER BY c.created_at DESC
		LIMIT 100"
	);
}
?>

<div class="wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Customers', 'avenia-digital-products' ); ?></h1>
	<hr class="wp-header-end" />

	<p class="description">
		<?php esc_html_e( 'Customers are created automatically from purchases and free downloads that collect an email.', 'avenia-digital-products' ); ?>
	</p>

	<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="search-box aveniadp-admin-search-box">
		<input type="hidden" name="page" value="aveniadp-customers" />
		<label class="screen-reader-text" for="aveniadp-customers-search"><?php esc_html_e( 'Search customers', 'avenia-digital-products' ); ?></label>
		<input type="search" id="aveniadp-customers-search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php echo esc_attr__( 'Email, name, customer ID...', 'avenia-digital-products' ); ?>" />
		<?php submit_button( __( 'Search customers', 'avenia-digital-products' ), '', '', false ); ?>
		<?php if ( '' !== $search ) : ?>
			<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=aveniadp-customers' ) ); ?>"><?php esc_html_e( 'Clear', 'avenia-digital-products' ); ?></a>
		<?php endif; ?>
	</form>

	<?php if ( ! empty( $customers ) ) : ?>
		<?php if ( '' !== $search || 100 === count( $customers ) ) : ?>
			<p class="description">
				<?php
				if ( '' !== $search ) {
					printf(
						/* translators: %d: number of matching customers shown */
						esc_html( _n( 'Showing %d matching customer (max 100).', 'Showing %d matching customers (max 100).', count( $customers ), 'avenia-digital-products' ) ),
						(int) count( $customers )
					);
				} else {
					esc_html_e( 'Showing the 100 most recent customers. Use search to find older customers.', 'avenia-digital-products' );
				}
				?>
			</p>
		<?php endif; ?>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Customer ID', 'avenia-digital-products' ); ?></th>
					<th><?php esc_html_e( 'Name', 'avenia-digital-products' ); ?></th>
					<th><?php esc_html_e( 'Email', 'avenia-digital-products' ); ?></th>
					<th><?php esc_html_e( 'Orders', 'avenia-digital-products' ); ?></th>
					<th><?php esc_html_e( 'Customer since', 'avenia-digital-products' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'avenia-digital-products' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $customers as $customer ) : ?>
					<tr>
						<td><?php echo esc_html( $customer->customer_id ); ?></td>
						<td><?php echo ! empty( $customer->name ) ? esc_html( $customer->name ) : '&mdash;'; ?></td>
						<td><?php echo esc_html( $customer->email ); ?></td>
						<td><?php echo esc_html( $customer->order_count ); ?></td>
						<td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $customer->created_at ) ) ); ?></td>
						<td>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=aveniadp-customers&view=' . $customer->customer_id ) ); ?>" class="button button-small">
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
				esc_html_e( 'No customers matched your search.', 'avenia-digital-products' );
			} else {
				esc_html_e( 'No customers found.', 'avenia-digital-products' );
			}
			?>
		</p>
	<?php endif; ?>
</div>
