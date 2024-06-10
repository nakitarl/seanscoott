<?php
/**
 * Import ajax actions.
 *
 * @package AiBuilder
 */

namespace AiBuilder\Inc\Ajax;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use AiBuilder\Inc\Ajax\AjaxBase;
use AiBuilder\Inc\Traits\Instance;
use AiBuilder\Inc\Classes\Ai_Builder_Importer_Log;
use AiBuilder\Inc\Classes\Zipwp\Ai_Builder_ZipWP_Integration;
use AiBuilder\Inc\Classes\Importer\Ai_Builder_Site_Options_Import;
use AiBuilder\Inc\Classes\Importer\Ai_Builder_Utils;
use AiBuilder\Inc\Classes\Importer\Ai_Builder_Fse_Importer;

use STImporter\Importer\ST_Importer_File_System;
use STImporter\Importer\ST_Importer;
use STImporter\Resetter\ST_Resetter;
use STImporter\Importer\ST_Importer_Helper;

use STImporter\Importer\Batch\ST_Batch_Processing_Gutenberg;
use STImporter\Importer\Batch\ST_Batch_Processing_Misc;
/**
 * Class Flows.
 */
class Importer extends AjaxBase {

	use Instance;

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'astra_sites_import_complete', array( $this, 'update_required_options' ) );
	}

	/**
	 * Update options.
	 *
	 * @return void
	 */
	public function update_required_options() {
		update_option( 'astra_sites_import_complete', 'yes', 'no' );

		if ( 'ai' === get_transient( 'astra_sites_current_import_template_type' ) ) {
			update_option( 'astra_sites_batch_process_complete', 'yes' );
		} else {
			update_option( 'astra_sites_batch_process_complete', 'no' );
		}
		delete_transient( 'astra_sites_import_started' );
	}

	/**
	 * Register_ajax_events.
	 *
	 * @return void
	 */
	public function register_ajax_events() {

		$ajax_events = array(
			// Import Part 1 Start.
			'backup_settings',
			'reset_customizer_data',
			'reset_site_options',
			'reset_widgets_data',
			'reset_terms_and_forms',
			'get_deleted_post_ids',
			'reset_posts',
			'download_selected_image',
			'import_customizer_settings',
			'import_spectra_settings',
			'import_surecart_settings',
			// Import Part 1 End.

			// Import Part 2 Start.
			'import_options',
			'import_widgets',
			'gutenberg_batch',
			'image_replacement_batch',
			'import_end',
			'set_site_data',
			// Import Part 2 End.
		);

		$this->init_ajax_events( $ajax_events );
	}

	/**
	 * Backup our existing settings.
	 */
	public function backup_settings() {

		if ( ! defined( 'WP_CLI' ) && wp_doing_ajax() ) {
			check_ajax_referer( 'astra-sites', '_ajax_nonce' );

			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( __( 'User does not have permission!', 'ai-builder', 'astra-sites' ) );
			}
		}

		$log_file_path = ST_Resetter::backup_settings();

		if ( defined( 'WP_CLI' ) ) {
			\WP_CLI::line( 'File generated at ' . $log_file_path );
		} elseif ( wp_doing_ajax() ) {
			wp_send_json_success();
		}
	}

	/**
	 * Reset posts in chunks.
	 *
	 * @since 3.0.8
	 */
	public function reset_posts() {
		if ( wp_doing_ajax() ) {
			check_ajax_referer( 'astra-sites', '_ajax_nonce' );

			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( __( 'You are not allowed to perform this action', 'ai-builder', 'astra-sites' ) );
			}
		}

		ST_Resetter::reset_posts();

		if ( wp_doing_ajax() ) {
			wp_send_json_success();
		}
	}

	/**
	 * Reset customizer data
	 *
	 * @since 1.3.0
	 * @return void
	 */
	public function reset_customizer_data() {

		if ( ! defined( 'WP_CLI' ) && wp_doing_ajax() ) {
			// Verify Nonce.
			check_ajax_referer( 'astra-sites', '_ajax_nonce' );

			if ( ! current_user_can( 'customize' ) ) {
				wp_send_json_error( __( 'You are not allowed to perform this action', 'ai-builder', 'astra-sites' ) );
			}
		}

		Ai_Builder_Importer_Log::add( 'Deleted customizer Settings ' . wp_json_encode( get_option( 'astra-settings', array() ) ) );

		ST_Resetter::reset_customizer_data();

		if ( defined( 'WP_CLI' ) ) {
			\WP_CLI::line( 'Deleted Customizer Settings!' );
		} elseif ( wp_doing_ajax() ) {
			wp_send_json_success();
		}
	}

	/**
	 * Reset site options
	 *
	 * @since 1.3.0
	 * @return void
	 */
	public function reset_site_options() {

		if ( ! defined( 'WP_CLI' ) && wp_doing_ajax() ) {
			// Verify Nonce.
			check_ajax_referer( 'astra-sites', '_ajax_nonce' );

			if ( ! current_user_can( 'customize' ) ) {
				wp_send_json_error( __( 'You are not allowed to perform this action', 'ai-builder', 'astra-sites' ) );
			}
		}

		$options = get_option( '_astra_sites_old_site_options', array() );

		Ai_Builder_Importer_Log::add( 'Deleted - Site Options ' . wp_json_encode( $options ) );

		ST_Resetter::reset_site_options( $options );

		if ( defined( 'WP_CLI' ) ) {
			\WP_CLI::line( 'Deleted Site Options!' );
		} elseif ( wp_doing_ajax() ) {
			wp_send_json_success();
		}
	}

	/**
	 * Reset widgets data
	 *
	 * @since 1.3.0
	 * @return void
	 */
	public function reset_widgets_data() {

		if ( ! defined( 'WP_CLI' ) && wp_doing_ajax() ) {
			// Verify Nonce.
			check_ajax_referer( 'astra-sites', '_ajax_nonce' );

			if ( ! current_user_can( 'customize' ) ) {
				wp_send_json_error( __( 'You are not allowed to perform this action', 'ai-builder', 'astra-sites' ) );
			}
		}

		// Get all old widget ids.
		$old_widgets_data = (array) get_option( '_astra_sites_old_widgets_data', array() );

		ST_Resetter::reset_widgets_data( $old_widgets_data );

		if ( defined( 'WP_CLI' ) ) {
			\WP_CLI::line( 'Deleted Widgets!' );
		} elseif ( wp_doing_ajax() ) {
			wp_send_json_success( __( 'Deleted Widgets!', 'ai-builder', 'astra-sites' ) );
		}
	}

	/**
	 * Reset terms and forms.
	 *
	 * @since 3.0.3
	 */
	public function reset_terms_and_forms() {
		if ( wp_doing_ajax() ) {
			check_ajax_referer( 'astra-sites', '_ajax_nonce' );

			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( __( 'You are not allowed to perform this action', 'ai-builder', 'astra-sites' ) );
			}
		}

		ST_Resetter::reset_terms_and_forms();

		if ( wp_doing_ajax() ) {
			wp_send_json_success();
		}
	}

	/**
	 * Get post IDs to be deleted.
	 */
	public function get_deleted_post_ids() {
		if ( wp_doing_ajax() ) {
			check_ajax_referer( 'astra-sites', '_ajax_nonce' );

			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( __( 'You are not allowed to perform this action', 'ai-builder', 'astra-sites' ) );
			}
		}
		wp_send_json_success( astra_sites_get_reset_post_data() );
	}

	/**
	 * Download Images
	 *
	 * @since 4.1.0
	 * @return void
	 */
	public function download_selected_image() {

		check_ajax_referer( 'astra-sites', '_ajax_nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array(
					'data'   => 'You do not have permission to do this action.',
					'status' => false,

				)
			);
		}

		$index  = isset( $_POST['index'] ) ? sanitize_text_field( wp_unslash( $_POST['index'] ) ) : '';
		$images = Ai_Builder_ZipWP_Integration::get_business_details( 'images' );

		if ( empty( $images ) ) {
			wp_send_json_error(
				array(
					'data'   => 'Image not downloaded!',
					'status' => true,
				)
			);
		}

		$image = $images[ $index ];

		if ( empty( $image ) ) {
			wp_send_json_error(
				array(
					'data'   => 'Image not downloaded!',
					'status' => true,
				)
			);
		}

		$prepare_image = array(
			'id'          => $image['id'],
			'url'         => $image['url'],
			'description' => $image['description'],
		);

		Ai_Builder_Importer_Log::add( 'Downloading Image ' . $image['url'] );
		$id = ST_Importer_Helper::download_image( $prepare_image );
		Ai_Builder_Importer_Log::add( 'Downloaded Image attachment id: ' . $id );

		wp_send_json_success(
			array(
				'data'   => 'Image downloaded successfully!',
				'status' => true,
			)
		);

	}

	/**
	 * Import Customizer Settings.
	 *
	 * @since 1.0.14
	 * @since 1.4.0  The `$customizer_data` was added.
	 *
	 * @param  array $customizer_data Customizer Data.
	 * @return void
	 */
	public function import_customizer_settings( $customizer_data = array() ) {

		if ( ! defined( 'WP_CLI' ) && wp_doing_ajax() ) {
			// Verify Nonce.
			check_ajax_referer( 'astra-sites', '_ajax_nonce' );

			if ( ! current_user_can( 'customize' ) ) {
				wp_send_json_error( __( 'You are not allowed to perform this action', 'ai-builder', 'astra-sites' ) );
			}
		}

		$customizer_data = astra_get_site_data( 'astra-site-customizer-data' );

		if ( defined( 'WP_CLI' ) && empty( $customizer_data ) ) {
			\WP_CLI::line( 'Customizer data is empty!' );
		} elseif ( wp_doing_ajax() && empty( $customizer_data ) ) {
			wp_send_json_error( __( 'Customizer data is empty!', 'ai-builder', 'astra-sites' ) );
		}

		$result = ST_Importer::import_customizer_settings( $customizer_data );

		if ( false === $result['status'] ) {
			wp_send_json_error( $result['error'] );
		}

		wp_send_json_success();
	}

	/**
	 * Import Spectra Settings
	 *
	 * @since 3.1.16
	 *
	 * @param  string $url Spectra Settings JSON file URL.
	 * @return void
	 */
	public function import_spectra_settings( $url = '' ) {

		check_ajax_referer( 'astra-sites', '_ajax_nonce' );
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error(
				array(
					'error' => __( 'Permission Denied!', 'ai-builder', 'astra-sites' ),
				)
			);
		}

		$settings = astra_get_site_data( 'astra-site-spectra-options' );

		$result = ST_Importer::import_spectra_settings( $settings );

		if ( false === $result['status'] ) {
			if ( defined( 'WP_CLI' ) ) {
				\WP_CLI::line( $result['error'] );
			} elseif ( wp_doing_ajax() ) {
				wp_send_json_error( $result['error'] );
			}
		}

		if ( defined( 'WP_CLI' ) ) {
			\WP_CLI::line( 'Imported Spectra settings from ' . $url );
		} elseif ( wp_doing_ajax() ) {
			wp_send_json_success( $url );
		}
	}

	/**
	 * Import Surecart Settings
	 *
	 * @since 3.3.0
	 * @return void
	 */
	public function import_surecart_settings() {
		check_ajax_referer( 'astra-sites', '_ajax_nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'You are not allowed to perform this action', 'ai-builder', 'astra-sites' ) );
		}

		$id     = isset( $_POST['source_id'] ) ? base64_decode( sanitize_text_field( $_POST['source_id'] ) ) : ''; //phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		$result = ST_Importer::import_surecart_settings( $id );

		if ( ! is_wp_error( $result ) ) {
			wp_send_json_success( 'success' );
		}

		wp_send_json_error( __( 'There was an error cloning the surecart store.', 'ai-builder', 'astra-sites' ) );
	}

	/**
	 * Import Options.
	 *
	 * @since 1.0.14
	 * @since 1.4.0 The `$options_data` was added.
	 *
	 * @param  array $options_data Site Options.
	 * @return void
	 */
	public function import_options( $options_data = array() ) {

		if ( ! defined( 'WP_CLI' ) && wp_doing_ajax() ) {
			// Verify Nonce.
			check_ajax_referer( 'astra-sites', '_ajax_nonce' );

			if ( ! current_user_can( 'customize' ) ) {
				wp_send_json_error( __( 'You are not allowed to perform this action', 'ai-builder', 'astra-sites' ) );
			}
		}

		$options_data = astra_get_site_data( 'astra-site-options-data' );

		$result = ST_Importer::import_options( $options_data, Ai_Builder_Site_Options_Import::site_options() );

		if ( false === $result['status'] ) {
			if ( defined( 'WP_CLI' ) ) {
				\WP_CLI::line( $result['error'] );
			} elseif ( wp_doing_ajax() ) {
				wp_send_json_error( $result['error'] );
			}
		}

		if ( defined( 'WP_CLI' ) ) {
			\WP_CLI::line( 'Site options Imported!' );
		} elseif ( wp_doing_ajax() ) {
			wp_send_json_success( __( 'Site options Imported!', 'ai-builder', 'astra-sites' ) );
		}
	}

	/**
	 * Import Widgets.
	 *
	 * @since 1.0.14
	 * @since 1.4.0 The `$widgets_data` was added.
	 *
	 * @param  string $widgets_data Widgets Data.
	 * @return void
	 */
	public function import_widgets( $widgets_data = '' ) {

		if ( ! defined( 'WP_CLI' ) && wp_doing_ajax() ) {
			// Verify Nonce.
			check_ajax_referer( 'astra-sites', '_ajax_nonce' );

			if ( ! current_user_can( 'customize' ) ) {
				wp_send_json_error( __( 'You are not allowed to perform this action', 'ai-builder', 'astra-sites' ) );
			}
		}

		$data = astra_get_site_data( 'astra-site-widgets-data' );

		$result = ST_Importer::import_widgets( $widgets_data, $data );

		if ( false === $result['status'] ) {
			if ( defined( 'WP_CLI' ) ) {
				\WP_CLI::line( $result['error'] );
			} elseif ( wp_doing_ajax() ) {
				wp_send_json_error( $result['error'] );
			}
		} else {
			if ( defined( 'WP_CLI' ) ) {
				\WP_CLI::line( 'Widget Imported!' );
			} elseif ( wp_doing_ajax() ) {
				wp_send_json_success( 'Widget Imported!' );
			}
		}
	}

	/**
	 * Processing GT batch.
	 *
	 * @since 1.0.14
	 * @return void
	 */
	public function gutenberg_batch() {

		if ( ! defined( 'WP_CLI' ) && wp_doing_ajax() ) {
			// Verify Nonce.
			check_ajax_referer( 'astra-sites', '_ajax_nonce' );

			if ( ! current_user_can( 'customize' ) ) {
				wp_send_json_error( __( 'You are not allowed to perform this action', 'ai-builder', 'astra-sites' ) );
			}
		}

		$status = ST_Batch_Processing_Gutenberg::get_instance()->import();

		if ( wp_doing_ajax() ) {

			if ( $status['success'] ) {
				wp_send_json_success( $status['msg'] );
			} else {
				wp_send_json_error( $status['msg'] );
			}
		}
	}

		/**
		 * Processing GT batch.
		 *
		 * @since 1.0.14
		 * @return void
		 */
	public function image_replacement_batch() {

		if ( ! defined( 'WP_CLI' ) && wp_doing_ajax() ) {
			// Verify Nonce.
			check_ajax_referer( 'astra-sites', '_ajax_nonce' );

			if ( ! current_user_can( 'customize' ) ) {
				wp_send_json_error( __( 'You are not allowed to perform this action', 'ai-builder', 'astra-sites' ) );
			}
		}

		$status = ST_Batch_Processing_Misc::get_instance()->import();

		if ( wp_doing_ajax() ) {
			if ( $status['success'] ) {
				wp_send_json_success( $status['msg'] );
			} else {
				wp_send_json_error( $status['msg'] );
			}
		}
	}

	/**
	 * Import End.
	 *
	 * @since 1.0.14
	 * @return void
	 */
	public function import_end() {

		if ( ! defined( 'WP_CLI' ) && wp_doing_ajax() ) {
			// Verify Nonce.
			check_ajax_referer( 'astra-sites', '_ajax_nonce' );

			if ( ! current_user_can( 'customize' ) ) {
				wp_send_json_error( __( 'You are not allowed to perform this action', 'ai-builder', 'astra-sites' ) );
			}
		}

		$demo_data = ST_Importer_File_System::get_instance()->get_demo_content();
		// Set permalink structure to use post name.
		update_option( 'permalink_structure', '/%postname%/' );

		do_action( 'astra_sites_import_complete', $demo_data );

		if ( wp_doing_ajax() ) {
			wp_send_json_success();
		}
	}

	/**
	 * Set site related data.
	 *
	 * @since 3.0.0-beta.1
	 * @return void
	 */
	public function set_site_data() {

		if ( 'spectra-one' === get_option( 'stylesheet', 'astra' ) ) {
			Ai_Builder_Fse_Importer::set_fse_site_data();
			return;
		}

		check_ajax_referer( 'astra-sites', '_ajax_nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array(
					'success' => false,
					'message' => __( 'You are not authorized to perform this action.', 'ai-builder', 'astra-sites' ),
				)
			);
		}

		$param = isset( $_POST['param'] ) ? sanitize_text_field( $_POST['param'] ) : '';

		if ( empty( $param ) ) {
			wp_send_json_error(
				array(
					'error' => __( 'Received empty parameters.', 'ai-builder', 'astra-sites' ),
				)
			);
		}

		switch ( $param ) {

			case 'site-title':
					$business_name = isset( $_POST['business-name'] ) ? sanitize_text_field( stripslashes( $_POST['business-name'] ) ) : '';
				if ( ! empty( $business_name ) ) {
					update_option( 'blogname', $business_name );
				}

				break;

			case 'site-logo' === $param && function_exists( 'astra_get_option' ):
					$logo_id     = isset( $_POST['logo'] ) ? sanitize_text_field( $_POST['logo'] ) : '';
					$width_index = 'ast-header-responsive-logo-width';
					set_theme_mod( 'custom_logo', $logo_id );

				if ( ! empty( $logo_id ) ) {
					// Disable site title when logo is set.
					astra_update_option( 'display-site-title', false );
				}

					// Set logo width.
					$logo_width = isset( $_POST['logo-width'] ) ? sanitize_text_field( $_POST['logo-width'] ) : '';
					$option     = astra_get_option( $width_index );

				if ( isset( $option['desktop'] ) ) {
					$option['desktop'] = $logo_width;
				}
				astra_update_option( $width_index, $option );

				// Check if transparent header is used in the demo.
				$transparent_header = astra_get_option( 'transparent-header-logo', false );
				$inherit_desk_logo  = astra_get_option( 'different-transparent-logo', false );

				if ( '' !== $transparent_header && $inherit_desk_logo ) {
					astra_update_option( 'transparent-header-logo', wp_get_attachment_url( $logo_id ) );
					$width_index = 'transparent-header-logo-width';
					$option      = astra_get_option( $width_index );

					if ( isset( $option['desktop'] ) ) {
						$option['desktop'] = $logo_width;
					}
					astra_update_option( $width_index, $option );
				}

				$retina_logo = astra_get_option( 'different-retina-logo', false );
				if ( '' !== $retina_logo ) {
					astra_update_option( 'ast-header-retina-logo', wp_get_attachment_url( $logo_id ) );
				}

				$transparent_retina_logo = astra_get_option( 'different-transparent-retina-logo', false );
				if ( '' !== $transparent_retina_logo ) {
					astra_update_option( 'transparent-header-retina-logo', wp_get_attachment_url( $logo_id ) );
				}

				break;

			case 'site-colors' === $param && function_exists( 'astra_get_option' ) && method_exists( 'Astra_Global_Palette', 'get_default_color_palette' ):
					$palette = isset( $_POST['palette'] ) ? (array) json_decode( stripslashes( $_POST['palette'] ) ) : array();
					$colors  = isset( $palette['colors'] ) ? (array) $palette['colors'] : array();
				if ( ! empty( $colors ) ) {
					$global_palette = astra_get_option( 'global-color-palette' );
					$color_palettes = get_option( 'astra-color-palettes', \Astra_Global_Palette::get_default_color_palette() );

					foreach ( $colors as $key => $color ) {
						$global_palette['palette'][ $key ]               = $color;
						$color_palettes['palettes']['palette_1'][ $key ] = $color;
					}

					update_option( 'astra-color-palettes', $color_palettes );
					astra_update_option( 'global-color-palette', $global_palette );
				}
				break;

			case 'site-typography' === $param && function_exists( 'astra_get_option' ):
					$typography = isset( $_POST['typography'] ) ? (array) json_decode( stripslashes( $_POST['typography'] ) ) : '';

					$font_size_body = isset( $typography['font-size-body'] ) ? (array) $typography['font-size-body'] : '';
				if ( ! empty( $font_size_body ) && is_array( $font_size_body ) ) {
					astra_update_option( 'font-size-body', $font_size_body );
				}

				if ( ! empty( $typography['body-font-family'] ) ) {
					astra_update_option( 'body-font-family', $typography['body-font-family'] );
				}

				if ( ! empty( $typography['body-font-variant'] ) ) {
					astra_update_option( 'body-font-variant', $typography['body-font-variant'] );
				}

				if ( ! empty( $typography['body-font-weight'] ) ) {
					astra_update_option( 'body-font-weight', $typography['body-font-weight'] );
				}

				if ( ! empty( $typography['body-line-height'] ) ) {
					astra_update_option( 'body-line-height', $typography['body-line-height'] );
				}

				if ( ! empty( $typography['headings-font-family'] ) ) {
					astra_update_option( 'headings-font-family', $typography['headings-font-family'] );
				}

				if ( ! empty( $typography['headings-font-weight'] ) ) {
					astra_update_option( 'headings-font-weight', $typography['headings-font-weight'] );
				}

				if ( ! empty( $typography['headings-line-height'] ) ) {
					astra_update_option( 'headings-line-height', $typography['headings-line-height'] );
				}

				if ( ! empty( $typography['headings-font-variant'] ) ) {
					astra_update_option( 'headings-font-variant', $typography['headings-font-variant'] );
				}

				break;
		}

		// Clearing Cache on hostinger, Cloudways.
		Ai_Builder_Utils::third_party_cache_plugins_clear_cache();

		wp_send_json_success();
	}


}
