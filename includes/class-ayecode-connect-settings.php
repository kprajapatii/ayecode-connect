<?php
/**
 * A settings class for AyeCode Connect.
 */

/**
 * Bail if we are not in WP.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


if ( ! class_exists( 'AyeCode_Connect_Settings' ) ) {

	/**
	 * The settings for AyeCode Connect
	 */
	class AyeCode_Connect_Settings {
		/**
		 * The title.
		 *
		 * @var string
		 */
		public $name = 'AyeCode Connect';

		/**
		 * The relative url to the assets.
		 *
		 * @var string
		 */
		public $url = '';

		/**
		 * The AyeCode_Connect instance.
		 * @var
		 */
		public $client;

		/**
		 * The base url of the plugin.
		 * 
		 * @var
		 */
		public $base_url;

		/**
		 * AyeCode_UI_Settings instance.
		 *
		 * @access private
		 * @since  1.0.0
		 * @var    AyeCode_Connect_Settings There can be only one!
		 */
		private static $instance = null;

		/**
		 * Main AyeCode_Connect_Settings Instance.
		 *
		 * Ensures only one instance of AyeCode_Connect_Settings is loaded or can be loaded.
		 *
		 * @since 1.0.0
		 * @static
		 * @return AyeCode_Connect_Settings - Main instance.
		 */
		public static function instance() {
			if ( ! isset( self::$instance ) && ! ( self::$instance instanceof AyeCode_Connect_Settings ) ) {
				self::$instance = new AyeCode_Connect_Settings;

				$args                     = ayecode_connect_args();
				self::$instance->client   = new AyeCode_Connect( $args );

				if ( is_admin() ) {
					add_action( 'admin_menu', array( self::$instance, 'menu_item' ) );


					self::$instance->base_url = str_replace( "/includes/../", "/", plugins_url( '../', __FILE__ ) );

					// ajax
					add_action( 'wp_ajax_ayecode_connect_updates', array( self::$instance, 'ajax_toggle_updates' ) );
					add_action( 'wp_ajax_ayecode_connect_disconnect', array( self::$instance, 'ajax_disconnect_site' ) );
					add_action( 'wp_ajax_ayecode_connect_licences', array( self::$instance, 'ajax_toggle_licences' ) );

				}

				// cron, this needs to be outside the is_admin() check.
				add_action( self::$instance->client->prefix . "_callback", array(
					self::$instance,
					'cron_callback'
				), 10 );

				do_action( 'ayecode_connect_settings_loaded' );
			}

			return self::$instance;
		}

		/**
		 * The Cron callback to run checks.
		 */
		public function cron_callback() {

			// check we are registered
			if ( $this->client->is_registered() && defined( 'WP_EASY_UPDATES_ACTIVE' ) ) {

				// licence sync
				if ( get_option( $this->client->prefix . "_licence_sync" ) ) {
					// Sync licences now
					$this->client->sync_licences();
				}
			}
		}

		/**
		 * Disconnect site via ajax call.
		 */
		public function ajax_disconnect_site() {
			// security
			check_ajax_referer( 'ayecode-connect', 'security' );
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( - 1 );
			}

			$result = $this->client->disconnect_site();

			if ( ! is_wp_error( $result ) ) {
				wp_send_json_success();
			} else {
				wp_send_json_error();
			}

			wp_die();
		}

		/**
		 * Toggle updates via ajax.
		 */
		public function ajax_toggle_updates() {

			// security
			check_ajax_referer( 'ayecode-connect', 'security' );
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( - 1 );
			}

			$success = true;
			$state   = isset( $_POST['state'] ) && $_POST['state'] ? true : false;
			$plugin  = 'wp-easy-updates/external-updates.php';
			if ( $state ) { // enable
				$installed_plugins = array_map( array( $this, 'format_plugin_slug' ), array_keys( get_plugins() ) );

				if ( in_array( 'external-updates', $installed_plugins ) ) {
					$result = activate_plugin( $plugin );

					if ( is_wp_error( $result ) ) {
						$success = false;
					}
				} else {// request
					$result = $this->client->request_updates();
					if ( is_wp_error( $result ) ) {
						$success = false;
					}
				}

			} else { // disable
				$result = deactivate_plugins( $plugin );
				if ( is_wp_error( $result ) ) {
					$success = false;
				}
			}


			if ( $success ) {
				wp_send_json_success();
			} else {
				wp_send_json_error();
			}

			wp_clean_plugins_cache();

			wp_die();
		}

		/**
		 * Toggle licences via ajax call.
		 */
		public function ajax_toggle_licences() {

			// security
			check_ajax_referer( 'ayecode-connect', 'security' );
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( - 1 );
			}

			$success = true;
			$state   = isset( $_POST['state'] ) && $_POST['state'] ? true : false;

			if ( $state ) { // enable

				// sanity check
				if(!defined( 'WP_EASY_UPDATES_ACTIVE' )){
					wp_send_json_error(__("Plugin and theme update notifications must be enabled first","ayecode-connect"));
				}

				update_option( $this->client->prefix . "_licence_sync", true );
				wp_clear_scheduled_hook( $this->client->prefix . "_callback" );
				wp_schedule_event( time(), 'daily', $this->client->prefix . "_callback" );
				

				// Sync licences now
				$this->client->sync_licences();

			} else { // disable
				update_option( $this->client->prefix . "_licence_sync", false );
				wp_clear_scheduled_hook( $this->client->prefix . "_callback" );
			}


			if ( $success ) {
				wp_send_json_success();
			} else {
				wp_send_json_error();
			}

			wp_die();
		}


		/**
		 * Add the WordPress settings menu item.
		 */
		public function menu_item() {

			$page = add_submenu_page(
				'index.php',
				$this->name,
				$this->name,
				'manage_options',
				'ayecode-connect',
				array(
					$this,
					'settings_page'
				)
			);

			add_action( "admin_print_styles-{$page}", array( $this, 'scripts' ) );

		}

		/**
		 * Add scripts to our settings page.
		 */
		public function scripts() {
			wp_enqueue_style( 'ayecode-connect-bootstrap', $this->base_url . 'assets/css/ayecode-ui-compatibility.css', array(), AYECODE_CONNECT_VERSION );

			// Register the script
			wp_register_script( 'ayecode-connect', $this->base_url . 'assets/js/ayecode-connect.js', array( 'jquery' ), AYECODE_CONNECT_VERSION );

			// Localize the script with new data
			$translation_array = array(
				'nonce'          => wp_create_nonce( 'ayecode-connect' ),
				'error_msg'      => __( "Something went wrong, try refreshing the page and trying again.", "ayecode-connect" ),
				'disconnect_msg' => __( "Are you sure you with to disconnect your site?", "ayecode-connect" ),
			);
			wp_localize_script( 'ayecode-connect', 'ayecode_connect', $translation_array );
			wp_enqueue_script( 'ayecode-connect' );
		}

		/**
		 * Settings page HTML.
		 */
		public function settings_page() {
			// bsui wrapper makes our bootstrap wrapper work

			?>
			<!-- Clean & Mean UI -->
			<style>
				#wpbody-content > div.notice {
					display: none;
				}
			</style>

			<div class="bsui" style="margin-left: -20px;">
				<!-- Just an image -->
				<nav class="navbar bg-white border-bottom">
					<a class="navbar-brand p-0" href="#">
						<img src="<?php echo $this->base_url; ?>assets/img/ayecode.png" width="120" alt="AyeCode Ltd">
					</a>
				</nav>
			</div>


			<div class="bsui" style="margin-left: -20px; display: flex">

				<div id="ayecode-connect-wrapper" class="container bg-white w-100 p-4 m-4 border rounded text-center">
					<div class="ac-header">

					</div>
					<div class="ac-body mt-5">
						<div class="ac-button-container text-center">
							<h1 class="h5 mx-auto w-50 mb-3"><?php _e( "One click addon installs, live documentation search, support right from your WordPress Dashboard", "ayecode-connect" ); ?></h1>

							<?php
							if ( $this->client->is_registered() ) {

								$connected_username = $this->client->get_connected_username();
								?>
								<div class="alert alert-success  w-50 mx-auto text-left" role="alert">
									<?php echo sprintf( __( "You are connected to AyeCode Connect as user: %s", "ayecode-conect" ), "<b>$connected_username</b>" ); ?>
								</div>

								<ul class="list-group w-50 mx-auto">

									<li class="list-group-item d-flex justify-content-between align-items-center">
										<span
											class="mr-auto"><?php _e( "Plugin and theme update notifications", "ayecode-connect" ); ?></span>
										<div class="spinner-border spinner-border-sm mr-2 d-none text-muted"
										     role="status">
											<span class="sr-only"><?php _e( "Loading...", "ayecode-connect" ); ?></span>
										</div>
										<div class="custom-control custom-switch">
											<input type="checkbox" class="custom-control-input" id="ac-setting-updates"
												<?php if ( defined( 'WP_EASY_UPDATES_ACTIVE' ) ) {
													echo "checked";
												} ?>
												   onclick="if(jQuery(this).is(':checked')){ayecode_connect_updates(this,1);}else{ayecode_connect_updates(this,0);}"
											>
											<label class="custom-control-label" for="ac-setting-updates"></label>
										</div>
									</li>

									<li class="list-group-item d-flex justify-content-between align-items-center">
										<span
											class="mr-auto"><?php _e( "One click addon installs, no more license keys", "ayecode-connect" ); ?></span>
										<div class="spinner-border spinner-border-sm mr-2 d-none text-muted"
										     role="status">
											<span class="sr-only"><?php _e( "Loading...", "ayecode-connect" ); ?></span>
										</div>
										<div class="custom-control custom-switch">
											<input type="checkbox" class="custom-control-input" id="ac-setting-licences"
												<?php if ( get_option( $this->client->prefix . "_licence_sync" ) ) {
													echo "checked";
												} ?>
												   onclick="if(jQuery(this).is(':checked')){ayecode_connect_licences(this,1);}else{ayecode_connect_licences(this,0);}"
											>
											<label class="custom-control-label" for="ac-setting-licences"></label>
										</div>
									</li>

									<li class="list-group-item d-flex justify-content-between align-items-center">
										Live documentation search
										<span class="badge badge-light badge-pill">Coming soon</span>
									</li>
									<li class="list-group-item d-flex justify-content-between align-items-center">
										Support from Dashboard
										<span class="badge badge-light badge-pill">Coming soon</span>
									</li>
								</ul>

								<p class="mt-4">
									<span class="spinner-border spinner-border-sm mr-2 d-none text-muted" role="status">
										<span class="sr-only"><?php _e( "Loading...", "ayecode-connect" ); ?></span>
									</span>
									<a href="javascript:void(0)"
									   onclick="ayecode_connect_disconnect(this);return false;"
									   class="text-muted">
										<u><?php _e( 'Disconnect site', 'ayecode-connect' ); ?></u></a>
								</p>
								<?php
							} else {
								$connect_url = esc_url( $this->client->build_connect_url() );
								?>
								<small
									class="text-muted"><?php _e( "By clicking the <b>Connect Site</b> button, you agree to our <a href='https://ayecode.io/terms-and-conditions/' target='_blank' class='text-muted' ><u>Terms of Service</u></a> and to share details with AyeCode Ltd", "ayecode-connect" ); ?></small>
								<p class="mt-4">
									<a href="<?php echo $connect_url; ?>"
									   class="btn btn-primary"><?php _e( 'Connect Site', 'ayecode-connect' ); ?></a>
								</p>
								<?php
								// check for local domain
								$host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
								$localhost = $this->client->is_usable_domain($host);
								if(is_wp_error($localhost)){
									echo '<div class="alert alert-danger w-50 mx-auto" role="alert">';
									_e("It looks like you might be running on localhost, AyeCode Connect will only work on a live website.","ayecode-connect");
									echo "</div>";
								}
							}
							?>
						</div>

						<img src="<?php echo $this->base_url; ?>assets/img/connect-site.png" class="img-fluid mt-4"
						     alt="AyeCode Connect">
					</div>
					<div class="ac-footer border-top mt-5">
						<p class="text-muted h6 mt-4"><?php _e( 'AycCode Ltd are the creators of:', 'ayecode-connect' ); ?>
							<a href="https://wpgeodirectory.com/">wpgeodirectory.com</a>,
							<a href="https://wpinvoicing.com/">wpinvoicing.com</a> &
							<a href="https://userswp.io/">userswp.io</a>
						</p>
					</div>
				</div>
			</div>
			<?php
		}

		/**
		 * Get slug from path
		 *
		 * @param  string $key
		 *
		 * @return string
		 */
		private function format_plugin_slug( $key ) {
			$slug = explode( '/', $key );
			$slug = explode( '.', end( $slug ) );

			return $slug[0];
		}

	}

	/**
	 * Run the class if found.
	 */
	AyeCode_Connect_Settings::instance();

}