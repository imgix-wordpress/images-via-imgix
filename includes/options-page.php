<?php

class Imgix_Options_page {

	/**
	 * The instance of the class.
	 *
	 * @var Imgix_Options_page
	 */
	protected static $instance;

	/**
	 * Plugin options
	 *
	 * @var array
	 */
	protected $options = [];


	public function __construct() {
		$this->options = get_option( 'imgix_settings', [] );
		add_action( 'admin_init', [ $this, 'imgix_register_settings' ] );
		add_action( 'admin_menu', [ $this, 'imgix_add_options_link' ] );
	}

	/**
	 * Plugin loader instance.
	 *
	 * @return Imgix_Options_page
	 */
	public static function instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	/**
	 * Renders options page
	 */
	public function imgix_options_page() {
		?>
		<div class="wrap">

			<h1>
				<img src="<?php echo plugins_url( 'assets/images/imgix-logo.png', __DIR__ ); ?>" alt="imgix Logo">
			</h1>

			<p><strong>Need help getting started?</strong> It's easy! Check out our
				<a href="https://github.com/imgix-wordpress/imgix-wordpress#getting-started" target="_blank">instructions.</a>
			</p>

			<form method="post" action="<?php echo admin_url( 'options.php' ); ?>">
				<?php settings_fields( 'imgix_settings_group' ); ?>
				<table class="form-table">
					<tbody>
						<tr>
							<th>
								<label class="description" for="imgix_settings[cdn_link]"><?php esc_html_e( 'imgix Source', 'imgix' ); ?>
							</th>
							<td>
								<input id="imgix_settings[cdn_link]" type="url" name="imgix_settings[cdn_link]" placeholder="https://yourcompany.imgix.net" value="<?php echo $this->get_option( 'cdn_link' ); ?>" class="regular-text code"/>
							</td>
						</tr>
						<tr>
							<th>
								<label class="description" for="imgix_settings[auto_format]"><?php esc_html_e( 'Auto Format Images', 'imgix' ); ?></label>
							</th>
							<td>
								<input id="imgix_settings[auto_format]" type="checkbox" name="imgix_settings[auto_format]" value="1" <?php checked( $this->get_option( 'auto_format' ) ) ?> />
							</td>
						</tr>
						<tr>
							<th>
								<label class="description" for="imgix_settings[auto_enhance]"><?php esc_html_e( 'Auto Enhance Images', 'imgix' ); ?></label>
							</th>
							<td>
								<input id="imgix_settings[auto_enhance]" type="checkbox" name="imgix_settings[auto_enhance]" value="1" <?php checked( $this->get_option( 'auto_enhance' ) ) ?> />
							</td>
						</tr>
						<tr>
							<th>
								<label class="description" for="imgix_settings[auto_compress]"><?php esc_html_e( 'Auto Compress Images', 'imgix' ); ?></label>
							</th>
							<td>
								<input id="imgix_settings[auto_compress]" type="checkbox" name="imgix_settings[auto_compress]" value="1" <?php checked( $this->get_option( 'auto_compress' ) ) ?> />
							</td>
						</tr>
						<tr>
							<th>
								<label class="description" for="imgix_settings[add_dpi2_srcset]"><?php esc_html_e( 'Automatically add retina images using srcset', 'imgix' ); ?></label>
							</th>
							<td>
								<input id="imgix_settings[add_dpi2_srcset]" type="checkbox" name="imgix_settings[add_dpi2_srcset]" value="1" <?php checked( $this->get_option( 'add_dpi2_srcset' ) ) ?> />
							</td>
						</tr>
					</tbody>
				</table>

				<p class="submit">
					<input type="submit" class="button-primary" value="<?php esc_html_e( 'Save Options', 'imgix' ); ?>"/>
				</p>
			</form>

			<p class="description">
				This plugin is powered by
				<a href="http://www.imgix.com" target="_blank">imgix</a>. You can find and contribute to the code on
				<a href="https://github.com/imgix-wordpress/images-via-imgix" target="_blank">GitHub</a>.
			</p>
		</div>
		<?php
	}

	/**
	 *  Adds link to options page in Admin > Settings menu.
	 */
	public function imgix_add_options_link() {
		add_options_page( 'imgix', 'imgix', 'manage_options', 'imgix-options', [ $this, 'imgix_options_page' ] );
	}

	/**
	 *  Creates our settings in the options table.
	 */
	public function imgix_register_settings() {
		register_setting( 'imgix_settings_group', 'imgix_settings' );
	}

	/**
	 * Get option and handle if option is not set
	 *
	 * @param string $key
	 *
	 * @return mixed
	 */
	protected function get_option( $key ) {
		return isset( $this->options[ $key ] ) ? $this->options[ $key ] : '';
	}
}

Imgix_Options_page::instance();
