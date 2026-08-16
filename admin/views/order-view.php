<?php
/**
 * Admin order detail view.
 *
 * @package AveniaDigitalProducts
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Included from a function; variables stay in local scope.
$order_id = isset( $_GET['view'] ) ? absint( $_GET['view'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin order detail view; capability checked in admin bootstrap.
$orders   = function_exists( 'aveniadp_orders' ) ? aveniadp_orders() : new AVENIADP_Orders();
$order    = $orders->get_order( $order_id );

if ( ! $order ) {
	echo '<div class="wrap"><h1>' . esc_html__( 'Order not found', 'avenia-digital-products' ) . '</h1></div>';
	return;
}

$customers      = function_exists( 'aveniadp_customers' ) ? aveniadp_customers() : new AVENIADP_Customers();
$customer       = $customers->get_customer( $order->customer_id );
$items          = $orders->get_order_items( $order_id );
$access         = function_exists( 'aveniadp_access' ) ? aveniadp_access() : new AVENIADP_Access();
$access_revoked = ! $access->order_grants_access( $order );
$redirect_to    = admin_url( 'admin.php?page=aveniadp-orders&view=' . $order_id );
?>

<div class="wrap">
	<h1>
		<?php
		printf(
			/* translators: %d: order ID */
			esc_html__( 'Order #%d', 'avenia-digital-products' ),
			absint( $order->order_id )
		);
		?>
	</h1>

	<p>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=aveniadp-orders' ) ); ?>">&larr; <?php esc_html_e( 'Back to orders', 'avenia-digital-products' ); ?></a>
	</p>

	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'Customer', 'avenia-digital-products' ); ?></th>
			<td>
				<?php if ( $customer ) : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=aveniadp-customers&view=' . $customer->customer_id ) ); ?>">
						<?php echo esc_html( function_exists( 'aveniadp_format_customer_label' ) ? aveniadp_format_customer_label( $customer ) : $customer->email ); ?>
					</a>
				<?php else : ?>
					&mdash;
				<?php endif; ?>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Status', 'avenia-digital-products' ); ?></th>
			<td><?php echo esc_html( aveniadp_format_order_status( $order->status ) ); ?></td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Access', 'avenia-digital-products' ); ?></th>
			<td>
				<?php if ( 'completed' !== $order->status ) : ?>
					&mdash;
				<?php elseif ( $access_revoked ) : ?>
					<span class="aveniadp-status-revoked"><?php esc_html_e( 'Revoked', 'avenia-digital-products' ); ?></span>
					<?php if ( ! empty( $order->access_revoked_at ) ) : ?>
						<span class="description">
							<?php
							echo esc_html(
								sprintf(
									/* translators: %s: formatted date */
									__( 'on %s', 'avenia-digital-products' ),
									date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $order->access_revoked_at ) )
								)
							);
							?>
						</span>
					<?php endif; ?>
				<?php else : ?>
					<span class="aveniadp-status-active"><?php esc_html_e( 'Active', 'avenia-digital-products' ); ?></span>
				<?php endif; ?>
			</td>
		</tr>
		<?php if ( ! empty( $order->tax_amount ) && (float) $order->tax_amount > 0 ) : ?>
			<tr>
				<th scope="row"><?php esc_html_e( 'Tax', 'avenia-digital-products' ); ?></th>
				<td>
					<?php
					echo esc_html( aveniadp_format_price( $order->tax_amount ) );
					if ( ! empty( $order->tax_rate ) ) {
						echo ' ';
						printf(
							/* translators: %s: tax rate percentage */
							esc_html__( '(%s%%)', 'avenia-digital-products' ),
							esc_html( rtrim( rtrim( number_format( (float) $order->tax_rate, 2, '.', '' ), '0' ), '.' ) )
						);
					}
					?>
				</td>
			</tr>
		<?php endif; ?>
		<tr>
			<th scope="row"><?php esc_html_e( 'Total', 'avenia-digital-products' ); ?></th>
			<td><?php echo esc_html( aveniadp_format_price( $order->total ) ); ?></td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Payment method', 'avenia-digital-products' ); ?></th>
			<td><?php echo esc_html( aveniadp_format_payment_method( $order->payment_method ) ); ?></td>
		</tr>
		<?php if ( ! empty( $order->transaction_id ) ) : ?>
			<?php $transaction_url = function_exists( 'aveniadp_get_order_transaction_url' ) ? aveniadp_get_order_transaction_url( $order ) : ''; ?>
			<tr>
				<th scope="row"><?php esc_html_e( 'Transaction ID', 'avenia-digital-products' ); ?></th>
				<td>
					<?php if ( $transaction_url ) : ?>
						<a href="<?php echo esc_url( $transaction_url ); ?>" target="_blank" rel="noopener noreferrer">
							<code><?php echo esc_html( $order->transaction_id ); ?></code>
						</a>
					<?php else : ?>
						<code><?php echo esc_html( $order->transaction_id ); ?></code>
					<?php endif; ?>
				</td>
			</tr>
		<?php endif; ?>
		<tr>
			<th scope="row"><?php esc_html_e( 'Date', 'avenia-digital-products' ); ?></th>
			<td><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $order->created_at ) ) ); ?></td>
		</tr>
	</table>

	<h2><?php esc_html_e( 'Items', 'avenia-digital-products' ); ?></h2>
	<?php if ( ! empty( $items ) ) : ?>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Product', 'avenia-digital-products' ); ?></th>
					<th><?php esc_html_e( 'Price', 'avenia-digital-products' ); ?></th>
					<th><?php esc_html_e( 'Sites', 'avenia-digital-products' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $items as $item ) : ?>
					<tr>
						<td><?php echo esc_html( get_the_title( $item->product_id ) ); ?></td>
						<td><?php echo esc_html( aveniadp_format_price( $item->price ) ); ?></td>
						<td><?php echo esc_html( aveniadp_format_license_quantity_label( $item->license_quantity ) ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php else : ?>
		<p><?php esc_html_e( 'No items found.', 'avenia-digital-products' ); ?></p>
	<?php endif; ?>

	<?php if ( 'completed' === $order->status && ! $access_revoked ) : ?>
		<div class="aveniadp-admin-access-actions">
			<h2><?php esc_html_e( 'Access control', 'avenia-digital-products' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Revoke download access locally. Stripe or PayPal billing is not changed.', 'avenia-digital-products' ); ?>
			</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="aveniadp-revoke-access-form">
				<?php wp_nonce_field( 'aveniadp_revoke_order_access' ); ?>
				<input type="hidden" name="action" value="aveniadp_revoke_order_access" />
				<input type="hidden" name="order_id" value="<?php echo esc_attr( $order->order_id ); ?>" />
				<input type="hidden" name="redirect_to" value="<?php echo esc_attr( $redirect_to ); ?>" />
				<button type="submit" class="button button-secondary aveniadp-revoke-access-button">
					<?php esc_html_e( 'Revoke access', 'avenia-digital-products' ); ?>
				</button>
			</form>
		</div>
	<?php endif; ?>
</div>
