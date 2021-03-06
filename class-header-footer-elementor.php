<?php
/**
 * Entry point for the plugin. Checks if Elementor is installed and activated and loads it's own files and actions.
 *
 * @package  header-footer-elementor
 */

/**
 * Class Header_Footer_Elementor
 */
class Header_Footer_Elementor {

	/**
	 * Current theme template
	 *
	 * @var String
	 */
	public $template;

	/**
	 * Instance of Elemenntor Frontend class.
	 *
	 * @var \Elementor\Frontend()
	 */
	private static $elementor_frontend;

	/**
	 * Constructor
	 */
	function __construct() {

		$this->template = get_template();

		if ( defined( 'ELEMENTOR_VERSION' ) ) {

			self::$elementor_frontend = new \Elementor\Frontend();

			$this->includes();
			$this->load_textdomain();

			if ( 'genesis' == $this->template ) {

				require HFE_DIR . 'themes/genesis/class-genesis-compat.php';
			} elseif ( 'bb-theme' == $this->template || 'beaver-builder-theme' == $this->template ) {
				$this->template = 'beaver-builder-theme';
				require HFE_DIR . 'themes/bb-theme/class-bb-theme-compat.php';
			} elseif ( 'generatepress' == $this->template ) {

				require HFE_DIR . 'themes/generatepress/generatepress-compat.php';
			} else {

				add_action( 'admin_notices', array( $this, 'unsupported_theme' ) );
				add_action( 'network_admin_notices', array( $this, 'unsupported_theme' ) );
			}

			// Scripts and styles.
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
			add_filter( 'body_class', array( $this, 'body_class' ) );

		} else {

			add_action( 'admin_notices', array( $this, 'elementor_not_available' ) );
			add_action( 'network_admin_notices', array( $this, 'elementor_not_available' ) );
		}
	}

	/**
	 * Prints the admin notics when Elementor is not installed or activated.
	 */
	public function elementor_not_available() {

		if ( file_exists(  WP_PLUGIN_DIR . '/elementor/elementor.php' ) ) {
			$url = network_admin_url() . 'plugins.php?s=elementor';
		} else {
			$url = network_admin_url() . 'plugin-install.php?s=elementor';
		}

		echo '<div class="notice notice-error">';
		echo '<p>' . sprintf( __( 'The <strong>Header Footer Elementor</strong> plugin requires <strong><a href="%s">Elementor</strong></a> plugin installed & activated.', 'header-footer-elementor' ) . '</p>', $url );
		echo '</div>';
	}

	/**
	 * Loads the globally required files for the plugin.
	 */
	public function includes() {
		require_once HFE_DIR . 'admin/class-hfe-admin-ui.php';
	}

	/**
	 * Loads textdomain for the plugin.
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'header-footer-elementor' );
	}

	/**
	 * Enqueue styles and scripts.
	 */
	public function enqueue_scripts() {
		wp_enqueue_style( 'hfe-style', HFE_URL . 'assets/css/header-footer-elementor.css', array(), HFE_VER );
	}

	/**
	 * Adds classes to the body tag conditionally.
	 *
	 * @param  Array $classes array with class names for the body tag.
	 *
	 * @return Array          array with class names for the body tag.
	 */
	public function body_class( $classes ) {

		$header_id             = Header_Footer_Elementor::get_settings( 'type_header', '' );
		$footer_id             = Header_Footer_Elementor::get_settings( 'type_footer', '' );

		if ( '' !== $header_id ) {
			$classes[] = 'ehf-header';
		}

		if ( '' !== $footer_id ) {
			$classes[] = 'ehf-footer';
		}

		$classes[] = 'ehf-template-' . $this->template;
		$classes[] = 'ehf-stylesheet-' . get_stylesheet();

		return $classes;
	}

	/**
	 * Prints an admin notics oif the currently installed theme is not supported by header-footer-elementor.
	 */
	public function unsupported_theme() {
		$class   = 'notice notice-error';
		$message = __( 'Hey, your current theme is not supported by Header Footer Elementor, click <a href="https://github.com/Nikschavan/header-footer-elementor#which-themes-are-supported-by-this-plugin">here</a> to check out the supported themes.', 'header-footer-elementor' );

		printf( '<div class="%1$s"><p>%2$s</p></div>', $class, $message );
	}

	/**
	 * Prints the Header content.
	 */
	public static function get_header_content() {
		$header_id = Header_Footer_Elementor::get_settings( 'type_header', '' );
		echo self::$elementor_frontend->get_builder_content_for_display( $header_id );
	}

	/**
	 * Prints the Footer content.
	 */
	public static function get_footer_content() {

		$footer_id = Header_Footer_Elementor::get_settings( 'type_footer', '' );
		echo "<div class='footer-width-fixer'>";
		echo self::$elementor_frontend->get_builder_content_for_display( $footer_id );
		echo '</div>';
	}

	/**
	 * Get option for the plugin settings
	 *
	 * @param  mixed $setting Option name.
	 * @param  mixed $default Default value to be received if the option value is not stored in the option.
	 *
	 * @return mixed.
	 */
	public static function get_settings( $setting = '', $default = '' ) {
		if ( 'type_header' == $setting || 'type_footer' == $setting ) {
			$templates = self::get_template_id( $setting );

			return is_array( $templates ) ? $templates[0] : '';
		}
	}

	/**
	 * Get header or footer template id based on the meta query.
	 *
	 * @param  String $type Type of the template header/footer.
	 *
	 * @return Mixed       Returns the header or footer template id if found, else returns string ''.
	 */
	public static function get_template_id( $type ) {

		$cached = wp_cache_get( $type );
		
		if ( false !== $cached ) {
			return $cached;
		}

		$template = new WP_Query( array(
			'post_type'    => 'elementor-hf',
			'meta_key'     => 'ehf_template_type',
			'meta_value'   => $type,
			'meta_type'    => 'post',
			'meta_compare' => '>=',
			'orderby'      => 'meta_value',
			'order'        => 'ASC',
			'meta_query'   => array(
				'relation' => 'OR',
				array(
					'key'     => 'ehf_template_type',
					'value'   => $type,
					'compare' => '==',
					'type'    => 'post',
				)
			),
		) );

		if ( $template->have_posts() ) {
			$posts = wp_list_pluck( $template->posts, 'ID' );
			wp_cache_set( $type, $posts );

			return $posts;
		}

		return '';
	}

}
