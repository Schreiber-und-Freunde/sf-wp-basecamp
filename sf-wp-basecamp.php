<?
/*
Plugin Name: WP Basecamp
Description: Basecamp API Integration for Wordpress
Version: 0.1
Author: Schreiber & Freunde GmbH
Author URI: http://www.schreiber-freunde.de
*/

class SfWpBasecamp
{
	// singleton instance
	private static $instance;

	private $url;
	private $username;
	private $password;
	private $useragent;
	private $is_ready = true;
	private $result;

	public static function instance() {
		if ( isset( self::$instance ) )
			return self::$instance;

		self::$instance = new SfWpBasecamp;
		return self::$instance;
	}

	function __construct() {
		add_action( 'init', array(&$this, 'init'));
		add_action( 'admin_menu', array( &$this, 'add_pages' ), 30 );		
	}

	function init() {

		$account = get_option('basecamp_account');
		$username = get_option('basecamp_user');
		$password = get_option('basecamp_password');

		if( $account === false || $username === false || $password === false ) {
			$this->is_ready = false;
			add_action('admin_notices', array( &$this, 'admin_notice_missing_account_data'));
			return;
		}

		$this->url = 'https://basecamp.com/' . $account . '/api/v1/';
		$this->username = $username;
		$this->password = $password;

		$this->useragent = get_bloginfo( 'name' ) . ' (' . get_bloginfo( 'url' ) . ')';

		if( isset($_REQUEST['sfwp_basecamp_action']) ) {
			if( $_REQUEST['sfwp_basecamp_action'] == 'save_options' ) {
				$this->save_options();
			}

			if( $_REQUEST['sfwp_basecamp_action'] == 'test' ) {
				$this->test();
			}
		}
	}

	function admin_notice_missing_account_data() {
		echo '<div class="error"><p>' . __('WP Basecamp: Please go to the options page and fill in your account details.', 'sf_wp_basecamp') . '</p></div>';
	}

	function add_pages() {
		add_options_page( 'Basecamp', 'Basecamp', 'manage_options', 'sfwp_basecamp_options', array( &$this, 'page_options'));
	}

	function save_options() {

		if ( ! wp_verify_nonce( $_REQUEST['_wpnonce'] , 'sfwp_basecamp_action_save_options' ) ) {
			wp_die( __('Nonce check failed', 'sf_wp_basecamp') );
			return;
		}
		
		if( isset($_REQUEST['basecamp_account']) ) {
			update_option('basecamp_account', trim($_REQUEST['basecamp_account']) );
		}

		if( isset($_REQUEST['basecamp_user']) ) {
			update_option('basecamp_user', trim($_REQUEST['basecamp_user']) );
		}

		if( isset($_REQUEST['basecamp_password']) ) {
			update_option('basecamp_password', trim($_REQUEST['basecamp_password']) );
		}
	}

	function page_options() {
		?>
		<div class="wrap">
			<h2><? _e('Settings', 'sf_wp_basecamp'); ?> › <? _e('Basecamp', 'sf_wp_basecamp') ?></h2>
			<form action="<?php echo $_SERVER['REQUEST_URI']; ?>" method="post">
				<input type="hidden" name="sfwp_basecamp_action" value="save_options" />
				<input type="hidden" name="_wpnonce" value="<? echo wp_create_nonce( 'sfwp_basecamp_action_save_options' ) ?>" />
				<table class="form-table">
					<tr>
						<th><label for="basecamp_account"><? _e('Account ID', 'sf_wp_basecamp') ?></label></th>
						<td><input name="basecamp_account" id="basecamp_account" type="text" value="<? echo get_option('basecamp_account') ?>" /></td>
					</tr>
					<tr>
						<th><label for="basecamp_user"><? _e('Username', 'sf_wp_basecamp') ?></label></th>
						<td><input name="basecamp_user" id="basecamp_user" type="text" value="<? echo get_option('basecamp_user') ?>" /></td>
					</tr>
					<tr>
						<th><label for="basecamp_password"><? _e('Password', 'sf_wp_basecamp') ?></label></th>
						<td><input type="password" name="basecamp_password" id="basecamp_password" type="text" value="<? echo get_option('basecamp_password') ?>" /></td>
					</tr>
				</table>
				<p class="submit"><input type="submit" value="<? _e('Save Settings', 'sf_wp_basecamp') ?>" class="button-primary" /></p>
			</form>
			<h3><? _e('Test', 'sf_wp_basecamp') ?></h3>
			<form action="<?php echo $_SERVER['REQUEST_URI']; ?>" method="post">
				<input type="hidden" name="sfwp_basecamp_action" value="test" />
				<input type="hidden" name="_wpnonce" value="<? echo wp_create_nonce( 'sfwp_basecamp_action_test' ) ?>" />
				<p class="submit"><input type="submit" value="<? _e('Test Settings', 'sf_wp_basecamp') ?>" class="button-primary" /></p>
			</form>
			<? if( isset($this->result) ) : ?>
			<h3><? _e('Test Result', 'sf_wp_basecamp') ?></h3>
			<? echo '<pre>' . print_r( json_decode( $this->result ), true) . '</pre>'; ?>
			<? endif; ?>
		</div>
		<?
	}

	private function test() {
		
		if ( ! wp_verify_nonce( $_REQUEST['_wpnonce'] , 'sfwp_basecamp_action_test' ) ) {
			wp_die( __('Nonce check failed', 'sf_wp_basecamp') );
			return;
		}

		if( !$this->is_ready ) {
			return false;
		}

		$this->result = $this->get_todo_count(false);
	}

	public function do_request($method) {
		if( !$this->is_ready ) {
			return false;
		}
		
		$curl = curl_init();

		if ($data) {
			curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
		}

		curl_setopt($curl, CURLOPT_HTTPHEADER, array(
			'User-Agent: ' . $this->useragent
		));
		
		curl_setopt( $curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC );
		curl_setopt( $curl, CURLOPT_USERPWD, $this->username . ':' . $this->password );

		curl_setopt( $curl, CURLOPT_URL, $this->url . $method );
		curl_setopt( $curl, CURLOPT_RETURNTRANSFER, 1 );

		return curl_exec($curl);
	}

	public function get_todo_count( $open = true ) {
		
		$count = 0;

		if( $open ) {
			$todolists = json_decode( $this->do_request('todolists.json') );
			
			foreach ($todolists as $todolist) {
				$count += $todolist->remaining_count;
			}
			
		} else {
			$todolists = json_decode( $this->do_request('todolists.json') );

			foreach ($todolists as $todolist) {
				$count += $todolist->completed_count;
			}

			$todolists = json_decode( $this->do_request('todolists/completed.json') );

			foreach ($todolists as $todolist) {
				$count += $todolist->completed_count;
			}
		}

		return $count;
	}
}
$sf_wp_basecamp = SfWpBasecamp::instance();
function basecamp_get_todo_count($open = false) {
	return SfWpBasecamp::instance()->get_todo_count( $open );
}
?>
