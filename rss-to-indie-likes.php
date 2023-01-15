<?php
/**
 * Plugin Name:       RSS to Indie Likes
 * Plugin URI:        https://github.com/cagrimmett/rss-to-indie-likes
 * Description:       Takes posts from an RSS feed and turns them into Indie Likes on your site.
 * Version:           0.0.1
 * Author:            cagrimmett
 * Author URI:        https://cagrimmett.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 */

require_once ABSPATH . 'wp-admin/includes/plugin.php';

function rss2il_activate() {
	global $wpdb;
	$charset_collate = $wpdb->get_charset_collate();
	$table_name      = $wpdb->prefix . 'rss2il';
	$sql             = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        rss_guid VARCHAR(10000) NOT NULL,
        indie_like_id int(20) NOT NULL,
        UNIQUE KEY id (id)
    ) $charset_collate;";
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql );

	// Register plugin options
	register_setting(
		'rss2il_settings',
		'rss2il_feed'
	);
	register_setting(
		'rss2il_settings',
		'rss2il_author'
	);
	// schedule cron hook
	if ( ! wp_next_scheduled( 'rss2il_hook' ) ) {
		wp_schedule_event( time(), 'hourly', 'rss2il_hook' );
	}
}
register_activation_hook( __FILE__, 'rss2il_activate' );

function rss2il_deactivate() {
	wp_clear_scheduled_hook( 'rss2il_hook' );
}
register_deactivation_hook( __FILE__, 'rss2il_deactivate' );

function rss2il_uninstall() {
	global $wpdb;
	$table_name = $wpdb->prefix . 'rss2il';
	$sql        = "DROP TABLE IF EXISTS $table_name;";
	$wpdb->query( $sql );

	delete_option( 'rss2il_feed' );
	delete_option( 'rss2il_author' );
}
 register_uninstall_hook( __FILE__, 'rss2il_uninstall' );


function rss2il_convert_to_likes() {

	if ( is_plugin_active( 'indieblocks/indieblocks.php' ) ) {
		$feed   = get_option( 'rss2il_feed' );
		$author = get_option( 'rss2il_author' );

		// Check if the plugin options have been set

		if ( ! $feed || ! $author ) {
			printf( 'No feed or  author. Please go to the <a href="%s">plugin settings page</a> and set them.', admin_url( 'options-general.php?page=rss-to-indie-likes' ) );
			return;
		} else {
			// Fetch the existing starred posts from the database
			global $wpdb;
			$table_name     = $wpdb->prefix . 'rss2il';
			$existing_posts = $wpdb->get_results( "SELECT rss_guid FROM $table_name" );
			$existing_posts = array_map(
				function( $existing_post ) {
					return $existing_post->rss_guid;
				},
				$existing_posts
			);

			// Get the feed, but change default cache time to 30 mins
			add_filter( 'wp_feed_cache_transient_lifetime', 'return_1800' );
			function return_1800( $seconds ) {
				// change the default feed cache recreation period to 30 mins
				return 1800;
			}

			$rss = fetch_feed( $feed );

			remove_filter( 'wp_feed_cache_transient_lifetime', 'return_1800' );

			// If the feed doesn't work, return
			if ( is_wp_error( $rss ) ) {
				return;
			}

			// Get the items from the feed
			$rss_items = $rss->get_items();

			// Filter out starred posts that have already been processed and stored in the DB

			if ( ! empty( $existing_posts ) && ! is_wp_error( $rss_items ) ) {
				$rss_items = array_filter(
					$rss_items,
					function( $rss_item ) use ( $existing_posts ) {
						return ! in_array( $rss_item->get_permalink(), $existing_posts );
					}
				);
			}
			// Get the local timestamp
			function get_local_timestamp( $item ) {
				$local_timestamp = get_date_from_gmt( $item->get_date(), 'Y-m-d H:i:s' );
				return $local_timestamp;
			}

			foreach ( $rss_items as $item ) {

				if ( ! empty( $item->get_description() ) ) {
					$content = '<!-- wp:indieblocks/context -->
                    <div class="wp-block-indieblocks-context"><i>Likes <a class="u-like-of" href="' . $item->get_permalink() . '">' . $item->get_permalink() . '</a>.</i></div>
                    <!-- /wp:indieblocks/context -->
                    
                    <!-- wp:paragraph -->
                    <p>' . $item->get_description() . '</p>
                    <!-- /wp:paragraph -->';
				} else {
					$content = '<!-- wp:indieblocks/context -->
                    <div class="wp-block-indieblocks-context"><i>Likes <a class="u-like-of" href="' . $item->get_permalink() . '">' . $item->get_permalink() . '</a>.</i></div>
                    <!-- /wp:indieblocks/context -->';
				}

				$indie_like_id = wp_insert_post(
					array(
						'post_type'     => 'indieblocks_like',
						'post_title'    => 'Likes ' . $item->get_permalink(),
						'post_status'   => 'publish',
						'to_ping'       => $item->get_permalink(),
						'post_author'   => $author,
						'post_content'  => $content,
						'post_date'     => get_local_timestamp( $item ),
						'post_gmt_date' => $item->get_date( 'Y-m-d H:i:s' ),
					)
				);

				// Save the RSS item guid and Indie Like ID to the database.
				global $wpdb;
				$table_name = $wpdb->prefix . 'rss2il';
				$wpdb->insert(
					$table_name,
					array(
						'rss_guid'      => $item->get_permalink(),
						'indie_like_id' => $indie_like_id,
					)
				);

			}
		}
	} else {
		return;
	}
}

// hook that function onto our scheduled event
add_action( 'rss2il_hook', 'rss2il_convert_to_likes' );

function rss2il_options_page() {
	add_submenu_page(
		'tools.php', // Parent page slug
		'RSS to Indie Likes Settings', // Page title
		'RSS to Indie Likes', // Menu title
		'manage_options', // Capability required to access the page
		'rss2il-settings', // Menu slug
		'rss2il_settings_page' // Callback function to render the page
	);
}
add_action( 'admin_menu', 'rss2il_options_page' );

// Callback function to render the plugin settings page
function rss2il_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'You do not have sufficient permissions to access this page.' );
	}

			// Check if form has been submitted
	if ( isset( $_POST['rss2il_settings_form_submitted'] ) ) {
		// Validate and sanitize form input
		$feed   = sanitize_text_field( $_POST['rss2il_feed'] );
		$author = intval( $_POST['rss2il_author'] );

		// Save form input to plugin options
		update_option( 'rss2il_feed', $feed );
		update_option( 'rss2il_author', $author );

		// Display success message
		echo '<div class="notice notice-success is-dismissible">';
		echo '<p>Settings saved successfully.</p>';
		echo '</div>';
	}

			// Render form
	?>

		<div class="wrap">
			<h1>RSS to Indie Likes Settings</h1>

			<?php
			if ( ! is_plugin_active( 'indieblocks/indieblocks.php' ) ) {
				echo '<div class="notice notice-error">
                <p>This plugin requires <a href="https://wordpress.org/plugins/indieblocks/">IndieBlocks</a> to be installed and activated.</p>
                </div>';
			}
			?>
			<form method="post" action="">
				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="rss2il_username">RSS Feed</label>
						</th>
						<td>
							<input type="text" name="rss2il_feed" id="rss2il_feed" value="<?php echo esc_attr( get_option( 'rss2il_feed' ) ); ?>" class="regular-text" />
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="rss2il_author">Author to attribute the likes to</label>
						</th>
						<td>
							<?php
							// Get a list of all users
							$users = get_users();
							?>
							<select name="rss2il_author" id="rss2il_author">
								<?php
								foreach ( $users as $user ) {
									$selected = selected( $user->ID, get_option( 'rss2il_author' ), false );
									echo "<option value='$user->ID' $selected>$user->display_name</option>";
								}
								?>
							</select>
						</td>
					</tr>
				</table>
				<input type="hidden" name="rss2il_settings_form_submitted" value="1" />
				<?php submit_button(); ?>
			</form>
		</div>
	<?php
}

function rss2il_settings_link( $links ) {
	$settings_link = '<a href="' . admin_url( 'tools.php?page=rss2il-settings' ) . '">Settings</a>';
	array_unshift( $links, $settings_link );
	return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'rss2il_settings_link' );
