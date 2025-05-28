<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Show the support widget when set to do so.
 *
 * Class AyeCode_Connect_Support
 */
class AyeCode_Connect_Support {

	/**
	 * Is enabled or not.
	 *
	 * @var string|void
	 */
	public $enabled;

	/**
	 * Is support user enabled or not.
	 *
	 * @var string|void
	 */
	public $support_user;

	/**
	 * The prefix for settings.
	 *
	 * @var string|void
	 */
	public $prefix;

	/**
	 * The Connected user display name.
	 *
	 * @var string
	 */
	public $name;

	/**
	 * The Connected user email.
	 *
	 * @var string
	 */
	public $email;

	/**
	 * GeoDirectory help scout beacon ID for collecting beta features feedback in AyeCode Connect plugin.
	 *
	 * @var string
	 */
	public $ac_beta_beacon_id = '597998048';

	/**
	 * GeoDirectory help scout beacon ID.
	 *
	 * @var string
	 */
	public $gd_beacon_id = '3278983422';

	/**
	 * UsersWP help scout beacon ID.
	 *
	 * @var string
	 */
	public $uwp_beacon_id = '1406029167';

	/**
	 * GetPaid free scout beacon ID.
	 *
	 * @var string
	 */
	public $wpi_beacon_id = '617184761';


	/**
	 * AyeCode_Connect_Support constructor.
	 *
	 * @param array $args
	 */
	public function __construct( array $args = array() ) {

		// support user login
		add_action( 'setup_theme', array( $this, 'maybe_remove_support_user' ), 9 ); // remove user if expired
		add_action( 'setup_theme', array( $this, 'maybe_login_support_user' ) );


		// support widget
		if ( $args['support_user'] ) {
			$this->support_user = absint( $args['support_user'] );
		}
		if ( $args['enabled'] ) {
			$this->enabled = esc_attr( $args['enabled'] );
		}
		if ( $args['prefix'] ) {
			$this->prefix = esc_attr( $args['prefix'] );
		}
		if ( $args['name'] ) {
			$this->name = sanitize_text_field( $args['name'] );
		}
		if ( $args['email'] ) {
			$this->email = sanitize_email( $args['email'] );
		}
		add_action( 'admin_footer', array( $this, 'maybe_add_admin_footer_script' ) );
	}

	/**
	 * Remove support user if expired.
	 */
	public function maybe_remove_support_user() {
		if ( $this->support_user && ! get_transient( $this->prefix . "_support_user_key" ) ) {
			update_option( $this->prefix . "_support_user", false );

			// destroy support user
			$support_user = get_user_by( 'login', 'ayecode_connect_support_user' );
			if ( ! empty( $support_user ) && isset( $support_user->ID ) && ! empty( $support_user->ID ) ) {
				require_once(ABSPATH.'wp-admin/includes/user.php');
				$user_id = absint( $support_user->ID );
				// get all sessions for user with ID $user_id
				$sessions = WP_Session_Tokens::get_instance( $user_id );
				// we have got the sessions, destroy them all!
				$sessions->destroy_all();
				$reassign = user_can( 1, 'manage_options' ) ? 1 : null;
				wp_delete_user( $user_id, $reassign );
				if ( is_multisite() ) {
					if ( ! function_exists( 'wpmu_delete_user' ) ) { 
						require_once( ABSPATH . 'wp-admin/includes/ms.php' );
					}
					revoke_super_admin( $user_id );
					wpmu_delete_user( $user_id );
				}
			}
		}
	}

	/**
	 * Login the support user if conditions are met.
	 */
	public function maybe_login_support_user() {

		if ( ! empty( $_POST['ayecode_connect_support_user'] ) && $this->support_user && $key_hash = get_transient( $this->prefix . "_support_user_key" ) ) {

			$key = sanitize_text_field( urldecode( $_POST['ayecode_connect_support_user'] ) );
			if ( wp_check_password( $key, $key_hash ) && $this->support_user > time() ) {
				$support_user = get_user_by( 'login', 'ayecode_connect_support_user' );
				if ( ! ( ! empty( $support_user ) && isset( $support_user->ID ) && ! empty( $support_user->ID ) ) ) {
					$user_data = array(
						'user_pass'     => wp_generate_password( 20 ), // we never need to know this
						'user_login'    => 'ayecode_connect_support_user',
						'user_nicename' => 'AyeCode Support',
						'user_email'    => '', // no email so the pass can never be reset
						'first_name'    => 'AyeCode',
						'last_name'     => 'Support',
						'user_url'      => 'https://ayecode.io/',
						'role'          => 'administrator'
					);

					$user_id = wp_insert_user( $user_data );

					if ( is_wp_error( $user_id ) ) {
						echo $user_id->get_error_message();
					}elseif($user_id){
						if(is_multisite()){
							$blog_id = get_current_blog_id();
							add_user_to_blog( $blog_id, $user_id, $user_data['role'] );
							grant_super_admin( $user_id );
						}
					}
				} else {
					$user_id = absint( $support_user->ID );
				}

				if ( is_int( $user_id ) ) {
					wp_clear_auth_cookie();
					wp_set_current_user( $user_id );
					wp_set_auth_cookie( $user_id );
					wp_redirect( admin_url( "admin.php?page=ayecode-connect" ) );
					exit;
				}

			}
		}
	}

	/**
	 * Add the footer script is conditions are met.
	 */
	public function maybe_add_admin_footer_script() {
		if ( current_user_can( 'manage_options' ) && $beacon_id = $this->get_beacon_id() ) {
			if ( $this->enabled || ( isset( $_REQUEST['page'] ) && $_REQUEST['page'] == 'ayecode-connect' ) ) {
				echo $this->helpscout_base_js();
				echo $this->helpscout_beacon_js( $beacon_id );
			}

		}
	}

	/**
	 * Return a beacon ID for the current page.
	 *
	 * @return mixed|string
	 */
	public function get_beacon_id() {
		$beacon_id = '';

		// page conditions
		if ( ! empty( $_REQUEST['page'] ) ) {
			$page            = sanitize_title_with_dashes( $_REQUEST['page'] );
			$page_conditions = array(
				// GD
				'ayecode-connect'     => $this->gd_beacon_id,
				'ayecode-demo-content'=> $this->ac_beta_beacon_id,
				'geodirectory'        => $this->gd_beacon_id,
				'gd-settings'         => $this->gd_beacon_id,
				'gd-status'           => $this->gd_beacon_id,
				'gd-addons'           => $this->gd_beacon_id,
				// WPI
				'wpinv-settings'      => $this->wpi_beacon_id,
				'wpinv-reports'       => $this->wpi_beacon_id,
				'wpinv-subscriptions' => $this->wpi_beacon_id,
				'wpi-addons'          => $this->wpi_beacon_id,
				// UWP
				'userswp'             => $this->uwp_beacon_id,
				'uwp_form_builder'    => $this->uwp_beacon_id,
				'uwp_status'          => $this->uwp_beacon_id,
				'uwp-addons'          => $this->uwp_beacon_id,

			);

			if ( isset( $page_conditions[ $page ] ) ) {
				$beacon_id = $page_conditions[ $page ];
			}
		} // post_type conditions
		elseif ( ! empty( $_REQUEST['post_type'] ) ) {

		}

		return $beacon_id;
	}

	/**
	 * The base script for Help Scout.
	 *
	 * @return string
	 */
	public function helpscout_base_js() {
		ob_start();
		?>
		<script type="text/javascript">
            // AyeCode Connect Widget
            const AyeCodeConnectWidget = {
                config: {
                    freescoutBoxId: null,
                    color: '#007bff',
                    docsUrl: '#',
                    prefillName: '',
                    prefillEmail: '',
                },

                elements: {
                    helpIcon: null,
                    popup: null
                },

                init: function(options) {
                    // Merge options with defaults
                    Object.assign(this.config, options);

                    // Create widget after DOM is ready
                    if (document.readyState === 'loading') {
                        document.addEventListener('DOMContentLoaded', () => this.createWidget());
                    } else {
                        this.createWidget();
                    }
                },

                createWidget: function() {
                    // Create help icon
                    const helpIcon = document.createElement('div');
                    helpIcon.classList.add('bsui');
                    helpIcon.innerHTML = `
            <button type="button" class="btn btn-primary rounded-circle shadow d-flex align-items-center justify-content-center hover-zoom"
                    style="position: fixed; bottom: 20px; right: 20px; z-index: 1050; width: 50px; height: 50px; background-color: ${this.config.color}; border-color: ${this.config.color};">
                <i class="fa-solid fa-life-ring fa-lg text-white fs-3" style="margin-bottom: -2px;"></i>
            </button>
        `;

                    // Create popup window
                    const popup = document.createElement('div');
                    popup.classList.add('bsui');
                    popup.innerHTML = `
            <div class="card shadow p-0 m-0 position-fixed transition-all border-0" style="bottom: 90px; right: 20px; z-index: 1050; width: 320px; opacity: 0; transform: translateY(10px); transition: opacity 0.3s ease, transform 0.3s ease; pointer-events: none;">
                <div class="card-header text-center pb-5 pt-4" style="background-color: ${this.config.color}; color: white;">
                    <h6 class="mb-3"><?php _e('How can we help?','ayecode-connect') ?></h6>

                    <div class="d-flex align-items-center justify-content-center mb-2">
                        <?php
					$x = 0;
					while($x < 5){
						$x++;
						$img = plugins_url( 'assets/img/team'.absint($x).'.jpg', dirname( __FILE__ ) );
						echo '<img src="'.esc_url(  $img  ).'" class="rounded-circle border border-white border-3" style="width: 46px; height: 46px; margin-right: -5px;" />';
					}
					?>
                    </div>

                </div>
                <div class="card-body p-3 rounded-bottom bg-light">
                    <div class="d-grid gap-3 mt-n5">
                        <a href="${this.config.docsUrl}" class="row bg-white text-muted py-4 shadow-sm hover-shadow mx-0 rounded-1 text-start text-decoration-none position-relative" target="_blank">
                            <span class="col-3 text-center">
                                <span class="border-0 iconbox fill rounded-circle iconsmall d-inline-block align-middle btn-translucent-primary transition-all stretched-link" >
                                    <i class="fas fa-book "></i>
                                </span>
                            </span>
                            <span class="col-9 text-start ps-0">
                                <span class="text-dark fs-sm fw-bold"><?php esc_html_e('Documentation','ayecode-connect'); ?></span>
                                <p class="text-muted p-0 m-0 fs-xs">
                                    <?php esc_html_e('Browse the docs to find solutions','ayecode-connect'); ?>
                                </p>
                            </span>
                        </a>

                         <a href="https://support.ayecode.io/help/${this.config.freescoutBoxId}/?f[email]=${this.config.prefillEmail}&f[name]=${this.config.prefillName}" onclick="ayecode_connect_maybe_suggest_support_user_access()" class="row bg-white text-muted py-4 shadow-sm hover-shadow mx-0 rounded-1 text-start text-decoration-none position-relative" target="_blank">
                            <span class="col-3 text-center">
                                <span class="border-0 iconbox fill rounded-circle iconsmall d-inline-block align-middle btn-translucent-primary transition-all stretched-link" >
                                    <i class="far fa-envelope"></i>
                                </span>
                            </span>
                            <span class="col-9 text-start ps-0">
                                <span class="text-dark fs-sm fw-bold"><?php esc_html_e('Open Support Ticket','ayecode-connect'); ?></span>
                                <p class="text-muted p-0 m-0 fs-xs">
                                    <?php esc_html_e('Need more help? We got you!','ayecode-connect'); ?>
                                </p>
                            </span>
                        </a>

                        <a href="https://support.ayecode.io/help/${this.config.freescoutBoxId}/tickets" class="row bg-white text-muted py-4 shadow-sm hover-shadow mx-0 rounded-1 text-start text-decoration-none position-relative" target="_blank">
                            <span class="col-3 text-center">
                                <span class="border-0 iconbox fill rounded-circle iconsmall d-inline-block align-middle btn-translucent-primary transition-all stretched-link" >
                                    <i class="far fa-envelope-open"></i>
                                </span>
                            </span>
                            <span class="col-9 text-start ps-0">
                                <span class="text-dark fs-sm fw-bold"><?php esc_html_e('View Support Tickets','ayecode-connect'); ?></span>
                                <p class="text-muted p-0 m-0 fs-xs">
                                    <?php esc_html_e('View your previous support tickets','ayecode-connect'); ?>
                                </p>
                            </span>
                        </a>


                    </div>
                </div>
            </div>
        `;

                    // Add to DOM
                    document.body.appendChild(helpIcon);
                    document.body.appendChild(popup);

                    // Store references for later removal
                    this.elements.helpIcon = helpIcon;
                    this.elements.popup = popup;

                    // Get elements
                    const button = helpIcon.querySelector('button');
                    const buttonIcon = button.querySelector('i');
                    const popupDiv = popup.querySelector('.card');

                    let isOpen = false;

                    // Toggle popup
                    button.addEventListener('click', () => {
                        if (isOpen) {
                            this.closePopup(popupDiv, buttonIcon);
                        } else {
                            this.openPopup(popupDiv, buttonIcon);
                        }
                        isOpen = !isOpen;
                    });

                    // Close popup when clicking outside
                    document.addEventListener('click', (e) => {
                        if (!helpIcon.contains(e.target) && !popup.contains(e.target) && isOpen) {
                            this.closePopup(popupDiv, buttonIcon);
                            isOpen = false;
                        }
                    });
                },

                openPopup: function(popupDiv, buttonIcon) {
                    popupDiv.style.pointerEvents = 'auto';
                    popupDiv.style.opacity = '1';
                    popupDiv.style.transform = 'translateY(0)';
                    buttonIcon.className = 'fas fa-times fa-lg text-white fs-3';
                },

                closePopup: function(popupDiv, buttonIcon) {
                    popupDiv.style.opacity = '0';
                    popupDiv.style.transform = 'translateY(10px)';
                    buttonIcon.className = 'fa-solid fa-life-ring fa-lg text-white fs-3';

                    // Disable pointer events after animation
                    setTimeout(() => {
                        popupDiv.style.pointerEvents = 'none';
                    }, 300);
                },

                destroy: function() {
                    // Remove elements from DOM
                    if (this.elements.helpIcon) {
                        this.elements.helpIcon.remove();
                        this.elements.helpIcon = null;
                    }
                    if (this.elements.popup) {
                        this.elements.popup.remove();
                        this.elements.popup = null;
                    }

                    // Reset config
                    this.config = {
                        freescoutBoxId: null,
                        color: '#007bff',
                        docsUrl: '#',
                    };
                }
            };
		</script>
        <style>
            .bsui .d-grid .text-decoration-none{
                top: 0;
                transition: top ease 0.2s !important;
            }
            .bsui .d-grid .text-decoration-none:hover{
                top: -2px !important;
            }
        </style>
		<?php
		return ob_get_clean();
	}


	/**
	 * Script to enable the Help Scout Beacon.
	 *
	 * @param string $beacon_id
	 *
	 * @return string
	 */
	public function helpscout_beacon_js( $beacon_id = '' ) {
		if ( ! $beacon_id ) {
			return '';
		}
		$documentation_url = $this->get_documentation_url( $beacon_id );
		$brand_color = $this->get_brand_color( $beacon_id );
		ob_start();
		?>
		<script type="text/javascript">
			/**
			 * Variable for tracking if support email was sent.
			 */
			var ayecodeSupportSent = false;
			/**
			 * Set variable if support email was sent.
			 */
			function ayecode_connect_set_support_sent() {
				ayecodeSupportSent = true;
			}

			/**
			 * Fire the support access message if sent and then closed.
			 */
			function ayecode_connect_maybe_suggest_support_user_access() {
				if (!ayecodeSupportSent) {
                    // aui_modal('Maybe Enable Support Access?','If you think your issue might require support access, please enable the support user from AyeCode Connect. This can speed up the resolution time.',$footer,$dismissible,$class,$dialog_class,$body_class);
                    aui_modal('<?php esc_attr_e( 'Enable Support User Access?','ayecode-connect' );?>','<p class="fw-bold"><?php esc_attr_e( 'AyeCode Connect: If you think your issue might require support access, please enable the support user from AyeCode Connect. This can speed up the resolution time.','ayecode-connect' );?></p>','<a href="<?php echo esc_url( admin_url( "admin.php?page=ayecode-connect" ) ); ?>" class="btn btn-primary"><?php esc_attr_e( 'Go to Settings','ayecode-connect' );?></a>',true);
                    ayecode_connect_set_support_sent()
				}
			}

			/**
			 * Fire up the support widget.
			 */
			function ayecode_connect_init_widget() {

                AyeCodeConnectWidget.init({
                    freescoutBoxId: '<?php echo esc_attr( $beacon_id );?>',
                    color: '<?php echo esc_attr( $brand_color );?>',
                    docsUrl: '<?php echo esc_url( $documentation_url );?>',
                    prefillName: '<?php echo esc_attr( $this->name );?>',
                    prefillEmail: '<?php echo esc_attr( $this->email );?>',
                });

			}

			// run if enabled
			<?php
			if ( $this->enabled ) {
				echo "ayecode_connect_init_widget();";
			}
			?>
		</script>
		<?php
		return ob_get_clean();
	}

	/**
	 * Return a beacon ID for the current page.
	 *
	 * @return mixed|string
	 */
	public function get_signature( $beacon_id ) {
		$signature = '';

		$signatures = get_option( $this->prefix . '_connected_user_signatures' );

		// page conditions
		if ( $beacon_id == $this->gd_beacon_id && ! empty( $signatures['gd'] ) ) {
			$signature = esc_attr( $signatures['gd'] );
		} elseif ( $beacon_id == $this->uwp_beacon_id && ! empty( $signatures['uwp'] ) ) {
			$signature = esc_attr( $signatures['uwp'] );
		} elseif ( $beacon_id == $this->wpi_beacon_id && ! empty( $signatures['wpi'] ) ) {
			$signature = esc_attr( $signatures['wpi'] );
		}

		return $signature;
	}

	/**
     * Get the documentation url depending on the active beacon id.
     *
	 * @param $beacon_id
	 *
	 * @return string
	 */
	public function get_documentation_url( $beacon_id ) {
		// page conditions
		if ( $beacon_id == $this->gd_beacon_id  ) {
			$url = 'https://wpgeodirectory.com/documentation/';
		} elseif ( $beacon_id == $this->uwp_beacon_id ) {
			$url = 'https://userswp.io/documentation/';
		} elseif ( $beacon_id == $this->wpi_beacon_id  ) {
			$url = 'https://wpgetpaid.com/documentation/';
		}else{
			$url = 'https://wpgeodirectory.com/documentation/';
		}

		return $url;
	}

	/**
	 * Get the brand color.
	 *
	 * @param $beacon_id
	 *
	 * @return string
	 */
	public function get_brand_color( $beacon_id ) {
		// page conditions
		if ( $beacon_id == $this->gd_beacon_id  ) {
			$url = '#ff8333';
		} elseif ( $beacon_id == $this->uwp_beacon_id ) {
			$url = '#2981b3';
		} elseif ( $beacon_id == $this->wpi_beacon_id  ) {
			$url = '#009874';
		}else{
            // AyeCode color
			$url = '#52a6dd';
		}

		return $url;
	}



}