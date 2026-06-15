<?php
/**
 * SettingsPage — the "Chada Duplicate" settings screen (design handoff, Screen C).
 *
 * Native Settings API: one option (`cdup_settings`) with four sections — post
 * types, copy behaviour, excluded meta keys, and permissions. Also adds the
 * "Settings" action link on the Plugins screen.
 *
 * @package Chada\Duplicate
 */

namespace Chada\Duplicate\Admin;

use Chada\Duplicate\Duplicator;
use Chada\Duplicate\Settings;

defined( 'ABSPATH' ) || exit;

class SettingsPage {

	const MENU_SLUG     = 'chada-duplicate';
	const SETTING_GROUP = 'cdup_settings_group';
	const CAPABILITY    = 'manage_options';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_filter( 'plugin_action_links_' . CHADA_DUP_BASENAME, array( $this, 'add_settings_link' ) );
	}

	/**
	 * Add the settings page under Settings.
	 *
	 * @return void
	 */
	public function add_menu() {
		add_options_page(
			__( 'Chada Duplicate', 'chada-duplicate' ),
			__( 'Chada Duplicate', 'chada-duplicate' ),
			self::CAPABILITY,
			self::MENU_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Add a "Settings" link to the plugin's row on the Plugins screen.
	 *
	 * @param string[] $links Existing action links.
	 * @return string[]
	 */
	public function add_settings_link( $links ) {
		$settings_link = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'options-general.php?page=' . self::MENU_SLUG ) ),
			esc_html__( 'Settings', 'chada-duplicate' )
		);
		array_unshift( $links, $settings_link );
		return $links;
	}

	/**
	 * Register the setting, sections, and fields.
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			self::SETTING_GROUP,
			Settings::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => Settings::defaults(),
			)
		);

		add_settings_section( 'cdup_post_types', __( 'Post types', 'chada-duplicate' ), array( $this, 'section_post_types' ), self::MENU_SLUG );
		add_settings_field( 'cdup_post_types_field', __( 'Enable on', 'chada-duplicate' ), array( $this, 'field_post_types' ), self::MENU_SLUG, 'cdup_post_types' );

		add_settings_section( 'cdup_copy_behavior', __( 'Copy behavior', 'chada-duplicate' ), '__return_false', self::MENU_SLUG );
		add_settings_field( 'cdup_default_status', __( 'Default status', 'chada-duplicate' ), array( $this, 'field_default_status' ), self::MENU_SLUG, 'cdup_copy_behavior' );
		add_settings_field( 'cdup_title_suffix', __( 'Title suffix', 'chada-duplicate' ), array( $this, 'field_title_suffix' ), self::MENU_SLUG, 'cdup_copy_behavior' );
		add_settings_field( 'cdup_also_copy', __( 'Also copy', 'chada-duplicate' ), array( $this, 'field_also_copy' ), self::MENU_SLUG, 'cdup_copy_behavior' );

		add_settings_section( 'cdup_excluded_meta', __( 'Excluded meta keys', 'chada-duplicate' ), array( $this, 'section_excluded_meta' ), self::MENU_SLUG );
		add_settings_field( 'cdup_excluded_meta_field', __( 'Meta keys to skip', 'chada-duplicate' ), array( $this, 'field_excluded_meta' ), self::MENU_SLUG, 'cdup_excluded_meta' );

		add_settings_section( 'cdup_permissions', __( 'Permissions', 'chada-duplicate' ), array( $this, 'section_permissions' ), self::MENU_SLUG );
		add_settings_field( 'cdup_roles_field', __( 'Roles allowed to duplicate', 'chada-duplicate' ), array( $this, 'field_roles' ), self::MENU_SLUG, 'cdup_permissions' );
	}

	/**
	 * Render the settings page wrapper.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Chada Duplicate', 'chada-duplicate' ); ?></h1>
			<form action="options.php" method="post">
				<?php
				settings_fields( self::SETTING_GROUP );
				do_settings_sections( self::MENU_SLUG );
				submit_button();
				?>
			</form>
			<?php
			/**
			 * Fires after the settings form on the Chada Duplicate settings page.
			 * Used by the "More by Chada" cross-sell panel.
			 */
			do_action( 'cdup_after_settings_form' );
			?>
		</div>
		<?php
	}

	/* ---------- Section intros ---------- */

	public function section_post_types() {
		echo '<p class="description">' . esc_html__( 'Show the Duplicate action on these content types.', 'chada-duplicate' ) . '</p>';
	}

	public function section_excluded_meta() {
		echo '<p class="description">' . esc_html__( 'Custom fields (post meta) with these keys are not copied. One key per line.', 'chada-duplicate' ) . '</p>';
	}

	public function section_permissions() {
		echo '<p class="description">' . esc_html__( 'Only users with one of these roles see the Duplicate action. Administrators always can.', 'chada-duplicate' ) . '</p>';
	}

	/* ---------- Fields ---------- */

	public function field_post_types() {
		$enabled = (array) Settings::get( 'post_types' );
		foreach ( Duplicator::candidate_post_types() as $slug => $post_type_object ) {
			printf(
				'<label style="display:block;margin:0 0 6px;"><input type="checkbox" name="%1$s[post_types][]" value="%2$s" %3$s> %4$s</label>',
				esc_attr( Settings::OPTION ),
				esc_attr( $slug ),
				checked( in_array( $slug, $enabled, true ), true, false ),
				esc_html( $post_type_object->labels->name )
			);
		}
	}

	public function field_default_status() {
		$current = Settings::get( 'default_status' );
		$choices = array(
			'draft'   => __( 'Draft', 'chada-duplicate' ),
			'inherit' => __( 'Same as original', 'chada-duplicate' ),
		);
		foreach ( $choices as $value => $label ) {
			printf(
				'<label style="margin-right:16px;"><input type="radio" name="%1$s[default_status]" value="%2$s" %3$s> %4$s</label>',
				esc_attr( Settings::OPTION ),
				esc_attr( $value ),
				checked( $current, $value, false ),
				esc_html( $label )
			);
		}
	}

	public function field_title_suffix() {
		printf(
			'<input type="text" class="regular-text" name="%1$s[title_suffix]" value="%2$s"> <p class="description">%3$s</p>',
			esc_attr( Settings::OPTION ),
			esc_attr( (string) Settings::get( 'title_suffix' ) ),
			esc_html__( 'Appended to the cloned title, e.g. " (copy)".', 'chada-duplicate' )
		);
	}

	public function field_also_copy() {
		$this->checkbox( 'copy_author', __( 'Author', 'chada-duplicate' ) );
		$this->checkbox( 'copy_comments', __( 'Comments', 'chada-duplicate' ) );
		if ( function_exists( 'wc_get_product' ) ) {
			$this->checkbox( 'copy_price', __( 'Product price', 'chada-duplicate' ) );
		}
	}

	public function field_excluded_meta() {
		$keys = (array) Settings::get( 'excluded_meta' );
		printf(
			'<textarea class="large-text code" rows="5" name="%1$s[excluded_meta]">%2$s</textarea>',
			esc_attr( Settings::OPTION ),
			esc_textarea( implode( "\n", $keys ) )
		);
	}

	public function field_roles() {
		$allowed = (array) Settings::get( 'allowed_roles' );
		foreach ( wp_roles()->get_names() as $slug => $label ) {
			$is_admin = ( 'administrator' === $slug );
			printf(
				'<label style="display:block;margin:0 0 6px;"><input type="checkbox" name="%1$s[allowed_roles][]" value="%2$s" %3$s %4$s> %5$s</label>',
				esc_attr( Settings::OPTION ),
				esc_attr( $slug ),
				checked( $is_admin || in_array( $slug, $allowed, true ), true, false ),
				disabled( $is_admin, true, false ),
				esc_html( translate_user_role( $label ) )
			);
		}
		echo '<p class="description">' . esc_html__( 'Administrators always have access.', 'chada-duplicate' ) . '</p>';
	}

	/**
	 * Render a single boolean checkbox for the given setting key.
	 *
	 * @param string $key   Setting key.
	 * @param string $label Visible label.
	 * @return void
	 */
	private function checkbox( $key, $label ) {
		printf(
			'<label style="margin-right:16px;"><input type="checkbox" name="%1$s[%2$s]" value="1" %3$s> %4$s</label>',
			esc_attr( Settings::OPTION ),
			esc_attr( $key ),
			checked( (bool) Settings::get( $key ), true, false ),
			esc_html( $label )
		);
	}

	/**
	 * Sanitize the submitted settings into a clean, typed array.
	 *
	 * @param mixed $input Raw submitted value.
	 * @return array<string,mixed>
	 */
	public function sanitize( $input ) {
		$input     = is_array( $input ) ? $input : array();
		$defaults  = Settings::defaults();
		$candidate = array_keys( Duplicator::candidate_post_types() );
		$roles     = array_keys( wp_roles()->get_names() );

		$clean = array();

		// Post types — only known candidates.
		$clean['post_types'] = array_values(
			array_intersect( $candidate, isset( $input['post_types'] ) ? array_map( 'sanitize_key', (array) $input['post_types'] ) : array() )
		);

		// Default status.
		$clean['default_status'] = ( isset( $input['default_status'] ) && 'inherit' === $input['default_status'] ) ? 'inherit' : 'draft';

		// Title suffix — strip tags and line breaks but PRESERVE spaces (the
		// leading space in " (copy)" is meaningful; sanitize_text_field would trim it).
		$raw_suffix            = isset( $input['title_suffix'] ) ? (string) $input['title_suffix'] : $defaults['title_suffix'];
		$raw_suffix            = wp_kses( $raw_suffix, array() );
		$clean['title_suffix'] = str_replace( array( "\r", "\n", "\t" ), ' ', $raw_suffix );

		// Booleans.
		$clean['copy_author']   = ! empty( $input['copy_author'] );
		$clean['copy_comments'] = ! empty( $input['copy_comments'] );
		$clean['copy_price']    = ! empty( $input['copy_price'] );

		// Excluded meta keys — one per line. WordPress calls this sanitizer twice
		// on the first save, the second time with the already-array result, so
		// accept either a textarea string or an array.
		$raw_meta = isset( $input['excluded_meta'] ) ? $input['excluded_meta'] : '';
		$lines    = is_array( $raw_meta ) ? $raw_meta : preg_split( '/\r\n|\r|\n/', (string) $raw_meta );
		$lines    = array_filter( array_map( 'trim', (array) $lines ) );
		$clean['excluded_meta'] = array_values( array_unique( array_map( 'sanitize_text_field', $lines ) ) );

		// Allowed roles — only known roles; administrator is always included.
		$submitted_roles      = isset( $input['allowed_roles'] ) ? array_map( 'sanitize_key', (array) $input['allowed_roles'] ) : array();
		$submitted_roles[]    = 'administrator';
		$clean['allowed_roles'] = array_values( array_intersect( $roles, array_unique( $submitted_roles ) ) );

		return $clean;
	}
}
