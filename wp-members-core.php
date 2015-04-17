<?php
/**
 * WP-Members Core Functions
 *
 * Handles primary functions that are carried out in most
 * situations. Includes commonly used utility functions.
 * 
 * This file is part of the WP-Members plugin by Chad Butler
 * You can find out more about this plugin at http://rocketgeek.com
 * Copyright (c) 2006-2015  Chad Butler
 * WP-Members(tm) is a trademark of butlerblog.com
 *
 * @package WordPress
 * @subpackage WP-Members
 * @author Chad Butler 
 * @copyright 2006-2015
 */


/**
 * Include utility functions
 */
require_once( 'utilities.php' ); 


if ( ! function_exists( 'wpmem' ) ):
/**
 * The Main Action Function.
 *
 * Does actions required at initialization
 * prior to headers being sent.
 *
 * @since 0.1 
 *
 * @global string $wpmem->action      The action variable also used in wpmem_securify.
 * @global string $wpmem->regchk Contains messages returned from $wpmem->action action functions, used in wpmem_securify.
 */
function wpmem() {

}
endif;


if ( ! function_exists( 'wpmem_securify' ) ):
/**
 * The Securify Content Filter.
 *
 * This is the primary function that picks up where wpmem() leaves off.
 * Determines whether content is shown or hidden for both post and pages.
 *
 * @since 2.0
 *
 * @global object $wpmem             The WP_Members object.
 * @global string $wpmem_themsg      Contains messages to be output.
 * @global string $wpmem_captcha_err Contains error message for reCAPTCHA.
 * @global object $post              The post object.
 * @param  string $content
 * @return string $content
 */
function wpmem_securify( $content = null ) {

	global $wpmem, $wpmem_themsg, $post;

	$post_type = get_post_type( $post );

	$content = ( is_single() || is_page() ) ? $content : wpmem_do_excerpt( $content );

	if ( ( ! wpmem_test_shortcode( $content, 'wp-members' ) ) ) {

		if ( $wpmem->regchk == "captcha" ) {
			global $wpmem_captcha_err;
			$wpmem_themsg = __( 'There was an error with the CAPTCHA form.' ) . '<br /><br />' . $wpmem_captcha_err;
		}

		// Block/unblock Posts
		if ( ! is_user_logged_in() && wpmem_block() == true ) {

			include_once( WPMEM_PATH . 'wp-members-dialogs.php' );
			
			// show the login and registration forms
			if ( $wpmem->regchk ) {
				
				// empty content in any of these scenarios
				$content = '';

				switch ( $wpmem->regchk ) {

				case "loginfailed":
					$content = wpmem_inc_loginfailed();
					break;

				case "success":
					$content = wpmem_inc_regmessage( $wpmem->regchk, $wpmem_themsg );
					$content = $content . wpmem_inc_login();
					break;

				default:
					$content = wpmem_inc_regmessage( $wpmem->regchk, $wpmem_themsg );
					$content = $content . wpmem_inc_registration();
					break;
				}

			} else {

				// toggle shows excerpt above login/reg on posts/pages
				global $wp_query;
				if ( $wp_query->query_vars['page'] > 1 ) {

						// shuts down excerpts on multipage posts if not on first page
						$content = '';

				} elseif ( $wpmem->show_excerpt[ $post->post_type ] == 1 ) {

					if ( ! stristr( $content, '<span id="more' ) ) {
						$content = wpmem_do_excerpt( $content );
					} else {
						$len = strpos( $content, '<span id="more' );
						$content = substr( $content, 0, $len );
					}

				} else {

					// empty all content
					$content = '';

				}

				$content = $content . wpmem_inc_login();

				$content = ( $wpmem->show_reg[ $post_type ] == 1 ) ? $content . wpmem_inc_registration() : $content;
			}

		// Protects comments if expiration module is used and user is expired
		} elseif ( is_user_logged_in() && wpmem_block() == true ){

			$content = ( $wpmem->use_exp == 1 && function_exists( 'wpmem_do_expmessage' ) ) ? wpmem_do_expmessage( $content ) : $content;

		}
	}

	/**
	 * Filter the value of $content after wpmem_securify has run.
	 *
	 * @since 2.7.7
	 *
	 * @param string $content The content after securify has run.
	 */
	$content = apply_filters( 'wpmem_securify', $content );

	if ( strstr( $content, '[wpmem_txt]' ) ) {
		// fix the wptexturize
		remove_filter( 'the_content', 'wpautop' );
		remove_filter( 'the_content', 'wptexturize' );
		add_filter( 'the_content', 'wpmem_texturize', 99 );
	}

	return $content;

} // end wpmem_securify
endif;


if ( ! function_exists( 'wpmem_do_sc_pages' ) ):
/**
 * Builds the shortcode pages (login, register, user-profile, user-edit, password).
 *
 * Some of the logic here is similar to the wpmem_securify() function. 
 * But where that function handles general content, this function 
 * handles building specific pages generated by shortcodes.
 *
 * @since 2.6
 *
 * @param  string $page
 * @param  string $redirect_to
 * @global object $wpmem
 * @global string $wpmem_themsg
 * @global object $post
 * @return string $content
 */
function wpmem_do_sc_pages( $page, $redirect_to = null ) {

	global $wpmem, $wpmem_themsg, $post;
	include_once( WPMEM_PATH . 'wp-members-dialogs.php' );

	$post_type = get_post_type( $post );

	$content = '';

	// deprecating members-area parameter to be replaced by user-profile
	$page = ( $page == 'user-profile' ) ? 'members-area' : $page;

	if ( $page == 'members-area' || $page == 'register' ) {

		if ( $wpmem->regchk == "captcha" ) {
			global $wpmem_captcha_err;
			$wpmem_themsg = __( 'There was an error with the CAPTCHA form.' ) . '<br /><br />' . $wpmem_captcha_err;
		}

		if ( $wpmem->regchk == "loginfailed" ) {
			return wpmem_inc_loginfailed();
		}

		if ( ! is_user_logged_in() ) {
			if ( $wpmem->action == 'register' ) {

				switch( $wpmem->regchk ) {

				case "success":
					$content = wpmem_inc_regmessage( $wpmem->regchk,$wpmem_themsg );
					$content = $content . wpmem_inc_login();
					break;

				default:
					$content = wpmem_inc_regmessage( $wpmem->regchk,$wpmem_themsg );
					$content = $content . wpmem_inc_registration();
					break;
				}

			} elseif ( $wpmem->action == 'pwdreset' ) {

				$content = wpmem_page_pwd_reset( $wpmem->regchk, $content );

			} else {

				$content = ( $page == 'members-area' ) ? $content . wpmem_inc_login( 'members' ) : $content;
				$content = ( $page == 'register' || $wpmem->show_reg[ $post_type ] != 1 ) ? $content . wpmem_inc_registration() : $content;
			}

		} elseif ( is_user_logged_in() && $page == 'members-area' ) {

			/**
			 * Filter the default heading in User Profile edit mode.
			 *
			 * @since 2.7.5
			 *
			 * @param string The default edit mode heading.
			 */
			$heading = apply_filters( 'wpmem_user_edit_heading', __( 'Edit Your Information', 'wp-members' ) );

			switch( $wpmem->action ) {

			case "edit":
				$content = $content . wpmem_inc_registration( 'edit', $heading );
				break;

			case "update":

				// determine if there are any errors/empty fields

				if ( $wpmem->regchk == "updaterr" || $wpmem->regchk == "email" ) {

					$content = $content . wpmem_inc_regmessage( $wpmem->regchk, $wpmem_themsg );
					$content = $content . wpmem_inc_registration( 'edit', $heading );

				} else {

					//case "editsuccess":
					$content = $content . wpmem_inc_regmessage( $wpmem->regchk, $wpmem_themsg );
					$content = $content . wpmem_inc_memberlinks();

				}
				break;

			case "pwdchange":

				$content = wpmem_page_pwd_reset( $wpmem->regchk, $content );
				break;

			case "renew":
				$content = wpmem_renew();
				break;

			default:
				$content = wpmem_inc_memberlinks();
				break;					  
			}

		} elseif ( is_user_logged_in() && $page == 'register' ) {

			$content = $content . wpmem_inc_memberlinks( 'register' );

		}

	}
	
	if ( $page == 'login' ) {
		$content = ( $wpmem->regchk == "loginfailed" ) ? wpmem_inc_loginfailed() : $content;
		$content = ( ! is_user_logged_in() ) ? $content . wpmem_inc_login( 'login', $redirect_to ) : wpmem_inc_memberlinks( 'login' );
	}
	
	if ( $page == 'password' ) {
		$content = wpmem_page_pwd_reset( $wpmem->regchk, $content );
	}
	
	if ( $page == 'user-edit' ) {
		$content = wpmem_page_user_edit( $wpmem->regchk, $content );
	}
	
	return $content;
} // end wpmem_do_sc_pages
endif;


if ( ! function_exists( 'wpmem_block' ) ):
/**
 * Determines if content should be blocked.
 *
 * @since 2.6
 *
 * @return bool $block true|false
 */
function wpmem_block() {

	global $post, $wpmem; 

	/**
	 * Backward compatibility for old block/unblock meta
	 */
	$meta = get_post_meta( $post->ID, '_wpmem_block', true );
	if ( ! $meta ) {
		// check for old meta
		$old_block   = get_post_meta( $post->ID, 'block',   true );
		$old_unblock = get_post_meta( $post->ID, 'unblock', true );
		$meta = ( $old_block ) ? 1 : ( ( $old_unblock ) ? 0 : $meta );
	}

	// setup defaults
		$defaults = array(
		'post_id'    => $post->ID,
		'post_type'  => $post->post_type,
		'block'      => ( $wpmem->block[ $post->post_type ] == 1 ) ? true : false,
		'block_meta' => $meta, // @todo get_post_meta( $post->ID, '_wpmem_block', true ),
		'block_type' => ( $post->post_type == 'post' ) ? $wpmem->block['post'] : ( ( $post->post_type == 'page' ) ? $wpmem->block['page'] : 0 ),
	);

	/**
	 * Filter the block arguments.
	 *
	 * @since 2.9.8
	 *
	 * @param array $args     Null.
	 * @param array $defaults Although you are not filtering the defaults, knowing what they are can assist developing more powerful functions.
	 */
	$args = apply_filters( 'wpmem_block_args', '', $defaults );

	// merge $args with defaults
	$args = ( wp_parse_args( $args, $defaults ) );

	if ( is_single() || is_page() ) {
		switch( $args['block_type'] ) {
			case 1: // if content is blocked by default
				$args['block'] = ( $args['block_meta'] == '0' ) ? false : $args['block'];
				break;
			case 0 : // if content is unblocked by default
				$args['block'] = ( $args['block_meta'] == '1' ) ? true : $args['block'];
				break;
		}
	} else {

		$args['block'] = false;

	}

	/**
	 * Filter the block boolean.
	 *
	 * @since 2.7.5
	 *
	 * @param bool  $args['block']
	 * @param array $args
	 */
	return apply_filters( 'wpmem_block', $args['block'], $args );
}
endif;


if ( ! function_exists( 'wpmem_shortcode' ) ):
/**
 * Executes various shortcodes.
 *
 * This function executes shortcodes for pages (settings, register, login, user-list,
 * and tos pages), as well as login status and field attributes when the wp-members tag
 * is used.  Also executes shortcodes for login status with the wpmem_logged_in tags
 * and fields when the wpmem_field tags are used.
 *
 * @since 2.4 
 *
 * @param  array  $attr page|url|status|msg|field|id
 * @param  string $content
 * @param  string $tag
 * @return string returns the result of wpmem_do_sc_pages|wpmem_list_users|wpmem_sc_expmessage|$content
 */
function wpmem_shortcode( $attr, $content = null, $tag = 'wp-members' ) {

	global $wpmem;

	// set all default attributes to false
	$defaults = array(
		'page'        => false,
		'redirect_to' => null,
		'url'         => false,
		'status'      => false,
		'msg'         => false,
		'field'       => false,
		'id'          => false,
		'underscores' => 'off',
	);

	// merge defaults with $attr
	$atts = shortcode_atts( $defaults, $attr, $tag );

	// handles the 'page' attribute
	if ( $atts['page'] ) {
		if ( $atts['page'] == 'user-list' ) {
			if ( function_exists( 'wpmem_list_users' ) ) {
				$content = do_shortcode( wpmem_list_users( $attr, $content ) );
			}
		} elseif ( $atts['page'] == 'tos' ) {
			return $atts['url'];
		} else {
			$content = do_shortcode( wpmem_do_sc_pages( $atts['page'], $atts['redirect_to'] ) );
		}

		// resolve any texturize issues...
		if ( strstr( $content, '[wpmem_txt]' ) ) {
			// fixes the wptexturize
			remove_filter( 'the_content', 'wpautop' );
			remove_filter( 'the_content', 'wptexturize' );
			add_filter( 'the_content', 'wpmem_texturize', 99 );
		}
		return $content;
	}

	// handles the 'status' attribute
	if ( ( $atts['status'] ) || $tag == 'wpmem_logged_in' ) {

		$do_return = false;

		// if using the wpmem_logged_in tag with no attributes & the user is logged in
		if ( $tag == 'wpmem_logged_in' && ( ! $attr ) && is_user_logged_in() )
			$do_return = true;

		// if there is a status attribute of "in" and the user is logged in
		if ( $atts['status'] == 'in' && is_user_logged_in() )
			$do_return = true;

		// if there is a status attribute of "out" and the user is not logged in
		if ( $atts['status'] == 'out' && ! is_user_logged_in() ) 
			$do_return = true;

		// if there is a status attribute of "sub" and the user is logged in
		if ( $atts['status'] == 'sub' && is_user_logged_in() ) {
			if ( $wpmem->use_exp == 1 ) {	
				if ( ! wpmem_chk_exp() ) {
					$do_return = true;
				} elseif ( $atts['msg'] == true ) {
					$do_return = true;
					$content = wpmem_sc_expmessage();
				}
			}
		}

		// return content (or empty content) depending on the result of the above logic
		return ( $do_return ) ? do_shortcode( $content ) : '';
	}

	// handles the wpmem_logged_out tag with no attributes & the user is not logged in
	if ( $tag == 'wpmem_logged_out' && ( ! $attr ) && ! is_user_logged_in() ) {
		return do_shortcode( $content );
	}

	// handles the 'field' attribute
	if ( $atts['field'] || $tag == 'wpmem_field' ) {
		if ( $atts['id'] ) {
			// we are getting some other user
			if ( $atts['id'] == 'get' ) {
				$the_user_ID = ( isset( $_GET['uid'] ) ) ? $_GET['uid'] : '';
			} else {
				$the_user_ID = $atts['id'];
			}
		} else {
			// get the current user
			$the_user_ID = get_current_user_id();
		}
		$user_info = get_userdata( $the_user_ID );

		if ( $atts['underscores'] == 'off' && $user_info ) {
			$user_info->$atts['field'] = str_replace( '_', ' ', $user_info->$atts['field'] );
		}

		return ( $user_info ) ? htmlspecialchars( $user_info->$atts['field'] ) . do_shortcode( $content ) : do_shortcode( $content );
	}

	// logout link shortcode
	if ( is_user_logged_in() && $tag == 'wpmem_logout' ) {
		$link = ( $atts['url'] ) ? wpmem_chk_qstr( $atts['url'] ) . 'a=logout' : wpmem_chk_qstr( get_permalink() ) . 'a=logout';
		$text = ( $content ) ? $content : __( 'Click here to log out.', 'wp-members' );
		return do_shortcode( "<a href=\"$link\">$text</a>" );
	}

}
endif;


if ( ! function_exists( 'wpmem_check_activated' ) ):
/**
 * Checks if a user is activated.
 *
 * @since 2.7.1
 *
 * @uses   wp_check_password
 * @param  int    $user
 * @param  string $username
 * @param  string $password
 * @return int    $user
 */ 
function wpmem_check_activated( $user, $username, $password ) {

	// password must be validated
	$pass = ( ( ! is_wp_error( $user ) ) && $password ) ? wp_check_password( $password, $user->user_pass, $user->ID ) : false;

	if ( ! $pass ) { 
		return $user;
	}

	// activation flag must be validated
	$active = get_user_meta( $user->ID, 'active', true );
	if ( $active != 1 ) {
		return new WP_Error( 'authentication_failed', __( '<strong>ERROR</strong>: User has not been activated.', 'wp-members' ) );
	}

	// if the user is validated, return the $user object
	return $user;
}
endif;


if ( ! function_exists( 'wpmem_login' ) ):
/**
 * Logs in the user.
 *
 * Logs in the the user using wp_signon (since 2.5.2). If login is
 * successful, it will set a cookie using wp_set_auth_cookie (since 2.7.7),
 * then it redirects and exits; otherwise "loginfailed" is returned.
 *
 * @since 0.1
 *
 * @uses   wp_signon
 * @uses   wp_set_auth_cookie
 * @uses   wp_redirect        Redirects to $redirect_to if login is successful.
 * @return string             Returns "loginfailed" if the login fails.
 */
function wpmem_login() {

	if ( $_POST['log'] && $_POST['pwd'] ) {

		/** get username and sanitize */
		$user_login = sanitize_user( $_POST['log'] );

		/** are we setting a forever cookie? */
		$rememberme = ( isset( $_POST['rememberme'] ) == 'forever' ) ? true : false;

		/** assemble login credentials */
		$creds = array();
		$creds['user_login']    = $user_login;
		$creds['user_password'] = $_POST['pwd'];
		$creds['remember']      = $rememberme;

		/** wp_signon the user and get the $user object */
		$user = wp_signon( $creds, false );

		/** if no error, user is a valid signon. continue */
		if ( ! is_wp_error( $user ) ) {

			/** set the auth cookie */
			wp_set_auth_cookie( $user->ID, $rememberme );

			/** determine where to put the user after login */			
			$redirect_to = ( isset( $_POST['redirect_to'] ) ) ? $_POST['redirect_to'] : $_SERVER['REQUEST_URI'];

			/**
			 * Filter the redirect url.
			 *
			 * @since 2.7.7
			 *
			 * @param string $redirect_to The url to direct to.
			 * @param int    $user->ID    The user's primary key ID.
			 */
			$redirect_to = apply_filters( 'wpmem_login_redirect', $redirect_to, $user->ID );

			/** and do the redirect */
			wp_redirect( $redirect_to );

			/** wp_redirect requires us to exit() */
			exit();
	
		} else {

			return "loginfailed";
		}

	} else {
		//login failed
		return "loginfailed";
	}
} // end of login function
endif;


if ( ! function_exists( 'wpmem_logout' ) ):
/**
 * Logs the user out then redirects.
 *
 * @since 2.0
 *
 * @uses wp_clearcookie
 * @uses wp_logout
 * @uses nocache_headers
 * @uses wp_redirect
 */
function wpmem_logout() {

	/**
	 * Filter the where the user goes when logged out.
	 *
	 * @since 2.7.1
	 *
	 * @param string The blog home page.
	 */
	$redirect_to = apply_filters( 'wpmem_logout_redirect', get_bloginfo( 'url' ) );

	wp_clear_auth_cookie();

	/** This action is defined in /wp-includes/pluggable.php **/
	do_action( 'wp_logout' );

	nocache_headers();

	wp_redirect( $redirect_to );
	exit();
}
endif;


if ( ! function_exists( 'wpmem_login_status' ) ):
/**
 * Displays the user's login status.
 *
 * @since 2.0
 *
 * @uses   wpmem_inc_memberlinks()
 * @param  boolean $echo           Determines whether function should print result or not (default: true).
 * @return string  $status         The user status string produced by wpmem_inc_memberlinks().
 */
function wpmem_login_status( $echo = true ) {

	include_once( 'wp-members-dialogs.php' );
	if ( is_user_logged_in() ) { 
		$status = wpmem_inc_memberlinks( 'status' );
		if ( $echo ) {
			echo $status; 
		}
		return $status;
	}
}
endif;


if ( ! function_exists( 'wpmem_inc_sidebar' ) ):
/**
 * Displays the sidebar.
 *
 * @since 2.0
 *
 * @uses wpmem_do_sidebar()
 */
function wpmem_inc_sidebar() {
	include_once('wp-members-sidebar.php');
	wpmem_do_sidebar();
}
endif;


if ( ! function_exists( 'widget_wpmemwidget_init' ) ):
/**
 * Initializes the widget.
 *
 * @since 2.0
 *
 * @uses register_widget
 */
function widget_wpmemwidget_init() {
	include_once( 'wp-members-sidebar.php' );
	register_widget( 'widget_wpmemwidget' );
}
endif;


if ( ! function_exists( 'wpmem_change_password' ) ):
/**
 * Handles user password change (not reset).
 *
 * @since 2.1
 *
 * @global $user_ID
 * @return string the value for $wpmem->regchk
 */
function wpmem_change_password() {

	global $user_ID;
	if ( isset( $_POST['formsubmit'] ) ) {

		$pass1 = $_POST['pass1'];
		$pass2 = $_POST['pass2'];

		if ( ! $pass1 && ! $pass2 ) { // check for both fields being empty

			return "pwdchangempty";

		} elseif ( $pass1 != $pass2 ) { // make sure the fields match

			return "pwdchangerr";

		} else { // update password in db (wp_update_user hashes the password)

			wp_update_user( array ( 'ID' => $user_ID, 'user_pass' => $pass1 ) );

			/**
			 * Fires after password change.
			 *
			 * @since 2.9.0
			 *
			 * @param int $user_ID The user's numeric ID.
			 */
			do_action( 'wpmem_pwd_change', $user_ID );

			return "pwdchangesuccess";

		}
	}
	return;
}
endif;


if ( ! function_exists( 'wpmem_reset_password' ) ):
/**
 * Resets a forgotten password.
 *
 * @since 2.1
 *
 * @uses   wp_generate_password
 * @uses   wp_update_user
 * @return string value for $wpmem->regchk
 */
function wpmem_reset_password() {

	global $wpmem;

	if ( isset( $_POST['formsubmit'] ) ) {

		/**
		 * Filter the password reset arguments.
		 *
		 * @since 2.7.1
		 *
		 * @param array The username and email.
		 */
		$arr = apply_filters( 'wpmem_pwdreset_args', array( 
			'user'  => ( isset( $_POST['user']  ) ) ? $_POST['user']  : '', 
			'email' => ( isset( $_POST['email'] ) ) ? $_POST['email'] : '',
		) );

		if ( ! $arr['user'] || ! $arr['email'] ) { 

			// there was an empty field
			return "pwdreseterr";

		} else {

			if ( username_exists( $arr['user'] ) ) {

				$user = get_user_by( 'login', $arr['user'] );

				if ( strtolower( $user->user_email ) !== strtolower( $arr['email'] ) || ( ( $wpmem->mod_reg == 1 ) && ( get_user_meta( $user->ID,'active', true ) != 1 ) ) ) {
					// the username was there, but the email did not match OR the user hasn't been activated
					return "pwdreseterr";

				} else {

					// generate a new password
					$new_pass = wp_generate_password();

					// update the users password
					wp_update_user( array ( 'ID' => $user->ID, 'user_pass' => $new_pass ) );

					// send it in an email
					require_once( 'wp-members-email.php' );
					wpmem_inc_regemail( $user->ID, $new_pass, 3 );

					/**
					 * Fires after password reset.
					 *
					 * @since 2.9.0
					 *
					 * @param int $user_ID The user's numeric ID.
					 */
					do_action( 'wpmem_pwd_reset', $user->ID );

					return "pwdresetsuccess";
				}
			} else {

				// username did not exist
				return "pwdreseterr";
			}
		}
	}
	return;
}
endif;


if ( ! function_exists( 'wpmem_no_reset' ) ):
/**
 * Keeps users not activated from resetting their password 
 * via wp-login when using registration moderation.
 *
 * @since 2.5.1
 *
 * @return bool
 */
function wpmem_no_reset() {

	global $wpmem;

	if ( strpos( $_POST['user_login'], '@' ) ) {
		$user = get_user_by( 'email', trim( $_POST['user_login'] ) );
	} else {
		$username = trim( $_POST['user_login'] );
		$user     = get_user_by( 'login', $username );
	}

	if ( $wmem->mod_reg == 1 ) { 
		if ( get_user_meta( $user->ID, 'active', true ) != 1 ) {
			return false;
		}
	}

	return true;
}
endif;


/**
 * Anything that gets added to the the <html> <head>.
 *
 * @since 2.2
 */
function wpmem_head() { 
	echo "<!-- WP-Members version ".WPMEM_VERSION.", available at http://rocketgeek.com/wp-members -->\r\n";
}


/**
 * Add registration fields to the native WP registration.
 *
 * @since 2.8.3
 */
function wpmem_wp_register_form() {
	include_once( 'native-registration.php' );
	wpmem_do_wp_register_form();
}


/**
 * Validates registration fields in the native WP registration.
 *
 * @since 2.8.3
 *
 * @param $errors
 * @param $sanatized_user_login
 * @param $user_email
 * @return $errors
 */
function wpmem_wp_reg_validate( $errors, $sanitized_user_login, $user_email ) {

	$wpmem_fields = get_option( 'wpmembers_fields' );
	$exclude = wpmem_get_excluded_meta( 'register' );

	foreach ( $wpmem_fields as $field ) {
		$is_error = false;
		if ( $field[5] == 'y' && $field[2] != 'user_email' && ! in_array( $field[2], $exclude ) ) {
			if ( ( $field[3] == 'checkbox' ) && ( ! isset( $_POST[$field[2]] ) ) ) {
				$is_error = true;
			} 
			if ( ( $field[3] != 'checkbox' ) && ( ! $_POST[$field[2]] ) ) {
				$is_error = true;
			}
			if ( $is_error ) { $errors->add( 'wpmem_error', sprintf( __('Sorry, %s is a required field.', 'wp-members'), $field[1] ) ); }
		}
	}

	return $errors;
}


/**
 * Inserts registration data from the native WP registration.
 *
 * @since 2.8.3
 *
 * @param $user_id
 */
function wpmem_wp_reg_finalize( $user_id ) {

	$native_reg = ( isset( $_POST['wp-submit'] ) && $_POST['wp-submit'] == esc_attr( __( 'Register' ) ) ) ? true : false;
	$add_new  = ( isset( $_POST['action'] ) && $_POST['action'] == 'createuser' ) ? true : false;
	if ( $native_reg || $add_new ) {
		// get the fields
		$wpmem_fields = get_option( 'wpmembers_fields' );
		// get any excluded meta fields
		$exclude = wpmem_get_excluded_meta( 'register' );
		foreach ( $wpmem_fields as $meta ) {
			if ( isset( $_POST[$meta[2]] ) && ! in_array( $meta[2], $exclude ) ) {
				update_user_meta( $user_id, $meta[2], sanitize_text_field( $_POST[$meta[2]] ) );
			}
		}
	}
	return;
}


/**
 * Loads the stylesheet for backend registration.
 *
 * @since 2.8.7
 */
function wpmem_wplogin_stylesheet() {
	echo '<link rel="stylesheet" id="custom_wp_admin_css"  href="' . WPMEM_DIR . 'css/wp-login.css" type="text/css" media="all" />';
}


/**
 * Securifies the comments.
 *
 * If the user is not logged in and the content is blocked
 * (i.e. wpmem_block() returns true), function loads a
 * dummy/empty comments template.
 *
 * @since 2.9.9
 *
 * @return bool $open Whether the current post is open for comments.
 */
function wpmem_securify_comments( $open ) {
	return ( ! is_user_logged_in() && wpmem_block() ) ? false : $open;
}

/** End of File **/