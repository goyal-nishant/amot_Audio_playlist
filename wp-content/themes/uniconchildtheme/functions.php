<?php


if (!function_exists('pr')) {
	function pr($p, $func = "print_r", $r = false)
	{
		if (!$func)
			$func = 'print_r';
		$bt = debug_backtrace();
		$caller = array_shift($bt);
		$file_line = "<strong>" . $caller['file'] . "(line " . $caller['line'] . ")</strong>\n";
		if (!$r) { //if print
			echo '<pre>';
			print_r($file_line);
			$func($p);
			echo '</pre>';
		} else { //if return
			ob_start();
			echo '<pre>';
			print_r($file_line);
			$func($p);
			echo '<pre>';
			$d = ob_get_contents();
			ob_end_clean();
			if (filter_var($r, FILTER_VALIDATE_EMAIL)) {
				$headers = 'From: webmaster@example.com' . "\r\n" .
					'Reply-To: webmaster@example.com' . "\r\n" .
					'X-Mailer: PHP/' . phpversion();
				mail($r, 'Debug Output', $d, $headers);
			}
			return $d;
		}
	}
}
if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

$pluginList = apply_filters('active_plugins', get_option('active_plugins'));
$pluginWoocommerce = 'woocommerce/woocommerce.php';
$pluginMembership = 'woocommerce-memberships/woocommerce-memberships.php';
$pluginSubscription = 'woocommerce-subscriptions/woocommerce-subscriptions.php';

function unicon_child_enqueue_styles()
{

	$parent_style = 'unicon-style';
	$child_style = 'unicon-child-style';
	$child_version = 'v1';

	wp_enqueue_style($parent_style, get_template_directory_uri() . '/style.css');
	wp_enqueue_style($child_style, get_stylesheet_directory_uri() . '/style.css', array($parent_style), wp_get_theme()->get('Version') . $child_version);
	wp_enqueue_style('unicon-child-custom-style', get_stylesheet_directory_uri() . '/unicon-custom.css', array($child_style), wp_get_theme()->get('Version') . $child_version);
}
add_action('wp_enqueue_scripts', 'unicon_child_enqueue_styles');

function unicon_child_woocommerce_before_shop_loop()
{
	$search = (empty($_GET["s"]) ? "" : $_GET["s"]);
	?>
	<form class="woocommerce-ordering" method="get">
		<input name="s" placeholder="Search" type="text" class="woocommerce-search-input" value="<?php echo $search; ?>">
		<?php
		// Keep query string vars intact
		foreach ($_GET as $key => $val) {
			if ('s' === $key || 'submit' === $key) {
				continue;
			}
			if (is_array($val)) {
				foreach ($val as $innerVal) {
					echo '<input type="hidden" name="' . esc_attr($key) . '[]" value="' . esc_attr($innerVal) . '" />';
				}
			} else {
				echo '<input type="hidden" name="' . esc_attr($key) . '" value="' . esc_attr($val) . '" />';
			}
		}
		?>
	</form>
	<?php
}
remove_action('woocommerce_before_shop_loop', 'woocommerce_catalog_ordering', 30);
add_action('woocommerce_before_shop_loop', 'unicon_child_woocommerce_before_shop_loop', 30);

function unicon_child_woocommerce_min_password_strength($strength)
{
	// 3 => Strong (default) | 2 => Medium | 1 => Weak | 0 => Very Weak (anything).
	return 1;
}
add_filter('woocommerce_min_password_strength', 'unicon_child_woocommerce_min_password_strength');

function unicon_child_remove_password_strength()
{
	if (wp_script_is('wc-password-strength-meter', 'enqueued')) {
		wp_dequeue_script('wc-password-strength-meter');
	}
}
add_action('wp_print_scripts', 'unicon_child_remove_password_strength', 100);



if (
	in_array($pluginWoocommerce, $pluginList)
	&& in_array($pluginMembership, $pluginList)
	&& in_array($pluginSubscription, $pluginList)
) {
	// define the woocommerce_before_add_to_cart_form callback

	function amotaudio_woocommerce_inject_music_player()
	{
		if (is_user_logged_in()) {
			if (!function_exists('wc_memberships')) {
				return;
			}
			global $product;
			$current_user = wp_get_current_user();
			amotaudio_showMusicPlayer($product, $current_user, true);
		}
	}

	function amotaudio_woocommerce_inject_music_players()
	{
		if (is_user_logged_in()) {
			if (!function_exists('wc_memberships')) {
				return;
			}
			global $product;
			$current_user = wp_get_current_user();
			amotaudio_showMusicPlayer($product, $current_user);
		}
	}
	// add the action 
	add_action('woocommerce_before_add_to_cart_form', 'amotaudio_woocommerce_inject_music_player', 9);
	add_action('woocommerce_after_shop_loop_item', 'amotaudio_woocommerce_inject_music_players', 9);


	function amotaudio_getAudioFile($audioFiles)
	{
		foreach ($audioFiles as $audioFile) {
			if (stripos($audioFile['file'], '.mp3') !== false) {
				return $audioFile;
			}
		}
		return false;
	}

	function amotaudio_getAudioFiles($product)
	{
		$downloads = array();
		if ($product->is_type('variable')) {
			$variations = array_reverse($product->get_available_variations());
			if ($variations) {
				foreach ($variations as $variation) {
					$variation_id = $variation['variation_id'];
					$single_variation = new WC_Product_Variation($variation_id);
					$variationAttributes = $single_variation->get_variation_attributes();
					if (
						(stripos($variationAttributes['attribute_audio-type'], "mp3") !== false
							|| stripos($variationAttributes['attribute_pa_audio-type'], "mp3") !== false)
						&& $variation['is_in_stock']
					) {
						$downloads = get_post_meta($variation_id, '_downloadable_files', true);
						break;
					}
				}
			}
		} else {
			if ($product->is_in_stock()) {
				$downloads = get_post_meta($product->id, '_downloadable_files', true);
			}
		}
		return $downloads;
	}

	function amotaudio_showMusicPlayer($product, $current_user, $showName = false)
	{
		/* if user bought the product wc_customer_bought_product( $current_user->email, $current_user->ID, $product->id ) */
		if (current_user_can('administrator') || wc_memberships_is_user_active_member($current_user->ID, 'member')) {
			//$audioFiles = $product->get_downloads();
			$audioFiles = amotaudio_getAudioFiles($product);
			if (count($audioFiles) > 0) {
				$audioFile = amotaudio_getAudioFile($audioFiles);
				if ($audioFile !== false) {
					$attr = array(
						'src' => $audioFile['file'],
						'loop' => '',
						'autoplay' => '',
						'preload' => 'none'
					);
					if ($showName) {
						echo '<h1 style="font-size: 22px; margin-bottom: 6px;" >' . $audioFile['name'] . '</h1>';
					}
					echo wp_audio_shortcode($attr);
				}
			}
		}
	}
}

function amotaudio_add_custom_subscription_interval($subscription_intervals)
{
	$subscription_intervals['13'] = sprintf(__('every %s', 'woocommerce-subscriptions'), WC_Subscriptions::append_numeral_suffix(13));
	return $subscription_intervals;
}
add_filter('woocommerce_subscription_period_interval_strings', 'amotaudio_add_custom_subscription_interval');

function aa_minti_js_custom()
{
	global $minti_data; ?>
	<script>
		<?php if ($minti_data['switch_simpleselect'] == 1) { ?>
			jQuery(document).ready(function ($) {
				$(".variations_form select").change(function (e) {
					e.preventDefault();
					$(".simpleselect .placeholder").html(this.options[this.selectedIndex].text);
				});
			});
		<?php } ?>
	</script>
	<?php
}
add_action('wp_footer', 'aa_minti_js_custom', 101);


function aa_default_catalog_orderby_exclusives()
{
	if (is_product_category('amot-exclusives'))
		return 'date';
}
add_filter('woocommerce_default_catalog_orderby', 'aa_default_catalog_orderby_exclusives');


// mark paymant as done uaing the hook
/**
 * Auto Complete all WooCommerce orders.
 */
add_action('woocommerce_thankyou', 'custom_woocommerce_auto_complete_order');
function custom_woocommerce_auto_complete_order($order_id)
{
	if (!$order_id) {
		return;
	}

	$order = wc_get_order($order_id);
	$order->update_status('completed');
}


add_action('wp_enqueue_scripts', 'add_my_script');
function add_my_script()
{
	wp_enqueue_script(
		'your-script',
		get_stylesheet_directory_uri() . '/js/script.js',
		array('jquery'),
		false,
		false
	);
	wp_enqueue_script('framework-function', get_stylesheet_directory_uri() . '/framework/js/functions.js', array('jquery'), false, false);
}

add_action('pre_get_posts', 'my_change_sort_order');
function my_change_sort_order($query)
{
	if (is_product_category('aa-singles') || is_product_category('al-anon-singles')):
		//If you wanted it for the archive of a custom post type use: is_post_type_archive( $post_type )
		//Set the order ASC or DESC
		$query->set('order', 'ASC');
		//Set the orderby
		$query->set('orderby', 'title');
	endif;
}
;

add_filter('tribe_events_add_no_index_meta', '__return_false');

add_action('pre_get_posts', 'recent_recording_sort_order');
function recent_recording_sort_order($query)
{
	if (is_product_category('amot-recent-recordings')):
		//If you wanted it for the archive of a custom post type use: is_post_type_archive( $post_type )
		//Set the order ASC or DESC
		$query->set('order', 'DESC');
		//Set the orderby
		$query->set('orderby', 'date');
	endif;
}
;

/*
 * Sign In
 */
add_action('rest_api_init', 'login');

/*
 * Registers REST route for user login.
 */

function login()
{
	register_rest_route(
		'v1',
		'login',
		array(
			'methods' => 'POST',
			'callback' => 'rest_api_user_login',
		)
	);
}

/*
 * Callback function to handle user login via REST API.
 * It expects 'username' and 'password' to be sent in the request parameters.
 */
function rest_api_user_login($request)
{
	// Retrieve parameters from the request body
	$parameters = $request->get_json_params();

	// Extract username and password from the parameters
	$username = $parameters['username'];
	$password = $parameters['password'];

	// Authenticate user with provided username and password
	$user = wp_authenticate($username, $password);

	// If authentication fails, return error
	if (is_wp_error($user)) {
		return new WP_Error('rest_login_failed', 'Invalid username or password.', array('status' => 401));
	}

	// Set current user and authentication cookie
	wp_set_current_user($user->ID);
	wp_set_auth_cookie($user->ID);

	// Generate JWT token for user
	$token = jwt_generate_token($user);

	// Response data
	$response = array(
		'success' => true,
		'token' => $token,
		'data' => array(
			'user_id' => $user->ID,
			'user_email' => $user->user_email,
			'user_nicename' => $user->user_nicename,
			'user_display_name' => $user->display_name,
		),
	);

	// Return response
	return new WP_REST_Response($response, 200);
}

/*
 * Generates a JSON Web Token (JWT) for the given user.
 * It expects user info to create a token.
 */
function jwt_generate_token($user)
{
	// Define a secret key for JWT encoding
	$secret_key = defined('SECRET_KEY') ? SECRET_KEY : '';

	// Payload data for the JWT
	$payload = array(
		'user_id' => $user->ID,
		'user_email' => $user->data->user_email,
		'user_nicename' => $user->data->user_nicename,
		'user_display_name' => $user->data->display_name,
		'iat' => time(),
		'exp' => time() + (7 * 24 * 60 * 60),
	);

	// Check if the Firebase JWT library is loaded, if not, load it
	if (!class_exists('Firebase\JWT\JWT')) {
		require 'vendor/firebase/php-jwt/src/JWT.php';
	}

	// Attempt to encode the payload into a JWT token
	try {
		$token = \Firebase\JWT\JWT::encode($payload, $secret_key, 'HS256');
	} catch (Exception $e) {
		// If an error occurs during encoding, return a WP_Error with status 500
		return new WP_Error('jwt_encode_error', 'Error encoding token: ' . $e->getMessage(), array('status' => 500));
	}

	// Return the generated JWT token
	return $token;
}

/* 
 * Verify token for authorization
 * It expects authorization header in the request.
 */

function verify_jwt_token($request)
{
	// Get the token from the request headers
	$token = $request->get_header('Authorization');
	if (!$token) {
		return new WP_Error('jwt_missing', 'JWT token is missing', array('status' => 401));
	}

	// Extract JWT token from the Authorization header
	list($jwt) = sscanf($token, 'Bearer %s');
	if (!$jwt) {
		return new WP_Error('jwt_invalid', 'Invalid JWT token', array('status' => 401));
	}

	// Check if the Firebase JWT library is loaded, if not, load it
	if (!class_exists('Firebase\JWT\JWT')) {
		require 'vendor/autoload.php';
	}

	// Verify JWT token
	$secret_key = defined('SECRET_KEY') ? SECRET_KEY : '';
	try {
		$decoded = \Firebase\JWT\JWT::decode($jwt, new \Firebase\JWT\Key($secret_key, 'HS256'));
		$user_id = $decoded->user_id;

		$request->set_param('user_id', $user_id);
	} catch (Exception $e) {
		return new WP_Error('jwt_invalid', 'Invalid JWT token: ' . $e->getMessage(), array('status' => 401));
	}

	// Check token expiry
	if (isset($decoded->exp) && $decoded->exp < time()) {
		return new WP_Error('jwt_expired', 'JWT token has expired', array('status' => 401));
	}

	// Token is valid
	return true;
}

/*
 * Sign Up
 */
add_action('rest_api_init', function () {
	register_rest_route(
		'v1',
		'/create-user',
		array(
			'methods' => 'POST',
			'callback' => 'rest_create_user',
		)
	);
});

/*
 * Callback function to handle user registration via REST API.
 * It expects 'username', 'email', and 'password' to be sent in the request parameters.
 */
function rest_create_user($request)
{
	// Retrieve parameters from the request body
	$parameters = $request->get_params();
	$username = $parameters['username'];
	$password = $parameters['password'];

	// Check if required parameters are provided
	if (empty($parameters['username']) || empty($parameters['email']) || empty($parameters['password'])) {
		return new WP_REST_Response('Missing required parameters.', 400);
	}

	// Check if username or email already exists
	if (username_exists($parameters['username']) || email_exists($parameters['email'])) {
		return new WP_REST_Response('Username or email already exists.', 400);
	}
	$user = wp_authenticate($username, $password);

	// Insert new user into the database
	$user_id = wp_insert_user(
		array(
			'user_login' => $parameters['username'],
			'user_email' => $parameters['email'],
			'user_pass' => $parameters['password'],
		)
	);

	// Check if user creation was successful
	if (is_wp_error($user_id)) {
		return new WP_REST_Response('Failed to create user.', 500);
	}

	// Retrieve user data
	$user = get_user_by('id', $user_id);

	// Check if user data retrieval was successful
	if (!$user) {
		return new WP_REST_Response('Failed to retrieve user data.', 500);
	}

	// Set current user and authentication cookie
	wp_set_current_user($user->ID);
	wp_set_auth_cookie($user->ID);

	// Generate JWT token for user
	$token = jwt_generate_token($user);

	// Response data
	$response = array(
		'success' => true,
		'token' => $token,
		'data' => array(
			'user_id' => $user->ID,
			'user_email' => $user->user_email,
			'user_nicename' => $user->user_nicename,
			'user_display_name' => $user->display_name,
		),
	);

	// Return response
	return new WP_REST_Response($response, 200);
}

/*
 * Get Playlist
 */
add_action('rest_api_init', 'playlist');

/*
 * Registers REST route for playlist.
 */
function playlist()
{
	register_rest_route(
		'v1',
		'/playlists/free',
		array(
			'methods' => 'GET',
			'callback' => 'get_playlist_data',
			'permission_callback' => 'verify_jwt_token',
		)
	);
}

/*
 * Callback function to get playlist via REST API.
 */
function get_playlist_data()
{
	$post_type = 'bb_playlist_player';
	$page = $_GET['page'];
	$dataArray = [];
	$posts = new WP_Query([
		'post_type' => $post_type,
		'posts_per_page' => '15',
		'paged' => $page,
		'meta_query' => [
			[
				'key' => 'membership_type',
				'value' => 'Unpaid',
				'compare' => 'like'
			]
		],
	]);
	// Handle errors
	if (empty($posts)) {
		return new WP_Error('no_data', 'No playlist data found', array('status' => 404));
	}

	// current working

	// echo "here";
	// echo "<pre>";
	// print_r($posts->have_posts());
	// echo"</pre>";


	while ($posts->have_posts()) {
		$posts->the_post();
		array_push($dataArray, [
			'id' => get_the_ID(),
			'post_title' => html_entity_decode(get_the_title()),
			'thumbnail' => get_the_post_thumbnail_url(),
			'audios' => get_post_meta(get_the_ID(), 'bb_playlist', true),
		]);
	}
	// Prepare and return response
	$response = array(
		'success' => true,
		'data' => $dataArray,
	);

	// Return response
	return new WP_REST_Response($response, 200);
}

/*
 * Single Audio
 */
add_action('rest_api_init', 'audio');

/*
 * Registers REST route for single audio.
 */

function audio()
{
	register_rest_route(
		'v1',
		'/playlist/(?P<post_id>\d+)',
		array(
			'methods' => 'GET',
			'callback' => 'get_audio_data',
			'permission_callback' => 'verify_jwt_token',
		)
	);
}

/*
 * Callback function to get audio via REST API.
 * It expects post id here.
 */
function get_audio_data($data)
{

	$post_id = $data['post_id'];

	// Get the author of the post
	$author_id = get_post_field('post_author', $post_id);
	$author_name = get_the_author_meta('display_name', $author_id);

	// Your code to retrieve data based on the post ID
	global $wpdb;

	// Retrieve postmeta data
	$meta_key = 'bb_playlist';
	$meta_query = $wpdb->prepare("
		 SELECT meta_value
		 FROM {$wpdb->prefix}postmeta
		 WHERE post_id = %d
		 AND meta_key = %s
	 ", $post_id, $meta_key);
	$meta_results = $wpdb->get_results($meta_query);



	// Handle errors or no data found
	if (empty($meta_results)) {
		return new WP_Error('no_data', 'No playlist data found', array('status' => 404));
	}

	// Convert serialized data to array
	$playlist_data = maybe_unserialize($meta_results[0]->meta_value);


	$meta_key = '_thumbnail_id';
	$thumbnail_id = get_post_meta($post_id, $meta_key, true);
	$thumbnail_post = get_post($thumbnail_id);
	$thumbnail = $thumbnail_post->guid;

	$post_data = get_post($post_id);
	$post_title = $post_data->post_title;
	// echo "<pre>""</pre>"

	$categories = wp_get_post_terms($post_id, 'playlist_category');
	// $categories = wp_get_post_terms($post_id, 'category');
	// $categories = wp_get_post_categories($post_id);

	$cats = [];
	foreach ($categories as $record) {
		$cats[] = $record->name;
	}

	// print_r($cats);
	// Prepare and return response
	$response = array(
		'success' => true,
		'author' => $author_name,
		'data' => $playlist_data,
		'thumbnail' => $thumbnail,
		'title' => $post_title,
		'categories' => $cats
	);

	// Return response
	return new WP_REST_Response($response, 200);
}

/*
 * Add/Remove playlist to favorite
 */
add_action('rest_api_init', 'favorite_playlist_endpoint');

/*
 * Registers REST route for adding/removing playlist to/from favorites.
 */
function favorite_playlist_endpoint()
{
	register_rest_route(
		'v1',
		'/favorite-playlist',
		array(
			'methods' => 'POST',
			'callback' => 'add_to_favorite_playlist',
			'permission_callback' => 'verify_jwt_token',
		)
	);
}

/*
 * Callback function to add playlist to favorite via REST API.
 * It expects user id and product id.
 */
function add_to_favorite_playlist($request)
{
	global $wpdb;

	$parameters = $request->get_json_params();

	// Check if required parameters are provided
	if (empty($parameters['user_id']) || empty($parameters['product_id'])) {
		return new WP_Error('invalid_parameters', 'Required parameters are missing', array('status' => 400));
	}

	$user_id = intval($parameters['user_id']);
	$product_id = intval($parameters['product_id']);

	$table_name = $wpdb->prefix . 'favourite_playlist';
	$existing_data = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE user_id = %d AND product_id = %d", $user_id, $product_id));

	// Toggle the status if data exists, insert new data otherwise
	if ($existing_data) {
		$new_status = $existing_data->status == 1 ? 0 : 1;
		$wpdb->update($table_name, array('status' => $new_status), array('user_id' => $user_id, 'product_id' => $product_id));
		$message = 'Status updated successfully';
	} else {
		$data = array(
			'user_id' => $user_id,
			'product_id' => $product_id,
			'status' => 1,
		);
		$format = array('%d', '%d', '%s');
		$success = $wpdb->insert($table_name, $data, $format);

		if (!$success) {
			return new WP_Error('insert_failed', 'Failed to add to favorite playlist', array('status' => 500));
		}

		$message = 'Added to favorite playlist successfully';
	}

	// Prepare and return response
	$response = array(
		'success' => true,
		'message' => $message,
	);

	// Return response
	return new WP_REST_Response($response, 200);
}

/*
 * Get favorite playlist
 */
add_action('rest_api_init', 'get_favorite_playlist');

/*
 * Registers REST route for getting favorite playlist.
 */
function get_favorite_playlist()
{
	register_rest_route(
		'v1',
		'/favorited-playlist/(?P<user_id>\d+)',
		array(
			'methods' => 'GET',
			'callback' => 'get_fav_playlist',
			'permission_callback' => 'verify_jwt_token',
		)
	);
}

/*
 * Callback function to get favorite playlist via REST API.
 * It expects user id in its request.
 */
function get_fav_playlist($request)
{
	global $wpdb;

	// Retrieve the user ID from the request
	$user_id = $request['user_id'];

	// Retrieve data from the tables
	$posts_table_name = $wpdb->prefix . 'posts';
	$favorite_table_name = $wpdb->prefix . 'favourite_playlist';
	$post_type = 'bb_playlist_player';
	$query = $wpdb->prepare("
        SELECT p.*
        FROM $posts_table_name p
        INNER JOIN $favorite_table_name fp ON p.ID = fp.product_id
        WHERE p.post_type = %s
        AND p.post_status = 'publish'
        AND fp.status = 1
        AND fp.user_id = %d", $post_type, $user_id);

	$results = $wpdb->get_results($query);

	// Handle errors
	if (empty($results)) {
		return new WP_Error('no_data', 'No playlist data found', array('status' => 404));
	}

	// Prepare and return response
	$response = array(
		'success' => true,
		'data' => $results,
	);

	// Return response
	return new WP_REST_Response($response, 200);
}

/* 
 *Custom Categories Audio Playlist Player 
 */
function create_category_taxonomies()
{
	// Add new taxonomy, NOT hierarchical (like tags)
	$labels = array(
		'name' => _x('Category', 'taxonomy general name', 'textdomain'),
		'singular_name' => _x('Category', 'taxonomy singular name', 'textdomain'),
		'search_items' => __('Search Category', 'textdomain'),
		'menu_name' => __('Category', 'textdomain'),
		'meta_box_cb' => 'post_categories_meta_box', // Enable thumbnail support
	);
	register_taxonomy(
		'playlist_category',
		'bb_playlist_player',
		array(
			'hierarchical' => true,
			'labels' => $labels,
			'rewrite' => array('slug' => 'playlist_category'),
		)
	);

	$labels = array(
		'name' => _x('Tag', 'taxonomy tag name', 'textdomain'),
		'singular_name' => _x('Tag', 'taxonomy singular name', 'textdomain'),
		'search_items' => __('Search Tag', 'textdomain'),
		'menu_name' => __('Tag', 'textdomain'),
		'meta_box_cb' => 'post_categories_meta_box', // Enable thumbnail support
	);
	register_taxonomy(
		'playlist_tag',
		'bb_playlist_player',
		array(
			'hierarchical' => true,
			'labels' => $labels,
			'rewrite' => array('slug' => 'playlist_tag'),
		)
	);
}
add_action('init', 'create_category_taxonomies', 0);



// Fetch all product categories

// // Register taxonomy for bb_playlist_player
// function register_bb_playlist_player_taxonomy()
// {
// 	register_taxonomy_for_object_type('product_cat', 'bb_playlist_player');
// }
// add_action('init', 'register_bb_playlist_player_taxonomy');



// Fetch all product categories
$product_categories = get_terms(
	array(
		'taxonomy' => 'product_cat',
		'hide_empty' => false, // Set to true if you want to hide empty categories
	)
);

// Check if categories were found
if (!empty($product_categories) && !is_wp_error($product_categories)) {
	// Loop through each category
	foreach ($product_categories as $category) {
		// Add this category to your custom post type
		wp_set_post_terms($post_id, $category->term_id, 'product_cat', true);
	}
}


/*
 * Add audio to favorites
 */
add_action('rest_api_init', 'favorite_audio_endpoint');

/*
 * Registers REST route for adding audio to favorites.
 */
function favorite_audio_endpoint()
{
	register_rest_route(
		'v1',
		'/set-favorite-audio',
		array(
			'methods' => 'POST',
			'callback' => 'add_to_favorite_audio',
			'permission_callback' => 'verify_jwt_token',
		)
	);
}

/*
 * Callback function to add audio to favorites via REST API.
 * It expects user id, playlist id, url and audio name.
 */
function add_to_favorite_audio($request)
{

	$parameters = $request->get_json_params();

	// Check if required parameters are provided
	if (empty($parameters['user_id']) || empty($parameters['playlist_id']) || empty($parameters['audio_name']) || empty($parameters['url'])) {
		return new WP_Error('invalid_parameters', 'Required parameters are missing', array('status' => 400));
	}

	$user_id = intval($parameters['user_id']);
	$playlist_id = intval($parameters['playlist_id']);
	$audio_name = sanitize_text_field($parameters['audio_name']);
	$url = esc_url_raw($parameters['url']);

	$fav_data = array(
		'post_title' => $audio_name,
		'post_type' => 'favorite',
		'post_status' => 'publish',
		'post_author' => $user_id,
	);

	// Insert post to favorites
	$post_id = wp_insert_post($fav_data);
	if (!is_wp_error($post_id)) {
		// Update post meta
		update_post_meta($post_id, 'playlist_id', $playlist_id);
		update_post_meta(
			$post_id,
			'track',
			array(
				'name' => $audio_name,
				'url' => $url,
				'author' => $user_id,
			)
		);

		$message = 'Added to favorite audio successfully';

		// Prepare response
		$response = array(
			'success' => true,
			'post_id' => $post_id,
			'message' => $message,
		);

		// Return response
		return new WP_REST_Response($response, 200);
	} else {
		// Return error 
		return new WP_Error('insert_error', 'Error occurred while adding to favorites', array('status' => 500));
	}
}

/*
 * Remove audio from fav
 */
add_action('rest_api_init', 'remove_favorite_audio_endpoint');

/*
 * Registers REST route for removing audio from favorites.
 */
function remove_favorite_audio_endpoint()
{
	register_rest_route(
		'v1',
		'/remove-favorite-audio',
		array(
			'methods' => 'DELETE',
			'callback' => 'delete_favorite_audio',
			'permission_callback' => 'verify_jwt_token',
		)
	);
}

/*
 * Callback function to remove audio from favorites via REST API.
 * It expects post id in the request.
 */
function delete_favorite_audio($request)
{
	$parameters = $request->get_json_params();

	if (empty($parameters['post_id'])) {
		return new WP_Error('invalid_parameters', 'Required parameter "post_id" is missing', array('status' => 400));
	}

	$post_id = intval($parameters['post_id']);

	// Check if the post exists
	if (!get_post($post_id)) {
		return new WP_Error('post_not_found', 'Post not found', array('status' => 404));
	}

	// Delete post meta
	delete_post_meta($post_id, 'playlist_id');
	delete_post_meta($post_id, 'track');

	// Delete the post
	if (wp_delete_post($post_id)) {
		$message = 'Post and associated meta deleted successfully';
		$response = array(
			'success' => true,
			'message' => $message,
		);
		return new WP_REST_Response($response, 200);
	} else {
		return new WP_Error('delete_error', 'Error occurred while deleting post', array('status' => 500));
	}
}

/*
 * Get favorite audios
 */
add_action('rest_api_init', function () {
	register_rest_route(
		'v1',
		'/favorite_audios/(?P<user_id>\d+)',
		array(
			'methods' => 'GET',
			'callback' => 'get_favorite_audios_by_user',
			'permission_callback' => 'verify_jwt_token',
		)
	);
});

/*
 * Callback function to get favorite audios by user via REST API.
 * It's argument expects user id.
 */
function get_favorite_audios_by_user($request)
{
	$parameters = $request->get_params();

	// Check if required parameters are provided
	if (empty($parameters['user_id'])) {
		return new WP_Error('invalid_parameters', 'Required parameter "user_id" is missing', array('status' => 400));
	}

	$user_id = intval($parameters['user_id']);

	$args = array(
		'post_type' => 'favorite',
		'posts_per_page' => -1, // Retrieve all posts
		'author' => $user_id,
	);

	$query = new WP_Query($args);
	$posts = $query->get_posts();

	$favorite_audios = array();

	foreach ($posts as $post) {
		$audio_data = array(
			'post_id' => $post->ID,
			'audio_name' => $post->post_title,
			'playlist_id' => get_post_meta($post->ID, 'playlist_id', true),
			'track' => get_post_meta($post->ID, 'track', true),
		);

		$favorite_audios[] = $audio_data;
	}

	// Return response
	return new WP_REST_Response($favorite_audios, 200);
}

// JWT Secret Key Field
$text_field_value = get_field('secret_key');

add_action('admin_init', 'my_general_section');
function my_general_section()
{
	add_settings_section(
		'my_settings_section', // Section ID 
		'My Options Title', // Section Title
		'my_section_options_callback', // Callback
		'general' // What Page?  This makes the section show up on the General Settings Page
	);
	add_settings_field( // Option 1
		'key_secret', // Option ID
		'JWT Key Secret', // Label
		'my_textbox_callback', // !important - This is where the args go!
		'general', // Page it will be displayed (General Settings)
		'my_settings_section', // Name of our section
		array( // The $args
			'key_secret' // Should match Option ID
		)
	);
	register_setting('general', 'key_secret', 'esc_attr');
}
function my_textbox_callback($args)
{  // Textbox Callback
	$option = get_option($args[0]);
	echo '<input type="text" id="' . $args[0] . '" name="' . $args[0] . '" value="' . $option . '" />';
}

/* 
 * Search audio or playlist
 */
add_action('rest_api_init', function () {
	register_rest_route(
		'v1',
		// '/search/(?P<query>\w+)',
		// '/search/(?P<query>[^/]+)',
		'/search/(?P<query>.+)',
		// '/search/(?P<query>.+)',
		array(
			'methods' => 'GET',
			'callback' => 'search_playlists_and_tracks',
			'permission_callback' => 'verify_jwt_token',
		)
	);
});

/*
 * Callback function to get the searched content via REST API.
 * It expects audio, playlist or text in its request to search the content.
 * Returns array of objects containing playlist and audios.
 */
function search_playlists_and_tracks($request)
{
	$parameters = $request->get_params();

	// Check if required parameters are provided
	if (empty($parameters['query'])) {
		return new WP_Error('invalid_parameters', 'Required parameter "query" is missing', array('status' => 400));
	}

	$search_query = urldecode($parameters['query']); // Decoding the URL-encoded string

	// Sanitize the search query
	$search_query = sanitize_text_field($search_query);


	// echo "Search query: " . $search_query;


	$categories = get_terms(
		array(
			'taxonomy' => 'playlist_category',
			'hide_empty' => true,
			'parent' => 0,
			'name__like' => $search_query
		)
	);
	$formatted_categories = array();

	foreach ($categories as $category) {
		$thumbnail = get_field('thumbnail', 'playlist_category_' . $category->term_id);
		$formatted_category = array(
			'id' => $category->term_id,
			'name' => $category->name,
			'slug' => $category->slug,
			'thumbnail' => $thumbnail ? $thumbnail['url'] : ''
		);

		$formatted_categories[] = $formatted_category;
	}

	$args = array(
		'post_type' => 'bb_playlist_player',
		'meta_query' => array(
			'relation' => 'OR',
			array(
				'key' => 'bb_playlist',
				'value' => $search_query,
				'compare' => 'LIKE',
			),
		),
	);

	$filtered_tracks = [];

	$query = new WP_Query($args);

	if ($query->have_posts()) {
		while ($query->have_posts()) {
			$query->the_post();
			$tracks = get_post_meta(get_the_ID(), 'bb_playlist', true);

			foreach ($tracks as $track) {

				if (stripos($track['name'], $search_query) !== false || stripos($track['author'], $search_query) !== false) {
					$filtered_tracks[] = $track;
				}
			}
		}

		wp_reset_postdata();
	}

	// Search for playlists
	$playlist_args = array(
		'post_type' => 'bb_playlist_player',
		'posts_per_page' => -1,
		's' => $search_query,
	);

	$playlist_query = new WP_Query($playlist_args);

	$playlists = [];

	if ($playlist_query->have_posts()) {
		while ($playlist_query->have_posts()) {
			$playlist_query->the_post();
			$playlists[] = ['id' => get_the_ID(), 'title' => get_the_title()];
		}
		wp_reset_postdata();
	}

	$response = array(
		'success' => true,
		'data' => [
			'categories' => $formatted_categories,
			'playlists' => $playlists,
			'tracks' => $filtered_tracks,
		]
	);

	// Return response
	return new WP_REST_Response($response, 200);
}

/*
 * Product Categories 
 */
add_action('rest_api_init', 'product_categories_endpoint');


function product_categories_endpoint()
{
	register_rest_route(
		'v1',
		'/categories',
		array(
			'methods' => 'GET',
			'callback' => 'get_product_categories',
			'permission_callback' => 'verify_jwt_token',
		)
	);
}

/*
 * Callback function to get the categories via REST API.
 * Returns array of objects containing categories.
 */
function get_product_categories()
{
	$categories = get_terms(
		array(
			'taxonomy' => 'playlist_category',
			'hide_empty' => true,
			'parent' => 0,
		)
	);

	$formatted_categories = array();

	foreach ($categories as $category) {
		$thumbnail = get_field('thumbnail', 'playlist_category_' . $category->term_id);

		$formatted_category = array(
			'id' => $category->term_id,
			'name' => $category->name,
			'slug' => $category->slug,
			'thumbnail' => $thumbnail ? $thumbnail['url'] : ''
		);

		$formatted_categories[] = $formatted_category;
	}

	return new WP_REST_Response($formatted_categories, 200);
}


/*
 * Categories associated product
 */
add_action('rest_api_init', 'categories_associated_product_endpoint');

function categories_associated_product_endpoint()
{
	register_rest_route(
		'v1',
		'/categories/(?P<id>\d+)',
		array(
			'methods' => 'GET',
			'callback' => 'categories_with_products',
			'permission_callback' => 'verify_jwt_token',
		)
	);
}

/*
 * Callback function to get the categories via REST API along with associated products.
 * Returns array of objects containing category and associated products.
 * Expects category id in the request
 */
function categories_with_products($request)
{
	$category_id = $request['id'];

	// $category = get_term($category_id, 'product_cat');

	if (is_wp_error($category)) {
		return new WP_REST_Response('Category not found', 404);
	}

	$post_type = 'bb_playlist_player';
	$page = $_GET['page'];
	$dataArray = [];
	$posts = new WP_Query([
		'post_type' => $post_type,
		'posts_per_page' => '15',
		'paged' => $page,
		'tax_query' => [
			[
				'taxonomy' => 'playlist_category',
				'field' => 'term_id',
				'terms' => $category_id,
			],
		],
	]);

	// Handle errors
	if (empty($posts)) {
		return new WP_Error('no_data', 'No playlist data found', array('status' => 404));
	}

	while ($posts->have_posts()) {
		$posts->the_post();
		$post_id = get_the_ID();
		$membership_type = get_post_meta($post_id, 'membership_type', true);
		array_push($dataArray, [
			'id' => get_the_ID(),
			'post_title' => get_the_title(),
			'thumbnail' => get_the_post_thumbnail_url(),
			'audios' => get_post_meta(get_the_ID(), 'bb_playlist', true),
			'membership_type' => $membership_type,
		]);
	}

	$response_data = array(
		'products' => $dataArray,
	);

	return new WP_REST_Response($response_data, 200);
}

/*
 * Get Subscription Status
 */

add_action('rest_api_init', 'subscription_status_endpoint');

function subscription_status_endpoint()
{
	register_rest_route(
		'v1',
		'/subscription-status',
		array(
			'methods' => 'GET',
			'callback' => 'get_subscription_status',
			'permission_callback' => 'verify_jwt_token',
		)
	);
}

function get_subscription_status($request)
{
	$user_id = $request->get_param('user_id');

	if (!$user_id) {
		return new WP_REST_Response(
			array(
				'status' => 'error',
				'message' => 'User not logged in.',
				'params' => $user_id
			),
			401
		);
	}

	$subscriptions = wcs_get_users_subscriptions($user_id);

	if (empty($subscriptions)) {
		return new WP_REST_Response(
			array(
				'status' => 'free',
				'message' => 'User has no active subscriptions.',
			),
			200
		);
	}

	$is_paid = false;
	foreach ($subscriptions as $subscription) {
		if ($subscription->has_status('active') && !$subscription->has_status('pending-cancel')) {
			$is_paid = true;
			break;
		}
	}

	return new WP_REST_Response(
		array(
			'status' => $is_paid ? 'paid' : 'free',
			'message' => $is_paid ? 'User has an active paid subscription.' : 'User has an active free subscription.',
		),
		200
	);
}

add_action('rest_api_init', 'update_profile_endpoint');

function update_profile_endpoint()
{
	register_rest_route(
		'v1',
		'/update-profile',
		array(
			'methods' => 'POST',
			'callback' => 'update_profile',
			'permission_callback' => 'verify_jwt_token',
		)
	);
}

function update_profile($request)
{

	$user_id = $request->get_param('user_id');
	$newPassword = $request->get_param('newPassword');
	$newUsername = $request->get_param('newUsername');

	// Check if the new username already exists in the database
	$user = get_user_by('login', $newUsername);
	if ($user && $user->ID != $user_id) {
		return new WP_Error('username_exists', __('Username already exists.', 'text-domain'));
	}

	// If the new username is the same as the current one, allow the update
	$current_user = get_userdata($user_id);

	if ($current_user && $current_user->display_name === $newUsername) {
		$user_data = array(
			'ID' => $user_id,
			'user_pass' => $newPassword, // Update password
		);
		wp_update_user($user_data); // Update user data
		return new WP_REST_Response(__('Profile updated successfully.', 'text-domain'), 200);
	}

	// If the new username is different, update both username and password
	$user_data = array(
		'ID' => $user_id,
		'display_name' => $newUsername, // Update username
		'user_pass' => $newPassword, // Update password
	);
	$result = wp_update_user($user_data); // Update user data

	if (is_wp_error($result)) {
		return $result; // Return error if update fails
	} else {
		return new WP_REST_Response(__('Profile updated successfully.', 'text-domain'), 200);
	}
}


add_action('rest_api_init', 'get_page_content_endpoint');

function get_page_content_endpoint()
{
	register_rest_route(
		'v1',
		'/get-page-content/privacypolicy',
		array(
			'methods' => 'GET',
			'callback' => 'get_rendered_privacy_policy_and_tc_content',
			'permission_callback' => 'verify_jwt_token',
		)
	);
}
function get_rendered_privacy_policy_and_tc_content($request)
{

	$pageName = $request->get_param('page_name');

	$content = '';

	// if ($pageName == 'privacypolicy') {
	$page_id = 15574;
	$post = get_post($page_id);

	if ($post) {
		WPBMap::addAllMappedShortcodes();
		$content = apply_filters('the_content', $post->post_content);
	}
	// }

	header('Content-Type: text/html');
	echo $content;
	exit();

	// return new WP_REST_Response($content, 200);
}

add_action('rest_api_init', 'get_trending_audio_endpoint');

function get_trending_audio_endpoint()
{
	register_rest_route(
		'v1',
		'/get_trending',
		array(
			'methods' => 'GET',
			'callback' => 'get_trending_audio',
			'permission_callback' => 'verify_jwt_token',
		)
	);
}


function get_trending_audio()
{
	$post_type = 'bb_playlist_player';
	$page = isset($_GET['page']) ? $_GET['page'] : 1; // Check if 'page' parameter is set in the URL
	$category_id = 2977;
	$args = array(
		'post_type' => $post_type,
		'posts_per_page' => 10,
		'paged' => $page,
		'tax_query' => [
			[
				'taxonomy' => 'playlist_tag',
				'field' => 'term_id',
				'terms' => $category_id,
			],
		],
	);

	$posts = new WP_Query($args);

	// // Debugging output
	// echo "Total posts found: " . $posts->found_posts;

	if (!$posts->have_posts()) {
		return new WP_Error('no_data', 'No playlist data found', array('status' => 404));
	}

	$dataArray = [];
	// while ($posts->have_posts()) {
	// 	$posts->the_post();
	// 	$post_id = get_the_ID();
	// 	array_push($dataArray, [
	// 		'id' => get_the_ID(),
	// 		'post_title' => get_the_title(),
	// 		'thumbnail' => get_the_post_thumbnail_url(),
	// 		'audios' => get_post_meta(get_the_ID(), 'bb_playlist', true),
	// 	]);
	// }
	while ($posts->have_posts()) {
		$posts->the_post();
		$post_id = get_the_ID();
		$membership_type = get_post_meta($post_id, 'membership_type', true);
		array_push($dataArray, [
			'id' => get_the_ID(),
			'post_title' => get_the_title(),
			'thumbnail' => get_the_post_thumbnail_url(),
			'audios' => get_post_meta(get_the_ID(), 'bb_playlist', true),
			'membership_type' => $membership_type,
		]);
	}
	$response_data = array(
		'trending' => $dataArray,
	);

	// Debugging output
	// echo "<pre>";
	// print_r($response_data);
	// echo "</pre>";

	return new WP_REST_Response($response_data, 200);
}

add_action('rest_api_init', 'get_user_details_endpoint');

function get_user_details_endpoint()
{
	register_rest_route(
		'v1',
		'/get_user_details',
		array(
			'methods' => 'GET',
			'callback' => 'get_user_details',
			'permission_callback' => 'verify_jwt_token',
		)
	);
}


function get_user_details()
{
	$user_id = $_GET['id'];

	// Check if user ID is provided
	if ($user_id) {
		// Get user data based on the provided ID
		$user_data = get_userdata($user_id);

		// echo "<pre>";
		// print_r($user_data);
		// echo "</pre>";
		// Check if user data exists
		if ($user_data) {
			// Prepare response data with user details
			$response_data = array(
				'ID' => $user_data->ID,
				'user_login' => $user_data->user_login,
				'user_email' => $user_data->user_email,
				'user_display_name' => $user_data->display_name,
				'user_nicename' => $user_data->user_nicename,
			);

			// Return a WP_REST_Response with the user details
			return new WP_REST_Response($response_data, 200);
		} else {
			// User not found, return a 404 response
			return new WP_Error('user_not_found', __('User not found.', 'text-domain'), array('status' => 404));
		}
	} else {
		// No user ID provided, return a 400 response
		return new WP_Error('missing_user_id', __('User ID is missing.', 'text-domain'), array('status' => 400));
	}
}


add_action('rest_api_init', 'get_receive_notifcations_endpoint');

function get_receive_notifcations_endpoint()
{
	register_rest_route(
		'v1',
		'/get_receive_notifcations',
		array(
			'methods' => 'GET',
			'callback' => 'get_receive_notifcations',
			'permission_callback' => 'verify_jwt_token',
		)
	);
}
function get_receive_notifcations($request)
{
	$user_id = $_GET['user_id'];


	// // Check if the user exists
	$user = get_user_by('id', $user_id);
	if (!$user) {
		return new WP_Error('user_not_found', 'User not found', array('status' => 404));
	}

	// Get the user meta data
	$receive_notifications = get_user_meta($user_id, 'receive_notifications', true);

	// If the meta data doesn't exist, return false
	if ($receive_notifications === '') {
		return new WP_REST_Response(
			array(
				'receive_notifications' => "no",
			),
			200
		);
	}

	return new WP_REST_Response(
		array(
			'receive_notifications' => $receive_notifications,
		),
		200
	);
}




add_action('rest_api_init', 'set_receive_notifcations_endpoint');

function set_receive_notifcations_endpoint()
{
	register_rest_route(
		'v1',
		'/set_receive_notifcations',
		array(
			'methods' => 'POST',
			'callback' => 'set_receive_notifications',
			'permission_callback' => 'verify_jwt_token',
		)
	);
}
function set_receive_notifications($request)
{
	// Get JSON parameters
	$parameters = $request->get_json_params();

	// Extract user_id and receive_notifications from the parameters
	$user_id = isset($parameters['id']) ? intval($parameters['id']) : 0;
	$receive_notifications = isset($parameters['receive_notifications']) ? $parameters['receive_notifications'] : null;


	// Validate user ID
	if ($user_id <= 0 || !get_userdata($user_id)) {
		return new WP_Error('invalid_user', 'Invalid user ID', array('status' => 400));
	}



	// Update or create the user meta data
	update_user_meta($user_id, 'receive_notifications', $receive_notifications);


	// $receive_notifications = get_user_meta($user_id, 'receive_notifications', true);
	// print_r($receive_notifications);

	return new WP_REST_Response(
		array(
			'success' => true,
			'message' => 'User receive notifications updated successfully.'
		),
		200
	);
}


// Add submenu item under 'Audio Playlist'
add_action('admin_menu', 'add_bb_playlist_import_submenu');

function add_bb_playlist_import_submenu()
{
	add_submenu_page(
		'edit.php?post_type=bb_playlist_player',
		'Import Playlist',
		'Import Playlist',
		'manage_options',
		'bb_playlist_player_import',
		'bb_playlist_player_import_page'
	);
}

function bb_playlist_player_import_page()
{
	?>
	<div class="wrap">
		<h1>Import Playlist</h1>
		<form method="post" enctype="multipart/form-data"
			action="<?php echo admin_url('admin-post.php?action=bb_playlist_player_import'); ?>">
			<input type="file" name="csv_file" accept=".csv">
			<?php wp_nonce_field('bb_playlist_player_import', 'bb_playlist_player_import_nonce'); ?>
			<input type="submit" class="button button-primary" value="Import CSV">
		</form>
	</div>
	<?php
}        


add_action('init', function () {
	if (isset($_GET['new-file'])) {
		require_once (__DIR__ . '/import-split.php');
		splitProducts();
	}
});




// Add rewrite rule on 'init'
add_action('init', function () {
	add_rewrite_rule('my-page/amotdata/?', 'index.php?import_action', 'top');
	flush_rewrite_rules();
});

add_filter('query_vars', function ($query_vars) {
	$query_vars[] = 'import_action';
	return $query_vars;
});

add_action('template_redirect', function () {
	$import_action = get_query_var('import_action');

	if ($import_action === 'import_amotdata') {
		require_once (__DIR__ . '/import-functions.php');

		if (function_exists('importFromWoocommerceProduct')) {
			importFromWoocommerceProduct();
			exit;
		}
	} elseif ($import_action === 'create_posts_by_category') {
		require_once (__DIR__ . '/import-functions.php');

		if (function_exists('createPostsByCategoryName')) {
			createPostsByCategoryName();
			exit;
		}
	}
});
