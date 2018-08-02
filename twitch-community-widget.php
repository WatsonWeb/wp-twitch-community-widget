<?php
/*
Plugin Name: Twitch Community Widget
Description: Adds a widget to display a list of live Twitch streams from a certain community.
Version: 1.0
Author: Bryan Watson
Author URI: https://bryanwatson.ca
*/

// The widget class
class Twitch_Community_Widget extends WP_Widget {

	// Main constructor
	public function __construct() {
		parent::__construct(
			'twitch_community_widget',
			__( 'Twitch Community Widget', 'text_domain' ),
			array(
				'customize_selective_refresh' => true,
				'description' => 'Display a list of live Twitch streams from a certain community.'
			)
		);
	}

	// The widget form (for the backend )
	public function form( $instance ) {

		// Set widget defaults
		$defaults = array(
			'tcw_title'    	=> '',
			'tcw_client_id' => '',
			'tcw_community_name' => '',
			'tcw_follow_button_channel' => '',
			'tcw_follow_button_label' => ''
		);
		
		// Parse current settings with defaults
		extract( wp_parse_args( ( array ) $instance, $defaults ) ); ?>

		<?php // Widget Title ?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'tcw_title' ) ); ?>"><?php _e( 'Title:', 'text_domain' ); ?></label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'tcw_title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'tcw_title' ) ); ?>" type="text" value="<?php echo esc_attr( $tcw_title ); ?>" />
		</p>

		<?php // Client ID ?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'tcw_client_id' ) ); ?>"><?php _e( 'Twitch App - Client ID:', 'text_domain' ); ?></label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'tcw_client_id' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'tcw_client_id' ) ); ?>" type="text" value="<?php echo esc_attr( $tcw_client_id ); ?>" />
		</p>

		<?php // Community Name ?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'tcw_community_name' ) ); ?>"><?php _e( 'Twitch Community Name:', 'text_domain' ); ?></label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'tcw_community_name' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'tcw_community_name' ) ); ?>" type="text" value="<?php echo esc_attr( $tcw_community_name ); ?>" />
		</p>

		<?php // Follow Button - Channel ?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'tcw_follow_button_channel' ) ); ?>"><?php _e( 'Follow Button - Twitch Channel:', 'text_domain' ); ?></label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'tcw_follow_button_channel' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'tcw_follow_button_channel' ) ); ?>" type="text" value="<?php echo esc_attr( $tcw_follow_button_channel ); ?>" />
		</p>

		<?php // Follow Button - Label ?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'tcw_follow_button_label' ) ); ?>"><?php _e( 'Follow Button - Label:', 'text_domain' ); ?></label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'tcw_follow_button_label' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'tcw_follow_button_label' ) ); ?>" type="text" value="<?php echo esc_attr( $tcw_follow_button_label ); ?>" />
		</p>
		<?php
	}

	// Update widget settings
	public function update( $new_instance, $old_instance ) {
		$instance = $old_instance;
		$instance['tcw_title']			= isset( $new_instance['tcw_title'] )			? wp_strip_all_tags( $new_instance['tcw_title'] ) : '';
		$instance['tcw_client_id']		= isset( $new_instance['tcw_client_id'] )		? wp_strip_all_tags( $new_instance['tcw_client_id'] ) : '';
		$instance['tcw_community_name']	= isset( $new_instance['tcw_community_name'] )	? wp_strip_all_tags( $new_instance['tcw_community_name'] ) : '';
		$instance['tcw_follow_button_channel']	= isset( $new_instance['tcw_follow_button_channel'] )	? wp_strip_all_tags( $new_instance['tcw_follow_button_channel'] ) : '';
		$instance['tcw_follow_button_label']	= isset( $new_instance['tcw_follow_button_label'] )	? wp_strip_all_tags( $new_instance['tcw_follow_button_label'] ) : '';
		delete_transient('community_id');
		delete_transient('streams');
		return $instance;
	}

	// Display the widget
	public function widget( $args, $instance ) {

		extract( $args );

		// Check the widget options
		$tcw_title			= isset( $instance['tcw_title'] ) ? apply_filters( 'widget_title', $instance['tcw_title'] ) : '';
		$tcw_client_id		= isset( $instance['tcw_client_id'] ) ? $instance['tcw_client_id'] : '';
		$tcw_community_name	= isset( $instance['tcw_community_name'] ) ? $instance['tcw_community_name'] : '';
		$tcw_follow_button_channel	= isset( $instance['tcw_follow_button_channel'] ) ? $instance['tcw_follow_button_channel'] : '';
		$tcw_follow_button_label	= isset( $instance['tcw_follow_button_label'] ) ? $instance['tcw_follow_button_label'] : '';

		// Enqueue Widget CSS
		wp_enqueue_style( 'twitch-community-widget', plugin_dir_url(__FILE__).'/assets/twitch-community-widget.css' );
		
		// Get community id
		function get_community_id($community_name, $client_id) {

			// Check for cache
			$cached_id = get_transient('community_id');

			if (!empty($cached_id)) {
				return $cached_id;
			}

			if (empty($community_name)) {
				echo "Error: No Community Name supplied to get_community_id.";
				return false;
			}

			if (empty($client_id)) {
				echo "Error: No Client ID supplied to get_community_id.";
				return false;
			}

			$url = 'https://api.twitch.tv/kraken/communities?name=' . strtolower($community_name);
			$request_args = array(
				'headers' => array(
					'Client-ID' => $client_id,
					'Accept' => 'application/vnd.twitchtv.v5+json'
				)
			);
			$response = wp_remote_get( esc_url_raw( $url ), $request_args ); 
			$response_array = json_decode( wp_remote_retrieve_body( $response ), true );

			if (empty($response_array['_id'])){
				echo "Error: Not able to retrieve Community ID in get_community_id.";
				return false;
			}

			// Cache result
			set_transient( 'community_id', $response_array['_id'], MONTH_IN_SECONDS );

			// Returns string of community id
			return $response_array['_id'];
		}

		// Get community streams
		function get_community_streams($community_name, $client_id) {

			// Check for cache
			$cached_streams = get_transient('streams');

			if (!empty($cached_streams)) {
				return $cached_streams;
			}

			if (empty($community_name)) {
				echo "Error: No Community Name supplied to get_community_streams.";
				return false;
			}

			if (empty($client_id)) {
				echo "Error: No Client ID supplied to get_community_streams.";
				return false;
			}

			$community_id = get_community_id($community_name, $client_id);

			if (empty($community_id)) {
				echo "Error: No Community ID supplied to get_community_streams.";
				return false;
			}

			$url = 'https://api.twitch.tv/helix/streams?community_id=' . $community_id;
			$request_args = array(
				'headers' => array(
					'Client-ID' => $client_id
				)
			);
			$response = wp_remote_get( esc_url_raw( $url ), $request_args ); 
			$response_array = json_decode( wp_remote_retrieve_body( $response ), true );
			$streams = $response_array['data'];

			if (empty($streams)){
				//echo "Error: Not able to retrieve Community Streams in get_community_streams.";
				return false;
			}

			$streams = add_usernames($streams, $client_id);
			$streams = add_games($streams, $client_id);

			// Cache result
			set_transient( 'streams', $streams, MINUTE_IN_SECONDS*3 );

			// Returns array of streams
			return $streams;
		}

		// Adds usernames to streams array based on user_id
		function add_usernames($streams, $client_id) {
			if (empty($streams)) {
				echo "Error: No Streams supplied to add_usernames.";
				return false;
			}

			if (empty($client_id)) {
				echo "Error: No Client ID supplied to add_usernames.";
				return false;
			}

			$user_ids = [];

			foreach ($streams as $stream) {
				array_push($user_ids, $stream['user_id']);
			}

			$url = 'https://api.twitch.tv/helix/users?id=' . implode('&id=', $user_ids);
			$request_args = array(
				'headers' => array(
					'Client-ID' => $client_id
				)
			);
			$response = wp_remote_get( esc_url_raw( $url ), $request_args ); 
			$response_array = json_decode( wp_remote_retrieve_body( $response ), true );

			for ($i=0; $i < count($response_array['data']); $i++) {
				$streams[$i]['display_name'] = $response_array['data'][$i]['display_name'];
				$streams[$i]['profile_image_url'] = $response_array['data'][$i]['profile_image_url'];
			}

			// Returns modified array of streams
			return $streams;
		}

		// Adds games to streams array based on game_id
		function add_games($streams, $client_id) {
			if (empty($streams)) {
				echo "Error: No Streams supplied to add_usernames.";
				return false;
			}

			if (empty($client_id)) {
				echo "Error: No Client ID supplied to add_usernames.";
				return false;
			}

			$game_ids = [];

			foreach ($streams as $stream) {
				array_push($game_ids, $stream['game_id']);
			}

			$url = 'https://api.twitch.tv/helix/games?id=' . implode('&id=', $game_ids);
			$request_args = array(
				'headers' => array(
					'Client-ID' => $client_id
				)
			);
			$response = wp_remote_get( esc_url_raw( $url ), $request_args ); 
			$response_array = json_decode( wp_remote_retrieve_body( $response ), true );

			for ($i=0; $i < count($game_ids); $i++) {

				$game_name = '';
				$game_art = '';

				for ($b=0; $b < count($response_array['data']); $b++) {
					if($game_ids[$i] == $response_array['data'][$b]['id']) {
						$game_name = $response_array['data'][$b]['name'];
						$game_art = $response_array['data'][$b]['box_art_url'];
					}
				}

				$streams[$i]['game_name'] = $game_name;
				$streams[$i]['game_art'] = $game_art;
			}

			// Returns modified array of streams
			return $streams;
		}

		// Display streams
		function display_community_streams($community_name, $client_id, $follow_channel, $follow_label, $streams_title) {

			if (empty($community_name)) {
				echo "Error: No Community Name supplied to display_community_streams.";
				return false;
			}

			if (empty($client_id)) {
				echo "Error: No Client ID supplied to display_community_streams.";
				return false;
			}

			if (empty($follow_channel)) {
				echo "Error: No Follow Channel supplied to display_community_streams.";
				return false;
			}

			if (empty($follow_label)) {
				echo "Error: No Follow Label supplied to display_community_streams.";
				return false;
			}

			$streams = get_community_streams($community_name, $client_id);

			if (empty($streams)) {
				display_follow_button($follow_channel, $follow_label);
				return false;
			}

			$random = rand(0,count($streams)-1);
			echo '<h4 class="widget-title">Featured Stream</h4>';
			display_stream($streams[$random], true, true);
			display_follow_button($follow_channel, $follow_label);
			array_splice($streams, $random, 1);

			for ($i=0; $i < count($streams); $i++) {
				if ($i === 0) {
					echo '<h4 class="widget-title">'.$streams_title.'</h4>';
					display_stream($streams[$i]);
				} else {
					display_stream($streams[$i]);
				}
			}
		}

		// Display stream 
		function display_stream($stream, $large = false, $show_thumbnail = false){

			$user = $stream['display_name'];
			$link = 'https://twitch.tv/' . $user;
			$title = $stream['title'];
			$title_short = strlen($title) > 35 ? substr($title, 0, 35) . '...': $title;
			$viewers = $stream['viewer_count'];
			$thumbnail = rtrim($stream['thumbnail_url'], '{width}x{height}.jpg') . '100x56.jpg';
			$thumbnail_large = rtrim($stream['thumbnail_url'], '{width}x{height}.jpg') . '300x169.jpg';
			$avatar = $stream['profile_image_url'];
			$game_name = $stream['game_name'];
			$game_art = $stream['game_art'];	
			?> 

			<div class="stream <?php if ($large){echo ' stream-large';} else { echo 'stream-regular'; }?>">
				<a href="<?php echo $link ?>" target="_blank" title="<?php if ($large){echo 'Watch '.$user.' live on Twitch!';}?><?php if (!$large){echo $title;}?>">		
					<?php if ($show_thumbnail) {?>
						<div class="thumbnail">						
							<img src="<?php if ($large) {echo $thumbnail_large;} else {echo $thumbnail;} ?>" alt="<?php echo $title ?>">
							<span class="live"><span class="circle"></span>Live!</span>
							<span class="viewers">
								<svg width="16px" height="16px" version="1.1" viewBox="0 0 16 16" x="0px" y="0px"><path clip-rule="evenodd" d="M11,14H5H2v-1l3-3h2L5,8V2h6v6l-2,2h2l3,3v1H11z" fill-rule="evenodd"></path></svg>
								<?php echo $viewers ?>
							</span>	
						</div>
					<?php } ?>

					<?php if (!$show_thumbnail) {?>
						<div class="avatar">
							<img src="<?php echo $avatar ?>" alt="<?php echo $user ?> on Twitch.tv" height="50" width="50">
						</div>
					<?php } ?>
					
					<?php if ($large) {?>
						<div class="description">
							<h2 class="user"><strong><?php echo $user ?></strong> playing <?php echo $game_name ?></h2>
							<h3 class="title"><em><?php echo $title ?></em></h3>
						</div>
					<?php } ?>

					<?php if (!$large) {?>
						<div class="description">
							<h2 class="user"><strong><?php echo $user ?></strong></h2>
							<h3 class="title">playing <?php echo $game_name ?></h3>
						</div>
					<?php } ?>

					<?php if (!$show_thumbnail) {?>
						<div class="viewers">
							<span>
								<svg width="16px" height="16px" version="1.1" viewBox="0 0 16 16" x="0px" y="0px"><path clip-rule="evenodd" d="M11,14H5H2v-1l3-3h2L5,8V2h6v6l-2,2h2l3,3v1H11z" fill-rule="evenodd"></path></svg>
								<?php echo $viewers ?>
							</span>
						</div>
					<?php } ?>
				</a>
			</div>

			<?php
		}

		function display_follow_button($channel, $label) { ?>		
			<a href="https://twitch.tv/<?php echo $channel ?>" target="_blank" class="btn twitch-follow" title="<?php echo $label ?>">
				<svg viewBox="0 0 128 134" width="24px" height="24px">
					<path d="m9 0-9 23v94h32v17h18l17-17h26l35-35v-82zm107 76-20 20h-32l-17 17v-17h-27v-84h96zm-20-41v35h-12v-35zm-32 0v35h-12v-35z" clip-rule="evenodd" rill-rule="evenodd"></path>
				</svg>
				<strong><?php echo $label ?></strong>
			</a>
		<?php }

		// WordPress core before_widget hook (always include)
		echo $before_widget;

		// Display the widget
		display_community_streams($tcw_community_name, $tcw_client_id, $tcw_follow_button_channel, $tcw_follow_button_label, $tcw_title);

		// WordPress core after_widget hook (always include)
		echo $after_widget;

	}

}

// Register the widget
function tcw_register_widget() {
	register_widget( 'Twitch_Community_Widget' );
}
add_action( 'widgets_init', 'tcw_register_widget' );