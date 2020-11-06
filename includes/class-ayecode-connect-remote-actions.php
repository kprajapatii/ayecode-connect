<?php
/**
 * A class to carryout authenticated remote actions for AyeCode Connect.
 */

/**
 * Bail if we are not in WP.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


if ( ! class_exists( 'AyeCode_Connect_Remote_Actions' ) ) {

	/**
	 * The remote actions for AyeCode Connect
	 */
	class AyeCode_Connect_Remote_Actions {
		/**
		 * The title.
		 *
		 * @var string
		 */
		public $name = 'AyeCode Connect';

		public $prefix = 'ayecode_connect';

		/**
		 * The relative url to the assets.
		 *
		 * @var string
		 */
		public $url = '';

		public $client;
		public $base_url;

		/**
		 * Holds the settings values.
		 *
		 * @var array
		 */
		private $settings;

		/**
		 * AyeCode_UI_Settings instance.
		 *
		 * @access private
		 * @since  1.0.0
		 * @var    AyeCode_Connect_Remote_Actions There can be only one!
		 */
		private static $instance = null;

		/**
		 * Main AyeCode_Connect_Remote_Actions Instance.
		 *
		 * Ensures only one instance of AyeCode_Connect_Remote_Actions is loaded or can be loaded.
		 *
		 * @since 1.0.0
		 * @static
		 * @return AyeCode_Connect_Remote_Actions - Main instance.
		 */
		public static function instance( $prefix = '' ,$client = '') {
			if ( ! isset( self::$instance ) && ! ( self::$instance instanceof AyeCode_Connect_Remote_Actions ) ) {
				self::$instance = new AyeCode_Connect_Remote_Actions;

				if ( $prefix ) {
					self::$instance->prefix = $prefix;
				}

				if ( $client ) {
					self::$instance->client = $client;
				}

				$remote_actions = array(
					'install_plugin'  => 'install_plugin',
					'update_licences' => 'update_licences',
					'install_theme'  => 'install_theme',
					'update_options'  => 'update_options',
					'import_menus'  => 'import_menus',
					'import_content'  => 'import_content',
				);

				/*
				 * Add any actions in the style of "{$prefix}_remote_action_{$action}"
				 */
				foreach ( $remote_actions as $action => $call ) {
					if( !has_action($prefix . '_remote_action_' . $action,array(
						self::$instance,
						$call
					)) ){
						add_action( $prefix . '_remote_action_' . $action, array(
							self::$instance,
							$call
						) ); // set settings
					}

				}

			}

			return self::$instance;
		}

		public function import_content(){
			$result = array( "success" => false );

//			wp_mail("stiofansisland@gmail.com","update content debug",print_r($_REQUEST,true));

			// validate
			if ( $this->validate_request() ) {

				// categories
				$categories = ! empty( $_REQUEST['categories'] ) ? json_decode( stripslashes( $_REQUEST['categories'] ), true ) : array();
//				wp_mail("stiofansisland@gmail.com","update content debug cats",print_r($categories,true));
				if(!empty($categories) && class_exists('GeoDir_Admin_Dummy_Data')){
					foreach ( $categories as $cpt => $cats ) {
						GeoDir_Admin_Dummy_Data::create_taxonomies( $cpt, $cats);
					}
				}


				// posts
				$posts = ! empty( $_REQUEST['posts'] ) ? json_decode( stripslashes( $_REQUEST['posts'] ), true ) : array();
//				wp_mail("stiofansisland@gmail.com","update content debug all posts",print_r($posts ,true));
				if(!empty($posts) && class_exists('GeoDir_Admin_Dummy_Data')){
//					wp_mail("stiofansisland@gmail.com","update content debug all posts content",print_r($posts,true));

					// insert the dummy data column
					//geodir_add_column_if_not_exist( $plugin_prefix . $post_type . "_detail", 'post_dummy', "TINYINT(1) NULL DEFAULT '0'" );
					foreach ( $posts as $cpt => $cpt_posts ) {

						// @todo maybe delete all dummy posts first?

						if ( ! empty( $cpt_posts ) ) {
							foreach ( $cpt_posts as $post_info ) {
//								wp_mail("stiofansisland@gmail.com","update content debug posts content",print_r($post_info,true));
								unset($post_info['ID']);
								$insert_result = wp_insert_post( $post_info, true ); // we hook into the save_post hook
//								wp_mail("stiofansisland@gmail.com","update content debug posts",print_r($insert_result,true));
//								return array( "success" => true );
							}
						}









					}
				}



				// set as success
				$result = array( "success" => true );
			}

			return $result;
		}

		public function import_menus(){
			$result = array( "success" => false );

//			wp_mail("stiofansisland@gmail.com","update menu debug",print_r($_REQUEST,true));

			// validate
			if ( $this->validate_request() ) {


				$menus = ! empty( $_REQUEST['menus'] ) ? $_REQUEST['menus'] : array();

				if ( ! empty( $menus ) ) {
					foreach($menus as $location => $menu){
						$import = $this->import_menu( $location, $menu );
					}
				}
				

				// set as success
				$result = array( "success" => true );
			}

			return $result;
		}

		/**
		 * Import menu.
		 * 
		 * @param $location
		 * @param $menu
		 *
		 * @return bool
		 */
		public function import_menu( $location, $menu ){
			$result = false;

			if ( ! empty( $menu ) ) {
				$name = esc_attr($menu['name']);

				// Does the menu exist already?
				$menu_exists = wp_get_nav_menu_object( $name );

				// If it doesn't exist, let's create it.
				if( !$menu_exists) {
					$menu_id = wp_create_nav_menu( $name );

					$locations = get_theme_mod( 'nav_menu_locations' );

					if($menu_id){
						$locations[$location] = $menu_id;
						set_theme_mod('nav_menu_locations', $locations);

						if ( ! empty( $menu['items'] ) ) {
							$menu_ids = array();
							$parent_ids = array();
							foreach ( $menu['items'] as $item) {
								// unset some things
								$p = $item['post'];
								$metas = $item['post_metas'];
								$original_id = absint( $p['ID'] );
								unset($p['ID']);
								$db_id = wp_insert_post( $p );

								// set id relations
								$menu_ids[$original_id] = $db_id;

								if($menu_id){
									// Associate the menu item with the menu term.
									wp_set_object_terms( $db_id, array( $menu_id ), 'nav_menu' );

									// set meta items
									if ( ! empty( $metas ) ) {
										foreach ( $metas as $key => $meta ) {
											$meta = maybe_unserialize( $meta[0] );
											if(is_array($meta)){
												$meta = implode( " ", $meta );
											}

											// set the correct id
											if ( $key == '_menu_item_object_id' ) {
												$meta = absint( $db_id );
											}

											// set correct parent id
											if ( $key == '_menu_item_menu_item_parent' && !empty($meta) ) {
//												$meta = absint( $menu_ids[$meta] );
												$parent_ids[$db_id] = absint($meta);
											}

											// set the correct url for add listing pages
											if ( $key == '_menu_item_url' && !empty($meta) && strpos($meta, 'listing_type=gd_') !== false && function_exists('geodir_add_listing_page_url')) {
												$url_parts = explode( "=", $meta );
												if(!empty($url_parts[1])){
													$meta = geodir_add_listing_page_url(esc_attr($url_parts[1]));
												}

											}

											update_post_meta( $db_id, sanitize_key($key), $meta );
										}
									}
								}

							}

							// set parent ids after insert
							if ( ! empty( $parent_ids ) ) {
								foreach($parent_ids as $id => $p_id){
									$n_id = !empty($menu_ids[$p_id]) ? absint($menu_ids[$p_id]) : 0;
									if($n_id){
										update_post_meta( $id, '_menu_item_menu_item_parent', $n_id );
									}
								}
							}
						}

					}

				}
				
			}

			return $result;
		}

		/**
		 * Update site options.
		 *
		 * @return array
		 */
		public function update_options(){
			$result = array( "success" => false );

//			wp_mail("stiofansisland@gmail.com","update settings debug j",print_r($_REQUEST,true));

			// validate
			if ( $this->validate_request() ) {
				// update
				$options = ! empty( $_REQUEST['update'] ) ? json_decode( stripslashes( $_REQUEST['update'] ), true ) : array();
				if(!empty($options)){
					foreach ( $options as $key => $option ) {
						// @todo add a options whitelist so only certain options can be updated.
						update_option( esc_attr($key), $option );
					}
				}

				// merge
				$options = ! empty( $_REQUEST['merge'] ) ? json_decode( stripslashes( $_REQUEST['merge'] ), true ) : array();
//				wp_mail("stiofansisland@gmail.com","update settings debug merge",print_r($options,true));
				if(!empty($options)){
					foreach ( $options as $key => $option ) {
						// @todo add a options whitelist so only certain options can be updated.
						$key = esc_attr($key);
						$current = get_option($key);

						if(!empty($current) && is_array($current)){
							update_option( $key, array_merge($current, $option) );
						}else{
							update_option( $key, $option );
						}

					}
				}

				// delete
				$options = ! empty( $_REQUEST['delete'] ) ? json_decode( stripslashes( $_REQUEST['delete']) , true ) : array();
				if(!empty($options)){
					foreach ( $options as $key => $option ) {
						// @todo add a options whitelist so only certain options can be updated.
						delete_option( esc_attr($key) );
					}
				}


				// GD Settings
				$settings = ! empty( $_REQUEST['geodirectory_settings'] ) ? json_decode( stripslashes( $_REQUEST['geodirectory_settings']) , true ) : array();
//				wp_mail("stiofansisland@gmail.com","update settings debug GD Settings",print_r($settings,true));
				if ( ! empty( $settings ) ) {
					$this->import_geodirectory_settings($settings);
				}

				// set as success
				$result = array( "success" => true );
			}

			return $result;
		}

		/**
		 * Import GeoDirectory custom table settings.
		 * 
		 * @param $settings
		 */
		public function import_geodirectory_settings($settings){
			global $wpdb;

			// custom_fields
			if ( ! empty( $settings['custom_fields'] ) && defined('GEODIR_CUSTOM_FIELDS_TABLE')) {
				// empty the table first
				$wpdb->query("TRUNCATE TABLE ".GEODIR_CUSTOM_FIELDS_TABLE );

				// insert
				foreach ( $settings['custom_fields'] as $custom_field ) {
					// maybe unserialize and change name
					if(!empty($custom_field['extra_fields'])){
						$custom_field['extra'] = maybe_unserialize( $custom_field['extra_fields'] );
					}

					// packaged key change
					if ( ! empty( $custom_field['packages'] ) ) {
						$custom_field['show_on_pkg'] = $custom_field['packages'];
					}

					unset($custom_field['id']);
					$r = geodir_custom_field_save($custom_field);
//									wp_mail("stiofansisland@gmail.com","update settings debug GD Settings",print_r($r,true));
//					return;

				}

			}

			// sort_fields
			if ( ! empty( $settings['sort_fields'] ) && defined('GEODIR_CUSTOM_SORT_FIELDS_TABLE')) {
				// empty the table first
				$wpdb->query("TRUNCATE TABLE ". GEODIR_CUSTOM_SORT_FIELDS_TABLE );

				// insert
				foreach ( $settings['sort_fields'] as $sort_fields ) {
					GeoDir_Settings_Cpt_Sorting::save_custom_field($sort_fields);
				}

			}

			// tabs
			if ( ! empty( $settings['tabs'] ) && defined('GEODIR_TABS_LAYOUT_TABLE')) {
				// empty the table first
				$wpdb->query("TRUNCATE TABLE ".GEODIR_TABS_LAYOUT_TABLE );

				// insert
				foreach ( $settings['tabs'] as $tab) {
					unset($tab['id']);// we need insert not update
					GeoDir_Settings_Cpt_Tabs::save_tab_item( $tab );
				}

			}

			// Advanced Search
			if ( ! empty( $settings['search_fields'] ) && defined('GEODIR_ADVANCE_SEARCH_TABLE')) {
				// empty the table first
				$wpdb->query("TRUNCATE TABLE ".GEODIR_ADVANCE_SEARCH_TABLE );

				// insert
				foreach ( $settings['search_fields'] as $search_field) {

					GeoDir_Adv_Search_Settings_Cpt_Search::save_field( $search_field );
				}

			}

			// price_packages
			if ( ! empty( $settings['price_packages'] ) && defined('GEODIR_ADVANCE_SEARCH_TABLE')) {
				// not implemented yet
			}

		}

		/**
		 * Update licence info.
		 *
		 * @return array
		 */
		public function update_licences() {
			$result = array( "success" => false );

			// validate
			if ( $this->validate_request() ) {
				$result    = array( "success" => true );
				$installed = ! empty( $_REQUEST['installed'] ) ? $this->sanitize_licences( $_REQUEST['installed'] ) : array();
				$all       = ! empty( $_REQUEST['all'] ) ? $this->sanitize_licences( $_REQUEST['all'], true ) : array();
				$site_id   = ! empty( $_REQUEST['site_id'] ) ? absint($_REQUEST['site_id']) : '';
				$site_url  = ! empty( $_REQUEST['site_url'] ) ? esc_url_raw($_REQUEST['site_url']) : '';
				

				// verify site_id
				if( $site_id != get_option( $this->prefix . '_blog_id', false ) ){
					return array( "success" => false );
				}

				// verify site_url
				if( $site_url && get_option( $this->prefix . "_url" ) ){
					$changed =  $this->client->check_for_url_change( $site_url );
					if( $changed ){
						return array( "success" => false );
					}
				}

				// Update licence keys for installed addons
				if ( ! empty( $installed ) && defined( 'WP_EASY_UPDATES_ACTIVE' ) ) {
					$wpeu_admin = new External_Updates_Admin( 'ayecode-connect', AYECODE_CONNECT_VERSION );
					$wpeu_admin->update_keys( $installed );
					$result = array( "success" => true );
				}

				// add all licence keys so new addons can be installed with one click.
				if ( ! empty( $all ) && defined( 'WP_EASY_UPDATES_ACTIVE' ) ) {
					update_option( $this->prefix . "_licences", $all );
				} elseif ( isset( $_REQUEST['all'] ) ) {
					update_option( $this->prefix . "_licences", array() );
				}
			}

			return $result;
		}

		/**
		 * Get an array of our valid domains.
		 *
		 * @return array
		 */
		public function get_valid_domains() {
			return array(
				'ayecode.io',
				'wpgeodirectory.com',
				'wpinvoicing.com',
				'userswp.io',
			);
		}

		/**
		 * Sanitize the array of licences.
		 *
		 * @param $licences
		 * @param bool $has_domain This indicates if the licences have another level of array key.
		 *
		 * @return array
		 */
		private function sanitize_licences( $licences, $has_domain = false ) {
			$valid_licences = array();

			if ( ! empty( $licences ) ) {
				if ( $has_domain ) {
					// get the array of valid domains
					$valid_domains = $this->get_valid_domains();

					foreach ( $licences as $domain => $domain_licences ) {
						// Check we have licences and the domain is valid.
						if ( ! empty( $domain_licences ) && in_array( $domain, $valid_domains ) ) {
							foreach ( $domain_licences as $plugin => $licence ) {
								$maybe_valid = (object) $this->validate_licence( $licence );
								if ( ! empty( $maybe_valid ) ) {
									$plugin                               = absint( $plugin ); // this is the plugin product id.
									$valid_licences[ $domain ][ $plugin ] = $maybe_valid;
								}
							}
						}
					}
				} else {
					foreach ( $licences as $plugin => $licence ) {
						$maybe_valid = (object) $this->validate_licence( $licence );
						if ( ! empty( $maybe_valid ) ) {
							$plugin                    = sanitize_text_field( $plugin ); // non domain this is a string
							$valid_licences[ $plugin ] = $maybe_valid;
						}
					}
				}
			}

			return $valid_licences;
		}

		/**
		 * Validate and sanitize licence info.
		 *
		 * @param $licence
		 *
		 * @return array
		 */
		private function validate_licence( $licence ) {
			$valid = array();

			if ( ! empty( $licence ) && is_array( $licence ) && ! empty( $licence['license_key'] ) ) {
				// key
				if ( isset( $licence['license_key'] ) ) {
					$valid['key'] = sanitize_key( $licence['license_key'] );
				}
				// status
				if ( isset( $licence['status'] ) ) {
					$valid['status'] = $this->validate_licence_status( $licence['status'] );
				}
				// download_id
				if ( isset( $licence['download_id'] ) ) {
					$valid['download_id'] = absint( $licence['download_id'] );
				}
				// price_id
				if ( isset( $licence['price_id'] ) ) {
					$valid['price_id'] = absint( $licence['price_id'] );
				}
				// payment_id
				if ( isset( $licence['payment_id'] ) ) {
					$valid['payment_id'] = absint( $licence['payment_id'] );
				}
				// expires
				if ( isset( $licence['expiration'] ) ) {
					$valid['expires'] = absint( $licence['expiration'] );
				}
				// parent
				if ( isset( $licence['parent'] ) ) {
					$valid['parent'] = absint( $licence['parent'] );
				}
				// user_id
				if ( isset( $licence['user_id'] ) ) {
					$valid['user_id'] = absint( $licence['user_id'] );
				}
			}

			return $valid;
		}

		/**
		 * Validate the licence status.
		 *
		 * @param $status
		 *
		 * @return string
		 */
		public function validate_licence_status( $status ) {

			// possible statuses
			$valid_statuses = array(
				'active',
				'inactive',
				'expired',
				'disabled',
			);

			// set empty if not a valid status
			if ( ! in_array( $status, $valid_statuses ) ) {
				$status = '';
			}

			return $status;
		}

		/**
		 * Validate the request origin.
		 *
		 * This file is not even loaded unless it passes JWT validation.
		 *
		 * @return bool
		 */
		private function validate_request() {
			$result = false;

			if ( $this->get_server_ip() === "173.208.153.114" ) {
				$result = true;
			}

			return $result;
		}

		/**
		 * Get the request has come from our server.
		 *
		 * @return string
		 */
		private function get_server_ip() {

			if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
				//check ip from share internet
				$ip = $_SERVER['HTTP_CLIENT_IP'];
			} elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
				//to check ip is pass from proxy
				$ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
			} else {
				$ip = $_SERVER['REMOTE_ADDR'];
			}

			// Cloudflare can provide a comma separated ip list
			if (strpos($ip, ',') !== false) {
				$ip = reset(explode(",",$ip));
			}

			return $ip;
		}


		/**
		 * Validate a download url is from our own server: 173.208.153.114
		 *
		 * @param $url
		 *
		 * @return bool
		 */
		private function validate_download_url( $url ) {
			$result = false;

			if ( $url ) {
				$parse = parse_url( $url );
				if ( ! empty( $parse['host'] ) ) {
					$ip = gethostbyname( $parse['host'] );
					if ( $ip === "173.208.153.114" ) { // AyeCode.io Server
						$result = true;
					}elseif( $ip === "198.143.164.252" ){ // wordpress.org server
						$result = true;
					}
				}
			}

			return $result;
		}

		/**
		 * Install plugin.
		 *
		 * @param $result
		 *
		 * @return mixed
		 */
		public function install_plugin( $result ) {
			// validate
			if ( ! $this->validate_request() ) {
				return array( "success" => false );
			}

			include_once( ABSPATH . 'wp-admin/includes/plugin-install.php' ); //for plugins_api..


			$plugin_slug = isset( $_REQUEST['slug'] ) ? sanitize_title_for_query( $_REQUEST['slug'] ) : '';
			$plugin      = array(
				'name'          => isset( $_REQUEST['name'] ) ? esc_attr( $_REQUEST['name'] ) : '',
				'repo-slug'     => $plugin_slug,
				'file-slug'     => isset( $_REQUEST['file-slug'] ) ? sanitize_title_for_query( $_REQUEST['file-slug'] ) : '',
				'download_link' => isset( $_REQUEST['download_link'] ) ? esc_url_raw( $_REQUEST['download_link'] ) : '',
				'activate'      => isset( $_REQUEST['activate'] ) && ! $_REQUEST['activate'] ? false : true,
				'network_activate'      => isset( $_REQUEST['network_activate'] ) && ! $_REQUEST['network_activate'] ? false : true,
			);

			$install = $this->background_installer( $plugin_slug, $plugin );

			if ( $install ) {
				$result = array( "success" => true );
			}

			return $result;
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
		
		/**
		 * Install a plugin from .org in the background via a cron job (used by
		 * installer - opt in).
		 *
		 * @param string $plugin_to_install_id
		 * @param array $plugin_to_install
		 *
		 * @since 2.6.0
		 *
		 * @return bool
		 */
		public function background_installer( $plugin_to_install_id, $plugin_to_install ) {

			$task_result = false;
			if ( ! empty( $plugin_to_install['repo-slug'] ) ) {
				require_once( ABSPATH . 'wp-admin/includes/file.php' );
				require_once( ABSPATH . 'wp-admin/includes/plugin-install.php' );
				require_once( ABSPATH . 'wp-admin/includes/class-wp-upgrader.php' );
				require_once( ABSPATH . 'wp-admin/includes/plugin.php' );

				WP_Filesystem();

				$skin              = new Automatic_Upgrader_Skin;
				$upgrader          = new WP_Upgrader( $skin );
				$installed_plugins = array_map( array( $this, 'format_plugin_slug' ), array_keys( get_plugins() ) );
				$plugin_slug       = $plugin_to_install['repo-slug'];
				$plugin_file_slug  = ! empty( $plugin_to_install['file-slug'] ) ? $plugin_to_install['file-slug'] : $plugin_slug;
				$plugin            = $plugin_slug . '/' . $plugin_file_slug . '.php';
				$installed         = false;
				$activate          = isset( $plugin_to_install['activate'] ) && $plugin_to_install['activate'] ? true : false;
				$network_activate          = isset( $plugin_to_install['network_activate'] ) && $plugin_to_install['network_activate'] ? true : false;

				// See if the plugin is installed already
				if ( in_array( $plugin_to_install['repo-slug'], $installed_plugins ) ) {
					$installed = true;
//					$activate  = ! is_plugin_active( $plugin );
				}

				// Install this thing!
				if ( ! $installed ) {
					// Suppress feedback
					ob_start();

					try {

						// if a download link is provided then validate it.
						if ( ! empty( $plugin_to_install['download_link'] ) ) {

							if ( ! $this->validate_download_url( $plugin_to_install['download_link'] ) ) {
								return new WP_Error( 'download_invalid', __( "Download source not valid.", "ayecode-connect" ) );
							}

							$plugin_information = (object) array(
								'name'          => esc_attr( $plugin_to_install['name'] ),
								'slug'          => esc_attr( $plugin_to_install['repo-slug'] ),
								'download_link' => esc_url( $plugin_to_install['download_link'] ),
							);
						} else {

							$plugin_information = plugins_api( 'plugin_information', array(
								'slug'   => $plugin_to_install['repo-slug'],
								'fields' => array(
									'short_description' => false,
									'sections'          => false,
									'requires'          => false,
									'rating'            => false,
									'ratings'           => false,
									'downloaded'        => false,
									'last_updated'      => false,
									'added'             => false,
									'tags'              => false,
									'homepage'          => false,
									'donate_link'       => false,
									'author_profile'    => false,
									'author'            => false,
								),
							) );
						}

						if ( is_wp_error( $plugin_information ) ) {
							throw new Exception( $plugin_information->get_error_message() );
						}

						$package  = $plugin_information->download_link;
						$download = $upgrader->download_package( $package );

						if ( is_wp_error( $download ) ) {
							throw new Exception( $download->get_error_message() );
						}

						$working_dir = $upgrader->unpack_package( $download, true );

						if ( is_wp_error( $working_dir ) ) {
							throw new Exception( $working_dir->get_error_message() );
						}


						$result = $upgrader->install_package( array(
							'source'                      => $working_dir,
							'destination'                 => WP_PLUGIN_DIR,
							'clear_destination'           => false,
							'abort_if_destination_exists' => false,
							'clear_working'               => true,
							'hook_extra'                  => array(
								'type'   => 'plugin',
								'action' => 'install',
							),
						) );

						if ( ! is_wp_error( $result ) ) {
							$task_result = true;
						}

//						$activate = true;

					} catch ( Exception $e ) {
//
					}

					// Discard feedback
					ob_end_clean();
				}

				wp_clean_plugins_cache();

				// Activate this thing
				if ( $activate ) {
					try {
						$result = activate_plugin( $plugin, "", $network_activate );

						if ( ! is_wp_error( $result ) ) {
							$task_result = true;
						}
					} catch ( Exception $e ) {
						//$task_result = false;
					}
				}
			}

			return $task_result;
		}

		/**
		 * Install theme.
		 *
		 * @param $result
		 *
		 * @return mixed
		 */
		public function install_theme( $result ) {
			// validate
			if ( ! $this->validate_request() ) {
				return array( "success" => false );
			}

			require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
			include_once ABSPATH . 'wp-admin/includes/theme.php';

			$slug = isset( $_REQUEST['slug'] ) ? sanitize_title_for_query( $_REQUEST['slug'] ) : '';
			$download_link = !empty($_REQUEST['download_link']) ? esc_url_raw($_REQUEST['download_link']) : '';



			if(empty($download_link)){
				$api = themes_api(
					'theme_information',
					array(
						'slug'   => $slug,
						'fields' => array( 'sections' => false ),
					)
				);

				if ( is_wp_error( $api ) ) {
					array( "success" => false );
				}

				$download_link = $api->download_link;

			}


			$skin     = new WP_Ajax_Upgrader_Skin();
			$upgrader = new Theme_Upgrader( $skin );
			$install    = $upgrader->install( $download_link );

			if ( $install ) {
				$result = array( "success" => true );
			}

			return $result;
		}


	}

}