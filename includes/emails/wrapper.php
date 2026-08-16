<?php
/**
 * Shared HTML email wrapper.
 *
 * Expects $email_content (string) in scope.
 *
 * @package AveniaDigitalProducts
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Included from a function; variables stay in local scope.
$logo_url   = function_exists( 'aveniadp_get_email_logo_url' ) ? aveniadp_get_email_logo_url() : '';
$site_name  = get_bloginfo( 'name' );
$site_url   = home_url( '/' );
$text_color = '#1d2327';
$muted      = '#646970';
$border     = '#dcdcde';
$bg         = '#f6f7f7';
$card       = '#ffffff';
$accent_colors = function_exists( 'aveniadp_get_appearance_accent_colors' )
	? aveniadp_get_appearance_accent_colors()
	: array(
		'bg'   => '#111111',
		'text' => '#ffffff',
	);
$accent     = $accent_colors['bg'];
?>
<!DOCTYPE html>
<html lang="<?php echo esc_attr( get_bloginfo( 'language' ) ); ?>">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<title><?php echo esc_html( $site_name ); ?></title>
</head>
<body style="margin:0;padding:0;background-color:<?php echo esc_attr( $bg ); ?>;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Oxygen-Sans,Ubuntu,Cantarell,'Helvetica Neue',sans-serif;color:<?php echo esc_attr( $text_color ); ?>;-webkit-text-size-adjust:100%;-ms-text-size-adjust:100%;">
	<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color:<?php echo esc_attr( $bg ); ?>;padding:32px 16px;">
		<tr>
			<td align="center">
				<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="600" style="max-width:600px;width:100%;">
					<tr>
						<td align="center" style="padding:0 0 24px;">
							<?php if ( $logo_url ) : ?>
								<a href="<?php echo esc_url( $site_url ); ?>" style="text-decoration:none;display:inline-block;">
									<img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( $site_name ); ?>" width="160" style="display:block;width:160px;max-width:160px;height:auto;border:0;outline:none;text-decoration:none;" />
								</a>
							<?php else : ?>
								<a href="<?php echo esc_url( $site_url ); ?>" style="font-size:22px;font-weight:700;color:<?php echo esc_attr( $accent ); ?>;text-decoration:none;">
									<?php echo esc_html( $site_name ); ?>
								</a>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<td style="background-color:<?php echo esc_attr( $card ); ?>;border:1px solid <?php echo esc_attr( $border ); ?>;border-radius:8px;padding:32px 28px;">
							<div style="font-size:15px;line-height:1.6;color:<?php echo esc_attr( $text_color ); ?>;">
								<?php echo wp_kses_post( $email_content ); ?>
							</div>
						</td>
					</tr>
					<tr>
						<td align="center" style="padding:24px 8px 0;font-size:12px;line-height:1.5;color:<?php echo esc_attr( $muted ); ?>;">
							<p style="margin:0 0 8px;">
								<?php
								printf(
									/* translators: %s: site name */
									esc_html__( 'Sent by %s', 'avenia-digital-products' ),
									esc_html( $site_name )
								);
								?>
							</p>
							<p style="margin:0;">
								<a href="<?php echo esc_url( $site_url ); ?>" style="color:<?php echo esc_attr( $accent ); ?>;text-decoration:none;">
									<?php echo esc_html( wp_parse_url( $site_url, PHP_URL_HOST ) ? wp_parse_url( $site_url, PHP_URL_HOST ) : $site_url ); ?>
								</a>
							</p>
						</td>
					</tr>
				</table>
			</td>
		</tr>
	</table>
</body>
</html>
