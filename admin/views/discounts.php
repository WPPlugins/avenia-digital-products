<?php
/**
 * Admin discounts view.
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
$discounts_table = $wpdb->prefix . 'aveniadp_discount_codes';

// phpcs:disable WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL -- Plugin-owned tables use $wpdb->prefix; SQL identifiers cannot be placeholders.

$discount_context = AVENIADP_Admin_Discounts::get_view_context();
$action           = $discount_context['action'];
$code_id          = $discount_context['code_id'];
$discount         = $discount_context['discount'];

foreach ( $discount_context['notices'] as $notice ) {
	$class = 'error' === $notice['type'] ? 'notice-error' : 'notice-success';
	echo '<div class="notice ' . esc_attr( $class ) . '"><p>' . esc_html( $notice['message'] ) . '</p></div>';
}

$products = get_posts(
	array(
		'post_type'      => AVENIADP_PRODUCT_POST_TYPE,
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'orderby'        => 'title',
		'order'          => 'ASC',
	)
);

if ( 'add' === $action || 'edit' === $action ) :
	$selected_products = array();
	if ( $discount && ! empty( $discount->applicable_products ) ) {
		$selected_products = json_decode( $discount->applicable_products, true );
		if ( ! is_array( $selected_products ) ) {
			$selected_products = array();
		}
	}

	$expires_date = '';
	if ( $discount && ! empty( $discount->expires_at ) ) {
		$expires_date = gmdate( 'Y-m-d', strtotime( $discount->expires_at ) );
	}

	$page_title = 'edit' === $action
		? __( 'Edit Discount Code', 'avenia-digital-products' )
		: __( 'Add New Discount Code', 'avenia-digital-products' );
	?>
	<div class="wrap">
		<h1><?php echo esc_html( $page_title ); ?></h1>

		<form method="post" action="">
			<?php wp_nonce_field( 'aveniadp_save_discount', 'aveniadp_discount_nonce' ); ?>
			<?php if ( $discount ) : ?>
				<input type="hidden" name="code_id" value="<?php echo esc_attr( $discount->code_id ); ?>" />
			<?php endif; ?>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="aveniadp_discount_code"><?php esc_html_e( 'Code', 'avenia-digital-products' ); ?></label></th>
					<td>
						<input type="text" id="aveniadp_discount_code" name="code" class="regular-text" value="<?php echo esc_attr( $discount ? $discount->code : '' ); ?>" required maxlength="50" />
						<p class="description"><?php esc_html_e( 'The code customers enter at checkout.', 'avenia-digital-products' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="aveniadp_discount_type"><?php esc_html_e( 'Discount Type', 'avenia-digital-products' ); ?></label></th>
					<td>
						<select id="aveniadp_discount_type" name="discount_type">
							<option value="percentage" <?php selected( ! $discount || 'percentage' === $discount->discount_type ); ?>><?php esc_html_e( 'Percentage', 'avenia-digital-products' ); ?></option>
							<option value="fixed" <?php selected( $discount && 'fixed' === $discount->discount_type ); ?>><?php esc_html_e( 'Fixed Amount', 'avenia-digital-products' ); ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="aveniadp_discount_value"><?php esc_html_e( 'Discount Value', 'avenia-digital-products' ); ?></label></th>
					<td>
						<input type="number" id="aveniadp_discount_value" name="discount_value" class="small-text" step="0.01" min="0.01" value="<?php echo esc_attr( $discount ? $discount->discount_value : '' ); ?>" required />
						<p class="description"><?php esc_html_e( 'Enter a percentage (e.g. 10) or fixed amount.', 'avenia-digital-products' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="aveniadp_usage_limit"><?php esc_html_e( 'Usage Limit', 'avenia-digital-products' ); ?></label></th>
					<td>
						<input type="number" id="aveniadp_usage_limit" name="usage_limit" class="small-text" min="1" value="<?php echo esc_attr( $discount && ! empty( $discount->usage_limit ) ? $discount->usage_limit : '' ); ?>" />
						<p class="description"><?php esc_html_e( 'Total uses across all customers. Leave blank for unlimited.', 'avenia-digital-products' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="aveniadp_usage_limit_per_customer"><?php esc_html_e( 'Usage Limit Per Customer', 'avenia-digital-products' ); ?></label></th>
					<td>
						<input type="number" id="aveniadp_usage_limit_per_customer" name="usage_limit_per_customer" class="small-text" min="1" value="<?php echo esc_attr( $discount && ! empty( $discount->usage_limit_per_customer ) ? $discount->usage_limit_per_customer : '' ); ?>" />
						<p class="description"><?php esc_html_e( 'How many times each customer can use this code. Leave blank for unlimited.', 'avenia-digital-products' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="aveniadp_expires_at"><?php esc_html_e( 'Expiration Date', 'avenia-digital-products' ); ?></label></th>
					<td>
						<input type="date" id="aveniadp_expires_at" name="expires_at" value="<?php echo esc_attr( $expires_date ); ?>" />
						<p class="description"><?php esc_html_e( 'Leave blank if the code never expires.', 'avenia-digital-products' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="aveniadp_minimum_amount"><?php esc_html_e( 'Minimum Order Amount', 'avenia-digital-products' ); ?></label></th>
					<td>
						<input type="number" id="aveniadp_minimum_amount" name="minimum_amount" class="small-text" step="0.01" min="0" value="<?php echo esc_attr( $discount && ! empty( $discount->minimum_amount ) ? $discount->minimum_amount : '' ); ?>" />
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="aveniadp_applicable_products"><?php esc_html_e( 'Applicable Products', 'avenia-digital-products' ); ?></label></th>
					<td>
						<select id="aveniadp_applicable_products" name="applicable_products[]" multiple size="8" class="regular-text">
							<?php foreach ( $products as $product ) : ?>
								<option value="<?php echo esc_attr( $product->ID ); ?>" <?php selected( in_array( $product->ID, $selected_products, true ) ); ?>>
									<?php echo esc_html( $product->post_title ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="description"><?php esc_html_e( 'Leave empty to apply to all products.', 'avenia-digital-products' ); ?></p>
					</td>
				</tr>
			</table>

			<p class="submit">
				<button type="submit" name="aveniadp_save_discount" class="button button-primary"><?php esc_html_e( 'Save Discount Code', 'avenia-digital-products' ); ?></button>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=aveniadp-discounts' ) ); ?>" class="button"><?php esc_html_e( 'Cancel', 'avenia-digital-products' ); ?></a>
			</p>
		</form>
	</div>
	<?php
	return;
endif;

// Get discount codes.
$discounts = $wpdb->get_results(
	"SELECT * FROM {$discounts_table} ORDER BY created_at DESC"
);
?>

<div class="wrap">
	<h1><?php esc_html_e( 'Discount Codes', 'avenia-digital-products' ); ?></h1>

	<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=aveniadp-discounts&action=add' ) ); ?>" class="button button-primary"><?php esc_html_e( 'Add New Discount Code', 'avenia-digital-products' ); ?></a></p>

	<?php if ( ! empty( $discounts ) ) : ?>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Code', 'avenia-digital-products' ); ?></th>
					<th><?php esc_html_e( 'Type', 'avenia-digital-products' ); ?></th>
					<th><?php esc_html_e( 'Value', 'avenia-digital-products' ); ?></th>
					<th><?php esc_html_e( 'Usage', 'avenia-digital-products' ); ?></th>
					<th><?php esc_html_e( 'Expires', 'avenia-digital-products' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'avenia-digital-products' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $discounts as $discount ) : ?>
					<tr>
						<td><strong><?php echo esc_html( $discount->code ); ?></strong></td>
						<td><?php echo esc_html( ucfirst( $discount->discount_type ) ); ?></td>
						<td>
							<?php
							if ( 'percentage' === $discount->discount_type ) {
								echo esc_html( $discount->discount_value ) . '%';
							} else {
								echo esc_html( aveniadp_format_price( $discount->discount_value ) );
							}
							?>
						</td>
						<td>
							<?php
							if ( ! empty( $discount->usage_limit ) ) {
								/* translators: 1: usage count, 2: usage limit */
								printf( esc_html__( '%1$d / %2$d', 'avenia-digital-products' ), (int) $discount->usage_count, (int) $discount->usage_limit );
							} else {
								/* translators: %d: usage count */
								printf( esc_html__( '%d (unlimited)', 'avenia-digital-products' ), (int) $discount->usage_count );
							}
							?>
						</td>
						<td>
							<?php
							if ( ! empty( $discount->expires_at ) ) {
								echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $discount->expires_at ) ) );
							} else {
								esc_html_e( 'Never', 'avenia-digital-products' );
							}
							?>
						</td>
						<td>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=aveniadp-discounts&action=edit&id=' . $discount->code_id ) ); ?>" class="button button-small">
								<?php esc_html_e( 'Edit', 'avenia-digital-products' ); ?>
							</a>
							<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=aveniadp-discounts&delete=' . $discount->code_id ), 'delete_discount_' . $discount->code_id ) ); ?>" class="button button-small" onclick="return confirm('Are you sure?');">
								<?php esc_html_e( 'Delete', 'avenia-digital-products' ); ?>
							</a>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php else : ?>
		<p><?php esc_html_e( 'No discount codes found.', 'avenia-digital-products' ); ?></p>
	<?php endif; ?>
</div>
