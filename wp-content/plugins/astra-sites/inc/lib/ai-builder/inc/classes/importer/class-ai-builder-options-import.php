<?php
/**
 * Customizer Site options importer class.
 *
 * @since  1.0.0
 * @package Astra Addon
 */

namespace AiBuilder\Inc\Classes\Importer;

use AiBuilder\Inc\Traits\Instance;

/**
 * Customizer Site options importer class.
 *
 * @since  1.0.0
 */
class Ai_Builder_Site_Options_Import {

	use Instance;

	/**
	 * Site Options
	 *
	 * @since 1.0.2
	 *
	 * @return array    List of defined array.
	 */
	public static function site_options() {
		return array(
			'custom_logo',
			'nav_menu_locations',
			'show_on_front',
			'page_on_front',
			'page_for_posts',
			'site_title',
			// Astra Theme Global Color Palette and Typography Preset options.
			'astra-color-palettes',
			'astra-typography-presets',
		);
	}

}
Ai_Builder_Site_Options_Import::Instance();
