<?php
/*
Plugin Name: Themeists Favicon
Plugin URI: #
Description: This plugin allows you to generate a favicon from a graphic you either upload or place on your server. If you are using a Themeists theme with our options panel then you can upload a file directly from there. Otherwise place a file called favicon.jpg (or .png or .gif) in your WordPress root folder and we'll do the rest.
Version: 1.0
Author: Themeists
Author URI: #
License: GPL2
*/

if( !class_exists( 'ThemeistsFavicon' ) ):


	/**
	 * Uses jpg/gif/png images. If a themeists theme is being used
	 * with the options panel then we place an option to do the uploading in the 'Basic' tab. Otherwise, we look for 
	 * a file favicon.jpg|png|gif and convert.
	 *
	 * @author Richard Tape
	 * @package ThemeistsFavicon
	 * @since 1.0
	 */
	
	class ThemeistsFavicon
	{


		/**
		 * We might not be using a themeists theme (which means we can't add anything to the options panel). By default,
		 * we'll say we are not. We check if the theme's author is Themeists to set this to true during instantiation.
		 *
		 * @author Richard Tape
		 * @package ThemeistsFavicon
		 * @since 1.0
		 */
		
		var $using_themeists_theme = false;
		

		/**
		 * Initialise ourselves and do a bit of setup
		 *
		 * @author Richard Tape
		 * @package ThemeistsFavicon
		 * @since 1.0
		 * @param None
		 * @return None
		 */

		function ThemeistsFavicon()
		{

			$theme_data = wp_get_theme();
			$theme_author = $theme_data->display( 'Author', false );

			if( strtolower( trim( $theme_author ) ) == "themeists" )
				$this->using_themeists_theme = true;


			//Check if we have the GD Library Installed
			register_activation_hook( __FILE__, 			array( &$this, 'on_plugin_activation' ) );


			if( $this->using_themeists_theme )
			{

				//First, let's add the image upload option to the themeists options panel
				add_action( 'of_set_options_in_basic_end', 	array( &$this, 'add_options_to_themeists_options_panel' ), 10, 1 );

				//Add new image size
				add_action( 'after_setup_theme', 			array( &$this, 'add_new_image_size' ) );

				//Output the markup on front- and back-end
				add_action( 'wp_head', 						array( &$this, 'output_favicon_markup' ) );
				add_action( 'admin_head',					array( &$this, 'output_favicon_markup' ) );

				add_action( 'of_set_options_in_help_end', 	array( &$this, 'themeistsfavicon_faqs' ), 11, 1 );

			}

		}/* ThemeistsFavicon() */



		/**
		 * When someone activates the plugin we need to check if imagick is installed (or a class exists for it). If it isn't
		 * then we don't let the person activate this plugin and throw them an error.
		 *
		 * @author Richard Tape
		 * @package ThemeistsFavicon
		 * @since 1.0
		 * @param None
		 * @return None
		 */
		
		function on_plugin_activation()
		{

			if( !extension_loaded( 'gd' ) || !function_exists( 'gd_info' ) ) 
			{

				deactivate_plugins( basename( __FILE__ ) );	// Deactivate ourself 
                
                wp_die( __( "Sorry but this plugin requires you to have the GD Library installed. If you have absolutely no idea what that is, then please contact your hosting provider and ask them to add it - it should be free. If you're a bit of a whizz-kid then feel free to compile PHP with the GD Library support and then activate this plugin.", 'themeistsfavicon' ) );

			}

		}/* on_plugin_activation() */
		

		/**
		 * If we are using a themeists theme (so $this->using_themeists_theme is true) then we add options to the 
		 * theme options panel.
		 *
		 * @author Richard Tape
		 * @package ThemeistsFavicon
		 * @since 1.0
		 * @param None
		 * @return None
		 */

		function add_options_to_themeists_options_panel()
		{

			global $options;

			$options[] = array(
				'name' => __( 'Favicon image upload', 'themeistsfavicon' ),
				'desc' => __( 'Upload an image in either .jpg .gif or .png format and we will do the rest. Ideally, you\'ll upload a square image that is 16x16px in dimension. Otherwise, we will do our best to resize appropriately.', 'themeistsfavicon' ),
				'id' => 'favicon_image',
				'type' => 'upload'
			);


		}/* add_options_to_themeists_options_panel() */



		/**
		 * We need to add another image size so WP does our resizing for us - the 16x16 force cropped
		 *
		 * @author Richard Tape
		 * @package ThemeistsFavicon
		 * @since 1.0
		 * @param 
		 * @return 
		 */
		
		function add_new_image_size()
		{

			add_image_size( 'favicon', 16, 16, true );

		}/* add_new_image_size */



		/**
		 * First checks if we're on a themeists theme. If we are we check whether an image has
		 * been uploaded to the favicon_image option. If one hasn't, then we'll check for one in
		 * the root of this site. If one doesn't exist there we'll use a default found in
		 * the root of this plugin. If we're not on a themeists theme, we'll check the root of this
		 * site for a favicon file. If one isn't found we'll return the default one in the root of this
		 * plugin. The path is passed through a filter themeists_favicon_path so this can very easily
		 * be modified by a theme or plugin.
		 *
		 * @author Richard Tape
		 * @package ThemeistsFavicon
		 * @since 1.0
		 * @param (string) path to uploaded graphic
		 * @return (strong) path to converted .ico file
		 */

		function make_favicon_from_upload()
		{

			//First we check if we're on a themeists theme or not
			if( $this->using_themeists_theme )
			{

				//Check the favicon_image options panel option
				$uploaded_image = of_get_option( 'favicon_image' );

				if( !isset( $uploaded_image ) || $uploaded_image == "" || !$uploaded_image )
				{

					return $this->check_for_favicon_in_root_or_use_default();

				}
				else
				{

					//There has been a file uploaded in the options panel. Let's use it.
					//When we upload an image, Options Framework creates a post of type "optionsframework" to
					//attach that image to. It's post_name (in wp_posts) is of-<option_name> so, as our option
					//is called favicon_image we will have a post of post_type optionsframework with a post_name
					//of of-favicon_image. We need to get the ID of that post.
					global $wpdb;
					$favicon_post_id = $wpdb->get_var( "SELECT ID FROM $wpdb->posts WHERE post_name = 'of-favicon_image'" );

					//Get the attachment ID of the image for this post
					$args = array(
					   'post_type' => 'attachment',
					   'numberposts' => 1,
					   'post_status' => null,
					   'post_parent' => $favicon_post_id
					  );

					$attachment = get_posts( $args );

					//Get the url from this attachment ID
					$image_attributes = wp_get_attachment_image_src( $attachment[0]->ID, 'favicon' ); // (array) [0] is the url

					$return_path = $image_attributes[0];

					//Set an option so we can cache this
					update_option( 'themeists_favicon_path', $return_path );

					return apply_filters( 'themeists_favicon_path', $return_path );

				}

			}
			else
			{

				//We're not on a themeists theme
				return $this->check_for_favicon_in_root_or_use_default();

			}

		}/* convert_to_ico() */



		/**
		 * Returns the path of the favicon. First checks to see if an option is stored (which is set by method above)
		 * which should help with caching. This option 'themeists_favicon_path', if set, is returned. If it isn't set
		 * then this function calls the make_favicon_from_upload() method which in turn creates the file.
		 * IE, isn't it always IE, needs the path to be fully qualified.
		 *
		 * @author Richard Tape
		 * @package 
		 * @since 1.0
		 * @param 
		 * @return (string) Path of the favicon
		 */
		
		function get_favicon_path()
		{

			if( get_option( 'themeists_favicon_path' ) )
			{
				return get_option( 'themeists_favicon_path' );
			}
			else
			{

				return $this->make_favicon_from_upload();

			}

		}/* get_favicon_path() */


		/**
		 * The output markup for the favicon for the front end and dashboard. This is hooked into
		 * wp_head for the front end and admin_head for the back end.
		 *
		 * @author Richard Tape
		 * @package ThemeistsFavicon
		 * @since 1.0
		 * @param None
		 * @return None
		 */
		
		function output_favicon_markup()
		{

			echo '<link rel="Shortcut Icon" type="image/png" href="' . $this->get_favicon_path() . '" />';

		}/* output_favicon_markup() */



		/**
		 * If we're on a themeists them but a file hasn't been uploaded to the 'Favicon Image Upload' field
		 * in the options panel
		 *
		 * OR
		 *
		 * We're not using a themeists theme at all:
		 *
		 * We check for a favicon.png/.ico in the WP root, if that doesn't exist we use the default in this
		 * plugin 
		 *
		 * @author Richard Tape
		 * @package 
		 * @since 1.0
		 * @param 
		 * @return 
		 */
		
		function check_for_favicon_in_root_or_use_default()
		{

			if( file_exists( ABSPATH . 'favicon.png' ) || file_exists( ABSPATH . 'favicon.ico' ) )
			{
				
				//Get the correct path
				$return_path = ( file_exists( ABSPATH . 'favicon.png' ) ) ? get_bloginfo( 'wpurl' ) . '/favicon.png' : get_bloginfo( 'wpurl' ) . '/favicon.ico';

				//Set an option so we can cache this
				update_option( 'themeists_favicon_path', $return_path );
				
				return apply_filters( 'themeists_favicon_path', $return_path );

			}
			else
			{

				//No favicon in the root, so return the path to the image in this plugin's root
				$return_path = plugins_url( 'favicon.png', __FILE__ );

				//Set an option so we can cache this
				update_option( 'themeists_favicon_path', $return_path );

				return apply_filters( 'themeists_favicon_path', $return_path );

			}

		}/* check_for_favicon_in_root_or_use_default() */


		/**
		 * Add an FAQ to the themeists option panel
		 *
		 * @author Richard Tape
		 * @package 
		 * @since 1.0
		 * @param 
		 * @return 
		 */
		
		function themeistsfavicon_faqs()
		{

			global $options;

			// Favicons ============================================

			$options[] = array(
				'name' => __( 'Hey, what is with the favicons?', 'themeistsfavicon' ),
				'desc' => __( '<p>Immediately as you activate the Themeists Favicon plugin, you will have a WordPress icon as your favicon. This is the default icon which you can see in the root folder of the plugin.</p><p>There are 3 ways you can change your favicon.</p><p>1) Place a file called favicon.png or favicon.ico in the <strong>root</strong> of your WordPress install (where your wp-config.php file probably resides).<br />2) Upload an image in the Favicon Image Upload option which has been added to the "Basic" tab of these options, or <br />3) If you are a developer you can use the <strong>themeists_favicon_path</strong> filter in a function of your choosing to specify a particular path.</p>', 'themeistsfavicon' ),
				'id' => 'favicons_q1',
				'std' => '',
				'type' => 'qna'
			);

			// Favicons ============================================

		}/* themeistsfavicon_faqs() */

		
	}/* class ThemeistsFavicon */

endif;


//And so it begins
$themeists_favicon = new ThemeistsFavicon;