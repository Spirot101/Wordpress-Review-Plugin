<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
/* 
	Ska jag ha en index fil med comments och DEFINED eller allt i samma fil och endast en kommentar i index.php
	Var ska ABSPATH vara?
	vf är det bättre med att ha klasser?
*/
class ReviewPlugin {
	
	//public $result;
	
	// Constructor to call actions and filters 
	public function __construct() {
		
		// Add assets (css, js)
		// add_action('wp_enqueue_scripts', array($this, 'load_assets'));
		
		// Add menu in admin panel
		add_action('init', array($this, 'review_post_type'));
		
		// Add form shortcode
		add_shortcode('review_shortcode_form', array($this, 'reviewFormShortcodeFunct'));
		
		// Add form data to database
		add_action('init', array($this, 'form_capture'));
		
		// Display form data on page
		add_shortcode('review_list', array($this, 'shortcode_review_post_type_list'));
		
		// Add modifier, priority = 10, accepted_args = 2
		add_filter( 'post_row_actions', array($this, 'modify_list_row_actions'), 10, 2);
		
		// Rest API
		add_action('rest_api_init', array($this, 'register_API'));
	}
	
	public function results_API() {
		$reviews = new WP_Query(array(
			'numberposts' => 10,
			'post_type' => 'eb_review'
		));
		
		$reviewresults = array();
		
		while($reviews->have_posts()) {
			$reviews->the_post();
			array_push($reviewresults, array(
				'id' => get_the_ID(),
				'title' => get_the_title(),
				'content' => get_the_content()
			));
		}
		
		return $reviewresults;
	}
	
	public function register_API() {
		register_rest_route('eb/v1', 'reviews', [
			'methods' => WP_REST_SERVER::READABLE, // or 'GET'
			'callback' => array($this, 'results_API'),
		]);
	}
	
	/* If you want to add css or js to the plugin
	// Load ccs and js
	public function load_assets() 
	{
		wp_enqueue_style(
			'review-plugin',
			plugin_dir_url( __FILE__ ) . 'css/review-plugin.css',
			array(),
			1,
			'all'
		);
		
		wp_enqueue_script(
			'review-plugin',
			plugin_dir_url( __FILE__ ) . 'js/review-plugin.js',
			array('jquery'),
			1,
			true
		);
	}
	*/
	
	// Review admin menu, custom post type
	public function review_post_type() 
	{
		// Set labels for the custom post type
		$labels = array(
			'name' => 'Review',
			'singular_name' => 'Review',
			'add_new'    => 'Add Review',
			'add_new_item' => 'Enter Review Details',
			'all_items' => 'All Reviews',
			'edit_item' => 'Edit Review'
		);
		   
		// Set Options for this custom post type
		$args = array(
			'labels' => $labels,
			'public' => false,
			'show_ui' => true,
			'show_in_menu' => true,
			'has_archive' => true,
			'supports' => array('title', 'editor', 'author'),
			'menu_icon' => 'dashicons-media-text',
		);
		
		register_post_type('eb_review', $args);
		
		// Check for admin actions
		$this->do_review_actions();
	}
	
	// Shortcode form
	public function reviewFormShortcodeFunct()
	{?>
	
		<div>
			<h2>Leave a review here!</h2>
			<form method="POST">
				<input type="text" name="name" placeholder="Name *"> <br>
				<input type="email" name="email" placeholder="Email *"> <br>
		
				<textarea name="review" placeholder="Review us here..."></textarea> <br>
				
				<input type="submit" name="form_submit" value="Submit">
			</form>
		</div>
	<?php }
	
	// action to submit form to database and send verification email
	public function form_capture()
	{
		if(isset($_POST['form_submit']))
		{
			// Create review object
			$my_review = array(
			  'post_title'    => wp_strip_all_tags($_POST['name']),
			  'post_content'  => wp_strip_all_tags($_POST['review']),
			  'post_type' => 'eb_review',
			  'meta_input' => array (
				  'email' => wp_strip_all_tags($_POST['email'])
			  )
			);
			 
			// Insert the review into the database
			$result = wp_insert_post( $my_review );
			
			// Checks if values are stored into database
			if(!is_wp_error($result)) {
			  //the review is valid
			  echo "A review was successfully inserted into database.";
			} else {
			  //there was an error in the review insertion
			  echo $result->get_error_message();
			}
			
			// Sends verification email
			$name = sanitize_text_field($_POST['name']);
			$email = sanitize_text_field($_POST['email']);
			$review = sanitize_textarea_field($_POST['review']);
			
			$to = 'erik.berger12@hotmail.com';
			$subject = 'Test form submission';
			$message = ''.$name.' - '.$email.' - '.$review;
			
			wp_mail($to, $subject, $message);
		}	
	}
	
	/* email about new review
	function wpdocs_email_friends( $post_id ) {
		$friends = 'bob@example.org, susie@example.org';
		wp_mail( $friends, "sally's blog updated", 'I just put something on my blog: http://blog.example.com' );
	 
		return $post_id;
	}
	add_action( 'publish_post', 'wpdocs_email_friends' );
	*/
	
	// Create Shortcode to display form data on page
	public function shortcode_review_post_type_list($attr) {
			
		// Sets the default value of how many reviews per page that is allowed, can be changed in shortcode with e.g review_per_page="3"
		$atts = shortcode_atts(array(
			'review_per_page' => 5,
		), $attr);
	  
		$args = array(
						'post_type' => 'eb_review',
						'posts_per_page' => $atts['review_per_page'],
					 );
	  
		$query = new WP_Query($args);
	  
		if($query->have_posts()) :
	  
	  		$reviews = '';
			  
			while($query->have_posts()) :
	  
				$query->the_post() ;
						  
			$reviews .= '<div>';
			$reviews .= '<h3>' . get_the_title() . '</h3>';
			$reviews .= '<p>' . get_the_content() . '</p><br>';
			$reviews .= '</div>';
			
			//$this->result .= '<div>';
			//$this->result .= '<h3>' . get_the_title() . '</h3>';
			//$this->result .= '<p>' . get_the_content() . '</p><br>';
			//$this->result .= '</div>';
				
			endwhile;
	  
			wp_reset_postdata(); // Restore original post data
				
		else:
			
			echo "Sorry, was unable to retrieve reviews from database.";
	  
		endif;
	  
		return $reviews;
		//return "$this->result";
	}
	
	// Modify page url
	public function modify_list_row_actions($actions, $review) {
		
		if ( $review->post_type == 'eb_review' && current_user_can('edit_others_posts') ) {
			
			if ($review->post_status == 'draft') {
				
				$url = admin_url('edit.php?post_type=eb_review&ebaction=approvereview&rid=' . $review->ID);
				
				$actions[] = '<a href="'.$url.'">Approve</a>';
				
			} elseif ($review->post_status == 'publish') {
				
				$url = admin_url('edit.php?post_type=eb_review&ebaction=unapprovereview&rid=' . $review->ID);
				
				$actions[] = '<a href="'.$url.'">Unapprove</a>';
			}
		}
		
		return $actions;
	}
	
	// Checks if certain criteria is set and then change the status
	private function do_review_actions() {
		
		if ( isset($_GET['ebaction']) && $_SERVER['PHP_SELF'] == '/wp-admin/edit.php' && current_user_can('edit_others_posts') ) {
			
			switch($_GET['ebaction']) {
				case "approvereview":
					$this->review_set_status( $_GET['rid'], 'publish' );
					break;
				case "unapprovereview":
					$this->review_set_status( $_GET['rid'], 'draft' );
					break;
				default:
			}
		}
	}
	
	// Checks and then change the status of the review
	private function review_set_status($id, $status) {
		
		$data = array(
		  'ID' => $id,
		  'post_status' => $status,
		 );
		 
		 wp_update_post($data);
	}

}

new ReviewPlugin;