<?php
/**
 * Plugin ajax actions.
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
use AiBuilder\Inc\Classes\Importer\Ai_Builder_Error_Handler;
use STImporter\Importer\ST_Importer;
use AiBuilder\Inc\Traits\Helper;
use AiBuilder\Inc\Classes\Zipwp\Ai_Builder_ZipWP_Integration;
use STImporter\Importer\ST_Importer_Helper;
/**
 * Class Flows.
 */
class Plugin extends AjaxBase {

	use Instance;

	/**
	 * Register_ajax_events.
	 *
	 * @return void
	 */
	public function register_ajax_events() {

		$ajax_events = array(
			'required_plugins',
			'required_plugin_activate',
			'filesystem_permission',
			'set_start_flag',
			'download_image',
			'report_error',
			'activate_theme',
			'site_language',
		);

		$this->init_ajax_events( $ajax_events );
	}

	/**
	 * Required Plugins
	 *
	 * @since 2.0.0
	 *
	 * @param  array $required_plugins Required Plugins.
	 * @param  array $options            Site Options.
	 * @param  array $enabled_extensions Enabled Extensions.
	 * @return mixed
	 */
	public function required_plugins( $required_plugins = array(), $options = array(), $enabled_extensions = array() ) {

		// Verify Nonce.
		if ( ! defined( 'WP_CLI' ) && wp_doing_ajax() ) {
			check_ajax_referer( 'astra-sites', '_ajax_nonce' );
			if ( ! current_user_can( 'edit_posts' ) ) {
				wp_send_json_error(
					array(
						'error' => __( 'Permission Denied!', 'ai-builder', 'astra-sites' ),
					)
				);
			}
		}

		$response = array(
			'active'       => array(),
			'inactive'     => array(),
			'notinstalled' => array(),
		);

		$required_plugins = astra_get_site_data( 'required-plugins' );

		$data = $this->get_required_plugins_data( $response, $required_plugins );

		if ( wp_doing_ajax() ) {
			wp_send_json_success( $data );
		} else {
			return $data;
		}
	}

	/**
	 * Required Plugin Activate
	 *
	 * @since 2.0.0 Added parameters $init, $options & $enabled_extensions to add the WP CLI support.
	 * @since 1.0.0
	 * @param  string $init               Plugin init file.
	 * @param  array  $options            Site options.
	 * @param  array  $enabled_extensions Enabled extensions.
	 * @return void
	 */
	public function required_plugin_activate( $init = '', $options = array(), $enabled_extensions = array() ) {

		if ( ! defined( 'WP_CLI' ) && wp_doing_ajax() ) {
			check_ajax_referer( 'astra-sites', '_ajax_nonce' );

			if ( ! current_user_can( 'install_plugins' ) || ! isset( $_POST['init'] ) || ! sanitize_text_field( $_POST['init'] ) ) {
				wp_send_json_error(
					array(
						'success' => false,
						'message' => __( 'Error: You don\'t have the required permissions to install plugins.', 'ai-builder', 'astra-sites' ),
					)
				);
			}
		}

		Ai_Builder_Error_Handler::Instance()->start_error_handler();

		$plugin_init = ( isset( $_POST['init'] ) ) ? esc_attr( sanitize_text_field( $_POST['init'] ) ) : $init;

		/**
		 * Disabled redirection to plugin page after activation.
		 * Silecing the callback for WP Live Chat plugin.
		 */
		add_filter( 'wp_redirect', '__return_false' );
		$silent = ( 'wp-live-chat-support/wp-live-chat-support.php' === $plugin_init ) ? true : false;

		$activate = activate_plugin( $plugin_init, '', false, $silent );

		Ai_Builder_Error_Handler::Instance()->stop_error_handler();

		if ( is_wp_error( $activate ) ) {
			if ( defined( 'WP_CLI' ) ) {
				\WP_CLI::error( 'Plugin Activation Error: ' . $activate->get_error_message() );
			} elseif ( wp_doing_ajax() ) {
				wp_send_json_error(
					array(
						'success' => false,
						'message' => $activate->get_error_message(),
					)
				);
			}
		}

		$options            = astra_get_site_data( 'astra-site-options-data' );
		$enabled_extensions = astra_get_site_data( 'astra-enabled-extensions' );

		$this->after_plugin_activate( $plugin_init, $options, $enabled_extensions );

		if ( defined( 'WP_CLI' ) ) {
			\WP_CLI::line( 'Plugin Activated!' );
		} elseif ( wp_doing_ajax() ) {
			wp_send_json_success(
				array(
					'success' => true,
					'message' => __( 'Plugin Activated', 'ai-builder', 'astra-sites' ),
				)
			);
		}
	}

	/**
	 * Retrieves the required plugins data based on the response and required plugin list.
	 *
	 * @param array $response            The response containing the plugin data.
	 * @param array $required_plugins    The list of required plugins.
	 * @since 3.2.5
	 * @return array                     The array of required plugins data.
	 */
	public function get_required_plugins_data( $response, $required_plugins ) {

		$learndash_course_grid = 'https://www.learndash.com/add-on/course-grid/';
		$learndash_woocommerce = 'https://www.learndash.com/add-on/woocommerce/';
		if ( is_plugin_active( 'sfwd-lms/sfwd_lms.php' ) ) {
			$learndash_addons_url  = admin_url( 'admin.php?page=learndash_lms_addons' );
			$learndash_course_grid = $learndash_addons_url;
			$learndash_woocommerce = $learndash_addons_url;
		}

		$third_party_required_plugins = array();
		$third_party_plugins          = array(
			'sfwd-lms'              => array(
				'init' => 'sfwd-lms/sfwd_lms.php',
				'name' => 'LearnDash LMS',
				'link' => 'https://www.learndash.com/',
			),
			'learndash-course-grid' => array(
				'init' => 'learndash-course-grid/learndash_course_grid.php',
				'name' => 'LearnDash Course Grid',
				'link' => $learndash_course_grid,
			),
			'learndash-woocommerce' => array(
				'init' => 'learndash-woocommerce/learndash_woocommerce.php',
				'name' => 'LearnDash WooCommerce Integration',
				'link' => $learndash_woocommerce,
			),
		);

		$plugin_updates          = get_plugin_updates();
		$update_avilable_plugins = array();
		$incompatible_plugins    = array();

		if ( ! empty( $required_plugins ) ) {
			$php_version = Helper::get_php_version();
			foreach ( $required_plugins as $key => $plugin ) {

				$plugin = (array) $plugin;

				if ( 'woocommerce' === $plugin['slug'] && version_compare( $php_version, '7.0', '<' ) ) {
					$plugin['min_php_version'] = '7.0';
					$incompatible_plugins[]    = $plugin;
				}

				if ( 'presto-player' === $plugin['slug'] && version_compare( $php_version, '7.3', '<' ) ) {
					$plugin['min_php_version'] = '7.3';
					$incompatible_plugins[]    = $plugin;
				}

				/**
				 * Has Pro Version Support?
				 * And
				 * Is Pro Version Installed?
				 */
				$plugin_pro = Helper::pro_plugin_exist( $plugin['init'] );
				if ( $plugin_pro ) {

					if ( array_key_exists( $plugin_pro['init'], $plugin_updates ) ) {
						$update_avilable_plugins[] = $plugin_pro;
					}

					// Pro - Active.
					if ( is_plugin_active( $plugin_pro['init'] ) ) {
						$response['active'][] = $plugin_pro;

						$this->after_plugin_activate( $plugin['init'] );

						// Pro - Inactive.
					} else {
						$response['inactive'][] = $plugin_pro;
					}
				} else {
					if ( array_key_exists( $plugin['init'], $plugin_updates ) ) {
						$update_avilable_plugins[] = $plugin;
					}

					// Lite - Installed but Inactive.
					if ( file_exists( WP_PLUGIN_DIR . '/' . $plugin['init'] ) && is_plugin_inactive( $plugin['init'] ) ) {
						$link                   = wp_nonce_url(
							add_query_arg(
								array(
									'action' => 'activate',
									'plugin' => $plugin['init'],
								),
								admin_url( 'plugins.php' )
							),
							'activate-plugin_' . $plugin['init']
						);
						$link                   = str_replace( '&amp;', '&', $link );
						$plugin['action']       = $link;
						$response['inactive'][] = $plugin;

						// Lite - Not Installed.
					} elseif ( ! file_exists( WP_PLUGIN_DIR . '/' . $plugin['init'] ) ) {

						// Added premium plugins which need to install first.
						if ( array_key_exists( $plugin['slug'], $third_party_plugins ) ) {
							$third_party_required_plugins[] = $third_party_plugins[ $plugin['slug'] ];
						} else {
							$link                       = wp_nonce_url(
								add_query_arg(
									array(
										'action' => 'install-plugin',
										'plugin' => $plugin['slug'],
									),
									admin_url( 'update.php' )
								),
								'install-plugin_' . $plugin['slug']
							);
							$link                       = str_replace( '&amp;', '&', $link );
							$plugin['action']           = $link;
							$response['notinstalled'][] = $plugin;
						}

						// Lite - Active.
					} else {
						$response['active'][] = $plugin;

						$this->after_plugin_activate( $plugin['init'] );
					}
				}
			}
		}

		// Checking the `install_plugins` and `activate_plugins` capability for the current user.
		// To perform plugin installation process.
		if (
			( ! defined( 'WP_CLI' ) && wp_doing_ajax() ) &&
			( ( ! current_user_can( 'install_plugins' ) && ! empty( $response['notinstalled'] ) ) || ( ! current_user_can( 'activate_plugins' ) && ! empty( $response['inactive'] ) ) ) ) {
			$message               = __( 'Insufficient Permission. Please contact your Super Admin to allow the install required plugin permissions.', 'ai-builder', 'astra-sites' );
			$required_plugins_list = array_merge( $response['notinstalled'], $response['inactive'] );
			$markup                = $message;
			$markup               .= '<ul>';
			foreach ( $required_plugins_list as $key => $required_plugin ) {
				$markup .= '<li>' . esc_html( $required_plugin['name'] ) . '</li>';
			}
			$markup .= '</ul>';

			wp_send_json_error( $markup );
		}

		$data = array(
			'required_plugins'             => $response,
			'third_party_required_plugins' => $third_party_required_plugins,
			'update_avilable_plugins'      => $update_avilable_plugins,
			'incompatible_plugins'         => $incompatible_plugins,
		);

		return $data;
	}

	/**
	 * After Plugin Activate
	 *
	 * @since 2.0.0
	 *
	 * @param  string $plugin_init        Plugin Init File.
	 * @param  array  $options            Site Options.
	 * @param  array  $enabled_extensions Enabled Extensions.
	 * @return void
	 */
	public function after_plugin_activate( $plugin_init = '', $options = array(), $enabled_extensions = array() ) {
		$data = array(
			'astra_site_options' => $options,
			'enabled_extensions' => $enabled_extensions,
		);

		do_action( 'astra_sites_after_plugin_activation', $plugin_init, $data );
	}

	/**
	 * Get the status of file system permission of "/wp-content/uploads" directory.
	 *
	 * @return void
	 */
	public function filesystem_permission() {
		if ( ! defined( 'WP_CLI' ) && wp_doing_ajax() ) {
			check_ajax_referer( 'astra-sites', '_ajax_nonce' );

			if ( ! current_user_can( 'customize' ) ) {
				wp_send_json_error( __( 'You do not have permission to perform this action.', 'ai-builder', 'astra-sites' ) );
			}
		}
		$wp_upload_path = wp_upload_dir();
		$permissions    = array(
			'is_readable' => false,
			'is_writable' => false,
		);

		foreach ( $permissions as $file_permission => $value ) {
			$permissions[ $file_permission ] = $file_permission( $wp_upload_path['basedir'] );
		}

		$permissions['is_wp_filesystem'] = true;
		if ( ! WP_Filesystem() ) {
			$permissions['is_wp_filesystem'] = false;
		}

		if ( defined( 'WP_CLI' ) ) {
			if ( ! $permissions['is_readable'] || ! $permissions['is_writable'] || ! $permissions['is_wp_filesystem'] ) {
				\WP_CLI::error( esc_html__( 'Please contact the hosting service provider to help you update the permissions so that you can successfully import a complete template.', 'ai-builder', 'astra-sites' ) );
			}
		} else {
			wp_send_json_success(
				array(
					'permissions' => $permissions,
					'directory'   => $wp_upload_path['basedir'],
				)
			);
		}
	}

	/**
	 * Set a flag that indicates the import process has started.
	 */
	public function set_start_flag() {
		if ( ! defined( 'WP_CLI' ) && wp_doing_ajax() ) {
			// Verify Nonce.
			check_ajax_referer( 'astra-sites', '_ajax_nonce' );

			if ( ! current_user_can( 'customize' ) ) {
				wp_send_json_error( __( 'You are not allowed to perform this action', 'ai-builder', 'astra-sites' ) );
			}
		}
		$uuid = isset( $_POST['uuid'] ) ? sanitize_text_field( $_POST['uuid'] ) : '';
		ST_Importer::set_import_process_start_flag( $uuid );
		wp_send_json_success();
	}


	/**
	 * Download File Into Uploads Directory
	 *
	 * @since 2.1.0 Added $overrides argument to override the uploaded file actions.
	 *
	 * @param  string $file Download File URL.
	 * @param  array  $overrides Upload file arguments.
	 * @param  int    $timeout_seconds Timeout in downloading the XML file in seconds.
	 * @return array        Downloaded file data.
	 */
	public static function download_file( $file = '', $overrides = array(), $timeout_seconds = 300 ) {

		// Gives us access to the download_url() and wp_handle_sideload() functions.
		require_once ABSPATH . 'wp-admin/includes/file.php';

		// Download file to temp dir.
		$temp_file = download_url( $file, $timeout_seconds );

		// WP Error.
		if ( is_wp_error( $temp_file ) ) {
			return array(
				'success' => false,
				'data'    => $temp_file->get_error_message(),
			);
		}

		// Array based on $_FILE as seen in PHP file uploads.
		$file_args = array(
			'name'     => basename( $file ),
			'tmp_name' => $temp_file,
			'error'    => 0,
			'size'     => filesize( $temp_file ),
		);

		$defaults = array(

			// Tells WordPress to not look for the POST form
			// fields that would normally be present as
			// we downloaded the file from a remote server, so there
			// will be no form fields
			// Default is true.
			'test_form'   => false,

			// Setting this to false lets WordPress allow empty files, not recommended.
			// Default is true.
			'test_size'   => true,

			// A properly uploaded file will pass this test. There should be no reason to override this one.
			'test_upload' => true,

			'mimes'       => array(
				'xml'  => 'text/xml',
				'json' => 'text/plain',
			),
		);

		$overrides = wp_parse_args( $overrides, $defaults );

		// Move the temporary file into the uploads directory.
		$results = wp_handle_sideload( $file_args, $overrides );

		astra_sites_error_log( wp_json_encode( $results ) );

		if ( isset( $results['error'] ) ) {
			return array(
				'success' => false,
				'data'    => $results,
			);
		}

		// Success.
		return array(
			'success' => true,
			'data'    => $results,
		);
	}

	/**
	 * Download Images
	 *
	 * @since 4.1.0
	 * @return void
	 */
	public function download_image() {

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
			wp_send_json_success(
				array(
					'data'   => 'No images selected to download!',
					'status' => true,
				)
			);
		}

		$image = $images[ $index ];

		if ( empty( $image ) ) {
			wp_send_json_success(
				array(
					'data'   => 'No image to download!',
					'status' => true,
				)
			);
		}

		$prepare_image = array(
			'id'          => $image['id'],
			'url'         => $image['url'],
			'description' => isset( $image['description'] ) ? $image['description'] : '',
		);

		$id = ST_Importer_Helper::download_image( $prepare_image );

		wp_send_json_success(
			array(
				'data'   => 'Image downloaded successfully!',
				'status' => true,
			)
		);

	}

	/**
	 * Report Error.
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public function report_error() {

		check_ajax_referer( 'astra-sites', '_ajax_nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array(
					'data'   => 'You do not have permission to do this action.',
					'status' => false,

				)
			);
		}

		$api_url = add_query_arg( [], trailingslashit( ST_Importer_Helper::get_api_domain() ) . 'wp-json/starter-templates/v2/import-error/' );

		if ( ! astra_sites_is_valid_url( $api_url ) ) {
			wp_send_json_error(
				array(
					/* Translators: %s is URL. */
					'message' => sprintf( __( 'Invalid URL - %s', 'ai-builder', 'astra-sites' ), $api_url ),
					'code'    => 'Error',
				)
			);
		}

		$post_id           = ( isset( $_POST['id'] ) ) ? intval( $_POST['id'] ) : 0;
		$user_agent_string = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] ) : '';

		if ( 0 === $post_id ) {
			wp_send_json_error(
				array(
					'message' => sprintf(
						/* translators: %d is the post ID */
						__( 'Invalid Post ID - %d', 'ai-builder', 'astra-sites' ),
						$post_id
					),
					'code'    => 'Error',
				)
			);
		}

		$api_args = array(
			'timeout'  => 3,
			'blocking' => true,
			'body'     => array(
				'url'        => esc_url( site_url() ),
				'err'        => stripslashes( $_POST['error'] ),
				'id'         => $_POST['id'],
				'logfile'    => $this->get_log_file_path(),
				'version'    => AI_BUILDER_VER,
				'abspath'    => ABSPATH,
				'user_agent' => $user_agent_string,
				'server'     => array(
					'php_version'            => Helper::get_php_version(),
					'php_post_max_size'      => ini_get( 'post_max_size' ),
					'php_max_execution_time' => ini_get( 'max_execution_time' ),
					'max_input_time'         => ini_get( 'max_input_time' ),
					'php_memory_limit'       => ini_get( 'memory_limit' ),
					'php_max_input_vars'     => ini_get( 'max_input_vars' ), // phpcs:ignore:PHPCompatibility.IniDirectives.NewIniDirectives.max_input_varsFound
				),
			),
		);

		do_action( 'st_before_sending_error_report', $api_args['body'] );

		$request = wp_safe_remote_post( $api_url, $api_args );

		do_action( 'st_after_sending_error_report', $api_args['body'], $request );

		if ( is_wp_error( $request ) ) {
			wp_send_json_error( $request );
		}

		$code = (int) wp_remote_retrieve_response_code( $request );
		$data = json_decode( wp_remote_retrieve_body( $request ), true );

		if ( 200 === $code ) {
			wp_send_json_success( $data );
		}

		wp_send_json_error( $data );
	}

	/**
	 * Get full path of the created log file.
	 *
	 * @return string File Path.
	 * @since 3.0.25
	 */
	public function get_log_file_path() {
		$log_file = get_option( 'astra_sites_recent_import_log_file', false );
		if ( ! empty( $log_file ) && isset( $log_file ) ) {
			return str_replace( ABSPATH, esc_url( site_url() ) . '/', $log_file );
		}

		return '';
	}

	/**
	 * Activate theme
	 *
	 * @since 1.3.2
	 * @return void
	 */
	public function activate_theme() {

		// Verify Nonce.
		check_ajax_referer( 'astra-sites', '_ajax_nonce' );

		if ( ! current_user_can( 'customize' ) ) {
			wp_send_json_error( __( 'You are not allowed to perform this action', 'ai-builder', 'astra-sites' ) );
		}

		Ai_Builder_Error_Handler::Instance()->start_error_handler();

		switch_theme( 'astra' );

		Ai_Builder_Error_Handler::Instance()->stop_error_handler();

		wp_send_json_success(
			array(
				'success' => true,
				'message' => __( 'Theme Activated', 'ai-builder', 'astra-sites' ),
			)
		);
	}
	/**
	 * Set site language.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function site_language() {

		if ( ! defined( 'WP_CLI' ) && wp_doing_ajax() ) {
			// Verify Nonce.
			check_ajax_referer( 'astra-sites', '_ajax_nonce' );

			if ( ! current_user_can( 'customize' ) ) {
				wp_send_json_error( __( 'You are not allowed to perform this action', 'ai-builder', 'astra-sites' ) );
			}
		}

		if ( ! wp_doing_ajax() ) {
			wp_send_json_error( __( 'You are not allowed to perform this action', 'ai-builder', 'astra-sites' ) );
		}

		$language = isset( $_POST['language'] ) ? sanitize_text_field( $_POST['language'] ) : 'en_US';
		$result   = $this->set_language( $language );

		if ( ! $result ) {
			wp_send_json_error( __( 'Failed to set the site language.', 'ai-builder', 'astra-sites' ) );
		}

		wp_send_json_success();
	}

	/**
	 * Set the site language.
	 *
	 * @since 1.0.0
	 *
	 * @param string $language  The language code.
	 * @return bool
	 */
	public function set_language( $language = 'en_US' ) {
		require_once ABSPATH . 'wp-admin/includes/translation-install.php';

		$locale_code = 'en_US' === $language ? '' : $language;
		if ( '' !== $locale_code && wp_can_install_language_pack() ) {
			$language = wp_download_language_pack( $locale_code );
		}
		if ( ( '' === $locale_code ) || ( '' !== $locale_code && $language ) ) {
			update_option( 'WPLANG', $locale_code );
			load_default_textdomain( $locale_code );
			return switch_to_locale( $locale_code );
		}

		return false;
	}
}
