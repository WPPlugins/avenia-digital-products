<?php
/**
 * Admin customer detail view.
 *
 * @package AveniaDigitalProducts
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Included from a function; variables stay in local scope.
$customer_id = isset( $_GET['view'] ) ? absint( $_GET['view'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin customer detail view; capability checked in admin bootstrap.
$customers   = function_exists( 'aveniadp_customers' ) ? aveniadp_customers() : new AVENIADP_Customers();
$customer    = $customers->get_customer( $customer_id );

if ( ! $customer ) {
	echo '<div class="wrap"><h1>' . esc_html__( 'Customer not found', 'avenia-digital-products' ) . '</h1></div>';
	return;
}

$orders         = $customers->get_customer_orders( $customer_id );
$access         = function_exists( 'aveniadp_access' ) ? aveniadp_access() : new AVENIADP_Access();
$redirect_to    = admin_url( 'admin.php?page=aveniadp-customers&view=' . $customer_id );
$has_active_access = false;

foreach ( $orders as $order ) {
	if ( 'completed' === $order->status && $access->order_grants_access( $order ) ) {
		$has_active_access = true;
		break;
	}
}
?>

<div class="wrap">
	<h1>
		<?php
		if ( function_exists( 'aveniadp_format_customer_label' ) ) {
			echo esc_html( aveniadp_format_customer_label( $customer ) );
		} else {
			echo esc_html( $customer->email );
		}
		?>
	</h1>

	<p>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=aveniadp-customers' ) ); ?>">&larr; <?php esc_html_e( 'Back to customers', 'avenia-digital-products' ); ?></a>
	</p>

	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'Customer ID', 'avenia-digital-products' ); ?></th>
			<td><?php echo esc_html( $customer->customer_id ); ?></td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Name', 'avenia-digital-products' ); ?></th>
			<td><?php echo ! empty( $customer->name ) ? esc_html( $customer->name ) : '&mdash;'; ?></td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Email', 'avenia-digital-products' ); ?></th>
			<td><?php echo esc_html( $customer->email ); ?></td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'WordPress user', 'avenia-digital-products' ); ?></th>
			<td>
				<?php if ( ! empty( $customer->user_id ) ) : ?>
					<a href="<?php echo esc_url( get_edit_user_link( $customer->user_id ) ); ?>">
						<?php echo esc_html( get_userdata( $customer->user_id )->user_login ); ?>
					</a>
				<?php else : ?>
					<?php esc_html_e( 'No account linked', 'avenia-digital-products' ); ?>
				<?php endif; ?>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Registered', 'avenia-digital-products' ); ?></th>
			<td><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $customer->created_at ) ) ); ?></td>
		</tr>
	</table>

	<h2><?php esc_html_e( 'Orders', 'avenia-digital-products' ); ?></h2>
	<?php if ( ! empty( $orders ) ) : ?>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Order ID', 'avenia-digital-products' ); ?></th>
					<th><?php esc_html_e( 'Total', 'avenia-digital-products' ); ?></th>
					<th><?php esc_html_e( 'Status', 'avenia-digital-products' ); ?></th>
					<th><?php esc_html_e( 'Access', 'avenia-digital-products' ); ?></th>
					<th><?php esc_html_e( 'Payment', 'avenia-digital-products' ); ?></th>
					<th><?php esc_html_e( 'Date', 'avenia-digital-products' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'avenia-digital-products' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $orders as $order ) : ?>
					<?php
					$order_access_active = 'completed' === $order->status && $access->order_grants_access( $order );
					?>
					<tr>
						<td><strong>#<?php echo esc_html( $order->order_id ); ?></strong></td>
						<td><?php echo esc_html( aveniadp_format_price( $order->total ) ); ?></td>
						<td><?php echo esc_html( aveniadp_format_order_status( $order->status ) ); ?></td>
						<td>
							<?php if ( 'completed' !== $order->status ) : ?>
								&mdash;
							<?php elseif ( $order_access_active ) : ?>
								<span class="aveniadp-status-active"><?php esc_html_e( 'Active', 'avenia-digital-products' ); ?></span>
							<?php else : ?>
								<span class="aveniadp-status-revoked"><?php esc_html_e( 'Revoked', 'avenia-digital-products' ); ?></span>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( aveniadp_format_payment_method( $order->payment_method ) ); ?></td>
						<td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $order->created_at ) ) ); ?></td>
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
		<p><?php esc_html_e( 'No orders yet.', 'avenia-digital-products' ); ?></p>
	<?php endif; ?>

	<?php if ( $has_active_access ) : ?>
		<div class="aveniadp-admin-access-actions">
			<h2><?php esc_html_e( 'Access control', 'avenia-digital-products' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Revoke all product access for this customer locally. Orders and purchase history are kept for records. Stripe or PayPal billing is not changed.', 'avenia-digital-products' ); ?>
			</p>
			<p class="description">
				<?php esc_html_e( 'Deleting the WordPress user alone does not revoke downloads. Use revoke access here first, then delete the WordPress account from Users if needed.', 'avenia-digital-products' ); ?>
			</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="aveniadp-revoke-access-form aveniadp-revoke-all-access-form">
				<?php wp_nonce_field( 'aveniadp_revoke_all_customer_access' ); ?>
				<input type="hidden" name="action" value="aveniadp_revoke_all_customer_access" />
				<input type="hidden" name="customer_id" value="<?php echo esc_attr( $customer->customer_id ); ?>" />
				<input type="hidden" name="redirect_to" value="<?php echo esc_attr( $redirect_to ); ?>" />
				<button type="submit" class="button button-secondary aveniadp-revoke-access-button">
					<?php esc_html_e( 'Revoke all access', 'avenia-digital-products' ); ?>
				</button>
			</form>
		</div>
	<?php endif; ?>
</div>
