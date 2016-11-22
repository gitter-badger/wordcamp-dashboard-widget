<?php
/**
 *
 * @link              http://www.lubus.in
 * @since             0.1
 * @package           WordCamp_Dashboard_Widget
 *
 * @wordpress-plugin
 * Plugin Name:       WordCamp Dashboard Widget
 * Plugin URI:        https://github.com/lubusIN/wordcamp-dashboard-widget
 * Description:       Wordpress plugin to show upcoming WordCamp on wp-admin dashboard.
 * Version:           0.5
 * Author:            LUBUS, Ajit Bohra
 * Author URI:        http://www.lubus.in
 * License:           GPL-3.0+
 * License URI:       http://www.gnu.org/licenses/gpl-3.0.txt
 * Text Domain:       wordcamp-dashboard-widget
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Plugin Activation: The code that runs during plugin activation.
 */
function lubus_wdw_activate() {
	lubus_wdw_get_wordcamp_data(); // Get data on activation 
}
register_activation_hook( __FILE__, 'lubus_wdw_activate' );

/**
 * Plugin Deactivation: The code that runs during plugin deactivation.
 */
function lubus_wdw_deactivate() {
	delete_transient("lubus_wdw_wordcamp_JSON"); // Remove transient data for plugin
}
register_deactivation_hook( __FILE__, 'lubus_wdw_deactivate' );

/**
 * Enqueue styles
 */
function lubus_wdw_add_styles( $hook ) {
	if ( 'index.php' != $hook ) { return; } // Only if its main dashboard page

	wp_register_style( "css-datatables", plugin_dir_url( __FILE__ ) . 'assets/css/jquery.dataTables.min.css', array(), "1.0", 'all' );
	wp_register_style( "css-style", plugin_dir_url( __FILE__ ) . 'assets/css/style.css', array(), "1.0", 'all' );
	
	wp_enqueue_style(  "css-datatables" );
	wp_enqueue_style( "css-style" );
}
add_action( 'admin_enqueue_scripts', 'lubus_wdw_add_styles' );

/**
 * Enqueue scripts
 */
function lubus_wdw_add_scripts( $hook ) {
	if ( 'index.php' != $hook ) { return; }  // Only if its main dashboard page

	wp_register_script( "js-datatables", plugin_dir_url( __FILE__ ) . 'assets/js/jquery.dataTables.min.js', array( 'jquery' ), "1.0", false );
	wp_register_script( "js-script", plugin_dir_url( __FILE__ ) . 'assets/js/script.js', array( 'jquery','js-datatables' ), "1.0", false );

	wp_enqueue_script( "js-datatables" );
	wp_enqueue_script( "js-script" );
}
add_action( 'admin_enqueue_scripts', 'lubus_wdw_add_scripts' );

/**
 * Add a widget to the dashboard.
 *
 * This function is hooked into the 'wp_dashboard_setup' action below.
 */
function lubus_wdw_add_widget() {
	wp_add_dashboard_widget(
                 'lubus_wdw_wordcamp_widget',  // Widget slug.
                 'Upcoming WordCamps',      // Title.
                 'lubus_wdw_display_wordcamps' // Display function.
        );	
}
add_action( 'wp_dashboard_setup', 'lubus_wdw_add_widget' );

/**
 * Create the function to output the contents of our Dashboard Widget.
 */

function lubus_wdw_display_wordcamps() {
	$upcoming_wordcamps = lubus_wdw_get_wordcamp_data(); // Get data

	// Generate tables if its data and not a WP_ERROR
	if($upcoming_wordcamps && !is_wp_error($upcoming_wordcamps)){
?>
	    <table id="lubus-wordcamp" class="display">
		<thead>
			<tr>
				<th scope="col" class="column-primary">Location</th>
				<th id="date" scope="col">Date</th>
				<th scope="col">Twitter</th>
			</tr>
		</thead>
		<tbody>
		    <?php
			foreach($upcoming_wordcamps as $key => $value)
			{
				$timestamp = lubus_wdw_get_meta($value['post_meta'],"Start Date (YYYY-mm-dd)");
				?>
				<tr>
					<td class="column-primary" data-colname="Location">
						<a href="<?php echo lubus_wdw_get_meta($value['post_meta'],"URL") ?>" target="_new" title="WordCamp Homepage">
							<?php echo lubus_wdw_get_meta($value['post_meta'],"Location"); ?>
						</a>
					</td>

					<td data-colname="Date" data-order="<?php echo date("Y-m-d", $timestamp); ?>">
						<?php echo date("d-m-Y", $timestamp); ?>
					</td>

					<td data-colname="Twitter">
						<a href="<?php echo lubus_wdw_get_twitter_url($value['post_meta'],"Twitter"); ?>" target="_new" title="Twitter profile">
							<span class="dashicons dashicons-twitter"></span> 
							
						</a>

						<span class="wdw_sep">|</span> 

						<a href="<?php echo lubus_wdw_get_twitter_hastag($value['post_meta'],"WordCamp Hashtag"); ?>" target="_new" title="Twitter Hashtag">
							<span class="wdw_hashtag">#</span>
						</a>

					</td>
				</tr>
				<?php
			}
			?>
			</tbody>
		</table>
	<?php
	} else {
		// Show error is unable to display or fetch data
	?>
		<div class="wp-ui-notification" id="lubus_wdw_error">
			<p><span class="dashicons dashicons-dismiss"></span> Unable to connect to wordcamp API try reloading the page</p>
		</div>
		<p class=".wp-ui-text-primary">If error persist <a href="https://github.com/lubusIN/wordcamp-dashboard-widget/issues/new" target="_new">click here</a> to create issue on github with the following error message</p>
		<p>
			<?php  
				// Developer friend 'error message' for troubleshooting
				if ( is_wp_error( $upcoming_wordcamps ) ) {
				   $error_string = $upcoming_wordcamps->get_error_message();
				   echo '<p class="wp-ui-text-notification">' . $error_string . '</p>';
				}
			?>
		</p>
	<?php
	}
}

/**
 * Get Wordcamp Data
 */
function lubus_wdw_get_wordcamp_data(){
	  //delete_transient( 'lubus_wdw_wordcamp_JSON' );
	  $transient = get_transient( 'lubus_wdw_wordcamp_JSON' ); // Get data from wordpress transient/cache
	  if( ! empty( $transient ) ) {
		    return json_decode($transient,true);
	  } else {
	  		$api_url = 'https://central.wordcamp.org/wp-json/posts?type=wordcamp&filter[order]=DESC&filter[posts_per_page]=300';
			$api_response = wp_remote_get( $api_url, array('sslverify' => false,'timeout' => 10));  // Call the API.

	  		if (lubus_wdw_check_response($api_response)) { 		
		  		// Get json data & filterit:
			    $parsed_json = json_decode($api_response['body'], true);
			    $upcoming_wordcamps = array();

			    // Create New JSON from filtered data
				foreach($parsed_json as $key => $value)
				{
					$wordcamp_date = lubus_wdw_get_meta($value['post_meta'],"Start Date (YYYY-mm-dd)");
					$today = date("Y-m-d");

					// Create new JSON
					// Check if data is not empty, N/A or less then 
					if ( $wordcamp_date !="" && $wordcamp_date !="N/A" && date("Y-m-d",$wordcamp_date) >= $today) {
						$upcoming_wordcamps[] = $value;
						$upcoming_wordcamps = $upcoming_wordcamps;
					}
				}

				 // Store data to wordpress transient/cache
				set_transient( 'lubus_wdw_wordcamp_JSON',json_encode($upcoming_wordcamps), DAY_IN_SECONDS );
				return $upcoming_wordcamps;
			}

			if ( is_wp_error( $api_response ) ) {
				return  $api_response;
			}
			return false;
	  }
}

/**
 * Get meta from array
 */
function lubus_wdw_get_meta($meta_array,$meta_key){
	$meta_value = 'N/A';
	foreach($meta_array as $m_key => $m_value) {
			if ($m_value["key"] == $meta_key) {
				 $meta_value = $m_value["value"];
			}
       }
     return $meta_value;
}

/**
 * Get twitter profile url
 */
function lubus_wdw_get_twitter_url($meta_array,$meta_key){
	$twitter_data = lubus_wdw_get_meta($meta_array,$meta_key); // Get inconsistent twitter value (url/name/@name)

	$regx_twitter_url = '/^(https?:\/\/)?([\da-z\.-]+)\.([a-z\.]{2,6})([\/\w \.-]*)*\/?$/';
	$regx_twitter_mention = '/@([A-Za-z0-9_]{1,15})/';

	$twitter_url = "https://www.twitter.com/";

	if (preg_match($regx_twitter_url,$twitter_data)) {
		$twitter_url = $twitter_data; // If proper URL leave it
	}
	else{
		$twitter_url .= $twitter_data; // Append based URL
	}

	return $twitter_url;
}

/**
 * Get twitter hashtag url
 */
function lubus_wdw_get_twitter_hastag($meta_array,$meta_key){
	$twitter_data = lubus_wdw_get_meta($meta_array,$meta_key); // Get inconsistent twitter value (url/name/@name)

	$regx_twitter_hashtag = '/\S*#(?:\[[^\]]+\]|\S+)/';

	$twitter_url = "https://www.twitter.com/";

	if (preg_match($regx_twitter_hashtag,$twitter_data)) {
		$twitter_url .= $twitter_data; // If proper URL leave it
	}
	else{
		$twitter_url .= '#'. $twitter_data; // Append based URL
	}

	return $twitter_url;
}

/**
 * Given an HTTP response, check it to see if it is worth storing.
 */
function lubus_wdw_check_response( $response ) {
  if( ! is_array( $response ) ) { return FALSE; } // Is the response an array?
  if( is_wp_error( $response ) ) { return FALSE; } // Is the response a wp error?
  if( ! isset( $response['response'] ) ) { return FALSE; } // Is the response weird?
  if( ! isset( $response['response']['code'] ) ) { return FALSE; }   // Is there a status code?
  if( in_array( $response['response']['code'], lubus_wdw_bad_status_codes() ) ) { return FALSE; }   // Is the status code bad?
  
  return $response['response']['code'];  // We made it!  Return the status code, just for posterity's sake.
}

/**
 * A list of HTTP statuses that suggest that we have data that is not worth storing.
 */
function lubus_wdw_bad_status_codes() {
  return array( 404, 500 );
}
?>