<?php
/**
 * The title bar style 1 for our theme.
 *
 * This template generates markup required for the title bar style 1
 *
 * @todo Update this template for Default Advanced Headers Style
 *
 * @package Astra Addon
 */

$astra_addon_show_breadcrumb       = Astra_Ext_Advanced_Headers_Loader::astra_advanced_headers_layout_option( 'breadcrumb' );
$astra_addon_is_breadcrumb_enabled = '';
$astra_addon_header_title          = apply_filters( 'astra_advanced_header_title', astra_get_the_title() );
$astra_addon_header_description    = apply_filters( 'astra_advanced_header_description', get_the_archive_description() );

if ( $astra_addon_show_breadcrumb ) {
	$astra_addon_is_breadcrumb_enabled = $astra_addon_show_breadcrumb;
}

?>
<div class="ast-inside-advanced-header-content">
	<div class="ast-advanced-headers-layout ast-advanced-headers-layout-1" >
		<div class="ast-container">
			<div class="ast-advanced-headers-wrap">
				<?php do_action( 'astra_advanced_header_layout_1_wrap_top' ); ?>
				<?php
				if ( $astra_addon_header_title ) {
					$astra_addon_header_title     = apply_filters( 'astra_advanced_header_layout_1_title', $astra_addon_header_title );
					$astra_addon_header_title_tag = apply_filters( 'astra_advanced_header_layout_1_title_tag', 'h1' );
					echo sprintf(
						'<%1$s class="ast-advanced-headers-title">
							%2$s
							%3$s
							%4$s
						</%1$s>',
						/**
						 * Filters the tags for Advanced Header Title - Layout 1.
						 *
						 * @since 2.1.3
						 *
						 * @param string $tags string containing the HTML tags for Advanced Header title.
						 */
						esc_html( $astra_addon_header_title_tag ),
						wp_kses_post( do_action( 'astra_advanced_header_layout_1_before_title' ) ),
						esc_attr( $astra_addon_header_title ),
						wp_kses_post( do_action( 'astra_advanced_header_layout_1_after_title' ) )
					);
				}
				do_action( 'astra_advanced_header_layout_1_after_title_tag' );
				if ( $astra_addon_header_description ) {
					$astra_addon_header_description = apply_filters( 'astra_advanced_header_layout_1_description', $astra_addon_header_description );
					?>
				<div class="taxonomy-description">
					<?php do_action( 'astra_advanced_header_layout_1_before_description' ); ?>
					<?php echo wp_kses_post( $astra_addon_header_description ); ?>
					<?php do_action( 'astra_advanced_header_layout_1_after_description' ); ?>
				</div>
				<?php } ?>

				<?php do_action( 'astra_advanced_header_layout_1_wrap_bottom' ); ?>
			</div>
	<?php if ( $astra_addon_is_breadcrumb_enabled ) { ?>
			<div class="ast-advanced-headers-breadcrumb">
				<?php Astra_Ext_Advanced_Headers_Markup::advanced_headers_breadcrumbs_markup(); ?>
			</div><!-- .ast-advanced-headers-breadcrumb -->
	<?php } ?>
		</div>
	</div>
</div>
