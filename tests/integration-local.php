<?php
// Run on a WordPress site whose APIs point at a local Worker (see mu-plugins/ayecode-local-api.php):
//   wp eval-file wp-content/plugins/ayecode-connect/tests/integration-local.php
// Requires: FX_VALID env (a licence key for item 65056), the local Worker with dev tokens.
$GLOBALS['W'] = $W = 'http://127.0.0.1:8787'; $SYNC = 'dev-store-sync-token'; $FS = 'dev-freescout-token';
$KEY = getenv( 'FX_VALID' ); if ( ! $KEY ) { WP_CLI::error( 'set FX_VALID' ); }
$GLOBALS['pass'] = 0; $GLOBALS['fail'] = 0;
function chk( $n, $ok, $info = '' ) { WP_CLI::line( ( $ok ? 'ok   ' : 'FAIL ' ) . $n . ( $info ? "  $info" : '' ) ); $ok ? $GLOBALS['pass']++ : $GLOBALS['fail']++; }
function api( $m, $p, $b = null, $h = array() ) { $r = wp_remote_request( $GLOBALS['W'] . $p, array( 'method' => $m, 'headers' => array_merge( array( 'content-type' => 'application/json' ), $h ), 'body' => $b ? wp_json_encode( $b ) : null, 'timeout' => 20 ) ); return array( wp_remote_retrieve_response_code( $r ), json_decode( wp_remote_retrieve_body( $r ), true ) ); }

global $ayecode_connect; $c = $ayecode_connect; $prefix = $c->prefix;
// clean slate
$c->disconnect_site( false ); delete_option( 'exup_site_token' ); delete_option( 'exup_keys' ); delete_option( $prefix . '_blog_token' ); delete_option( 'ayecode_connect_seen_jti' );

// ---- 1. registration via the API (pull model, local site) ----
$secret = $c->get_activation_secret();
chk( 'local domain is flagged local, not blocked', $c->is_local_domain() === true );
list( $s, $r ) = api( 'POST', '/v1/sync', array( 'ops' => array( array( 'entity' => 'pending_connection', 'op' => 'upsert', 'data' => array( 'secret_hash' => hash( 'sha256', $secret ), 'user_id' => 3036, 'url' => home_url() ) ) ) ), array( 'authorization' => 'Bearer ' . $SYNC ) );
chk( 'store wrote pending connection', $s === 200 && ! empty( $r['ok'] ) );
chk( 'try_register succeeds', $c->api->try_register() === true );
chk( 'site id and token stored', $c->get_blog_id() > 0 && strlen( $c->api->get_site_token() ) >= 32, 'site ' . $c->get_blog_id() );
chk( 'is_registered / is_active', $c->is_registered() && $c->is_active() );
chk( 'connected user stored', get_option( $prefix . '_connected_username' ) === 'atest2' );
chk( 'uses 2.0 api (not legacy)', ! $c->use_legacy_api() && strpos( $c->get_api_url( '/me/' . $c->get_blog_id() ), '/v1/connect/me' ) !== false );

// ---- 1b. a site connected through the old server exchanges its legacy token for an API token ----
{
	$lid = 999998; $bt = 'legacy-' . wp_generate_password( 24, false );
	list( $s, $r ) = api( 'POST', '/v1/sync', array( 'source' => 'edd', 'ops' => array( array( 'entity' => 'site', 'op' => 'upsert', 'data' => array( 'id' => $lid, 'url' => home_url(), 'user_id' => 3036, 'home_url' => home_url(), 'blog_token' => $bt ) ) ) ), array( 'authorization' => 'Bearer ' . $SYNC ) );
	chk( 'old server mirrored the legacy site', $s === 200 && ! empty( $r['ok'] ) );
	$saved = array( get_option( $prefix . '_blog_id' ), get_option( $prefix . '_blog_token' ), $c->api->get_site_token() );
	update_option( $prefix . '_blog_id', $lid ); update_option( $prefix . '_blog_token', $bt ); delete_option( $prefix . '_site_token' ); delete_transient( $prefix . '_token_exchange_tried' );
	chk( 'legacy-only site uses the legacy api', $c->use_legacy_api() );
	chk( 'exchange_legacy_token succeeds', $c->api->maybe_exchange_legacy_token() === true );
	chk( 'site token stored, 2.0 api now used', strlen( $c->api->get_site_token() ) >= 32 && ! $c->use_legacy_api() );
	chk( 'exchange is a no-op once a token exists', $c->api->maybe_exchange_legacy_token() === false );
	api( 'DELETE', '/v1/connect/site', null, array( 'authorization' => 'Bearer ' . $c->api->get_site_token() ) );
	update_option( $prefix . '_blog_id', $saved[0] ); update_option( $prefix . '_blog_token', $saved[1] ); update_option( $prefix . '_site_token', $saved[2] );
}

// ---- 2. licence sync through the 2.0 route ----
$r = $c->sync_licences();
$keys = get_option( 'exup_keys', array() );
chk( 'sync_licences stored a key for the installed add-on', ! empty( $keys['geodir_advance_search_filters/geodir_advance_search_filters.php']->key ), implode( ',', array_keys( (array) $keys ) ) );
chk( 'all licences option populated by domain', ! empty( get_option( $prefix . '_licences' )['wpgeodirectory.com'] ) );

// ---- 3. WPEU update check goes to the API with the site token ----
chk( 'WPEU sees the Connect site token', External_Updates_API::get_token() === $c->api->get_site_token() );
$admin = new External_Updates_Admin( 'external-updates', WP_EASY_UPDATES_VERSION );
$pkgs = $admin->get_packages_for_update_by_src( 'plugin' );
$ours = array(); foreach ( $pkgs as $src => $p ) { if ( External_Updates_API::is_ours( $src ) ) { $ours += $p; } }
$info = External_Updates_API::check_updates( $ours );
$asf = isset( $info->{'geodir_advance_search_filters/geodir_advance_search_filters.php'} ) ? $info->{'geodir_advance_search_filters/geodir_advance_search_filters.php'} : null;
chk( 'API update check returns the add-on', $asf && ! empty( $asf->new_version ), $asf ? 'v' . $asf->new_version . ' license=' . $asf->license : 'none' );
chk( 'package link is a signed Worker download', $asf && strpos( $asf->package, '/dl/65056/' ) !== false );
$t = $admin->check_for_plugin_updates( (object) array( 'checked' => array(), 'response' => array() ) ); $t = $admin->check_for_plugin_updates( $t );
chk( 'check_for_updates populates the transient from the API', isset( $t->response['geodir_advance_search_filters/geodir_advance_search_filters.php'] ) || isset( $t->no_update['geodir_advance_search_filters/geodir_advance_search_filters.php'] ) );

// ---- 4. WPEU deactivate/activate via API ----
$d = $admin->deactivate_licence( 'geodir_advance_search_filters/geodir_advance_search_filters.php', $KEY, 'plugin' );
chk( 'deactivate via API', isset( $d['success'] ), wp_json_encode( $d ) );
$a = $admin->activate_licence( 'geodir_advance_search_filters/geodir_advance_search_filters.php', $KEY, 'plugin' );
chk( 'activate via API', isset( $a['success'] ), wp_json_encode( $a ) );
$a2 = $admin->activate_licence( 'geodir_advance_search_filters/geodir_advance_search_filters.php', 'deadbeefdeadbeefdeadbeefdeadbeef', 'plugin' );
chk( 'invalid key gives the invalid message', isset( $a2['error'] ) && strpos( $a2['error'], 'invalid' ) !== false, wp_json_encode( $a2 ) );

// ---- 5. command pull: FreeScout queues update_licences, the site pulls, verifies and applies it ----
delete_option( 'exup_keys' );
$installed = array( 'geodir_advance_search_filters/geodir_advance_search_filters.php' => array( 'license_key' => $KEY, 'status' => 'active', 'download_id' => 65056, 'expiration' => 0, 'parent' => 0, 'user_id' => 3036 ) );
list( $s, $cmd ) = api( 'POST', '/v1/commands', array( 'site_id' => $c->get_blog_id(), 'action' => 'update_licences', 'payload' => array( 'site_id' => $c->get_blog_id(), 'site_url' => site_url(), 'installed' => $installed, 'all' => array() ) ), array( 'authorization' => 'Bearer ' . $FS ) );
chk( 'command queued for pull', $s === 200 && $cmd['status'] === 'queued', $cmd['mode'] ?? '' );
$ran = $c->api->pull_commands();
chk( 'pull executed one command', $ran === 1 );
$keys = get_option( 'exup_keys', array() );
chk( 'pulled update_licences stored the key', ! empty( $keys['geodir_advance_search_filters/geodir_advance_search_filters.php']->key ) );
chk( 'nothing left to pull', $c->api->pull_commands() === 0 );
$log = get_option( $prefix . '_auth_paths', array() );
chk( 'auth log records envelope path', ! empty( $log['envelope'] ) );

// ---- 6. permission callback: envelope path and legacy path ----
list( $s, $pulled ) = api( 'POST', '/v1/commands', array( 'site_id' => $c->get_blog_id(), 'action' => 'test_connection', 'payload' => array() ), array( 'authorization' => 'Bearer ' . $FS ) );
$token = $c->api->get_site_token();
list( $s, $q ) = api( 'GET', '/v1/connect/commands', null, array( 'authorization' => 'Bearer ' . $token ) );
$env = $q['commands'][0];
$req = new WP_REST_Request( 'POST', '/ayecode-connect/v1/do_action' ); $req->set_param( 'envelope', $env['envelope'] ); $req->set_param( 'signature', $env['signature'] );
chk( 'has_permission accepts a signed envelope via REST', $c->has_permission( $req ) === true );
chk( 'has_permission rejects the same envelope replayed', is_wp_error( $c->has_permission( $req ) ) );
// legacy: HS256 JWT with a blog token, from a spoofed vs a real sender IP
update_option( $prefix . '_blog_token', 'legacy-test-token' );
$jwt = AyeCode_Connect::encode( array( 'blog_id' => $c->get_blog_id() ), 'legacy-test-token' );
$req2 = new WP_REST_Request( 'POST', '/ayecode-connect/v1/do_action' ); $req2->set_header( 'Authorization', 'X_AUTH ' . $jwt );
$_SERVER['REMOTE_ADDR'] = '203.0.113.9'; $_SERVER['HTTP_X_FORWARDED_FOR'] = '173.208.153.114'; $_SERVER['HTTP_CLIENT_IP'] = '173.208.153.114';
chk( 'legacy JWT with spoofed X-Forwarded-For is rejected', is_wp_error( $c->has_permission( $req2 ) ) );
$_SERVER['REMOTE_ADDR'] = '173.208.153.114';
chk( 'legacy JWT from the old server IP is accepted', $c->has_permission( $req2 ) === true );
$req3 = new WP_REST_Request( 'POST', '/ayecode-connect/v1/do_action' ); $req3->set_header( 'Authorization', 'X_AUTH ' . AyeCode_Connect::encode( array( 'blog_id' => $c->get_blog_id() ), 'wrong-token' ) );
chk( 'legacy JWT with wrong secret is rejected even from the old IP', is_wp_error( $c->has_permission( $req3 ) ) );
delete_option( $prefix . '_blog_token' );

// ---- 7. download allow-list used by install_plugin ----
if ( ! class_exists( 'AyeCode_Connect_Remote_Actions' ) ) { require_once WP_PLUGIN_DIR . '/ayecode-connect/includes/class-ayecode-connect-remote-actions.php'; }
chk( 'install allow-list accepts the API host', AyeCode_Connect_Remote_Actions::validate_download_url( str_replace( 'http://127.0.0.1:8787', 'https://api.ayecode.io', $asf->package ) ) );

// ---- 8. disconnect ----
$c->disconnect_site( true );
chk( 'disconnect clears token and keys', ! $c->api->get_site_token() && ! get_option( 'exup_keys' ) && ! $c->is_registered() );
WP_CLI::line( "\n{$GLOBALS['pass']} passed, {$GLOBALS['fail']} failed" ); if ( $GLOBALS['fail'] ) { WP_CLI::halt( 1 ); }
