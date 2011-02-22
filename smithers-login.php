<?php
/*
Plugin Name: Smithers Login
Plugin URI: http://wpsmith.net/smithers-login/
Description: This plugin enables you to specify a style sheet to be used on the login page for each MS site.
Version: 0.4
Stable Tag: 0.4
Author: wpsmith
Author URI: http://www.wpsmith.net/
*/

class smithers_login
{
	var $default_settings;
	var $current_settings;
	var $errors;
	var $sl_logo;
	
	function smithers_login()
	{
		
		//default variables
		$name = get_bloginfo( 'name' );
		$blogURL = get_bloginfo( 'url' );
		$blogstyle = WP_PLUGIN_URL.'/smithers-login/smithers-login.css';
		
		// Default Settings
		$this->default_settings = array(
			'sl_style_sheet' => $blogstyle,
			'sl_head_url' => $blogURL,
			'sl_head_title' => 'Powered by ' . $name ,
			'sl_msg_css'=> '.login .custom-login-message, .login .custom-logout-message,.custom-message {
			-moz-border-radius:3px 3px 3px 3px;
			border-style:solid;
			border-width:1px;
			margin:15px 26px 0 24px !important;
			padding:12px !important;
			}
			.login .custom-login-message, .login .custom-logout-message, .custom-message  {
			background-color:#FFFFE0;
			border-color:#E6DB55;
			}',
			'sl_login_msg' => '',
			'sl_logout_msg' => '',
			'sl_reg_msg' => '',
			'sl_pass_msg' => '',
			'sl_login_error_loggedout' => '',
			'sl_login_error_registerdiabled' => '',
			'sl_login_error_confirm' => '',
			'sl_login_error_newpass' => '',
			'sl_login_error_registered' => ''
			);
		
		// Manage form POSTs
		if ($_POST['sl_update']) {
			$this->update_wpdb($_POST);
		}

		// Set Default settings as current if no options in wpdb
		// Otherwise populate current settings from wpdb
		foreach($this->default_settings  as $label => $value)
		{
			//if ($label)
			if(!get_option($label))
			{
				$this->current_settings[$label] = $value;
			}
			else
			{
				$this->current_settings[$label] = get_option($label);
			}
		}

		$this->add_hooks();
	}

	//gets value from current_settings array
	function g($key)
	{
		return stripslashes($this->current_settings[$key]);
	}

	//checks if keys array value is empty
	function e($key)
	{
		return empty($this->current_settings[$key]);
	}

	function update_wpdb($data)
	{
		global $wpdb;

		if($data)
		{
			$this->current_settings = $data;
			foreach($data as $name => $value)
			{
				if ($name=='sl_logo1') $name='sl_logo';
				update_option($name, $value);
				if (substr($name,0,7) == "sl_logo") {
					$this->add_option_to_blog_table(substr($name,7), $name, $value);
				}
			}
		}
	}

	function add_hooks()
	{
		// Add Admin Menu
		add_action('admin_menu', array(&$this, 'admin_menu'));
		//add_action('admin_menu', array(&$this, 'get_ms_sites'));
		// Adds the CSS to the header
		add_action('login_head', array(&$this, 'add_style_sheet'));
		// Changes the Link URL
		add_filter('login_headerurl', array(&$this, 'change_head_url'));
		// Changes the Page Title
		add_filter('login_headertitle', array(&$this, 'change_head_title'));
		// Adds the a P tag to the Login Page
		add_action('login_head', array(&$this, 'custom_login_head'));
		add_filter('login_message', array(&$this, 'custom_logout_message'));
		// Adds the a P tag to the Login Page
		add_action('login_form', array(&$this, 'add_login_msg'));
		// Adds the a P tag to the Registration Page
		add_action('register_form', array(&$this, 'add_register_msg'));
		// Adds the a P tag to the Lost Password Page
		add_action('lostpassword_form', array(&$this, 'add_lostpass_msg'));
	}

	function add_style_sheet()
	{
		/*** check for https ***/
		$protocol = $_SERVER['HTTPS'] == 'on' ? 'https' : 'http';
		
		/*** return the full address ***/
		$fulladdress = $protocol.'://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
		
		global $wpdb;
		$blogs = $wpdb->get_results( $wpdb->prepare("SELECT blog_id, domain, path FROM $wpdb->blogs WHERE site_id = %d AND archived = '0' AND mature = '0' AND spam = '0' AND deleted = '0' ORDER BY registered DESC", $wpdb->siteid), ARRAY_A );
		
		foreach ($blogs as $blog)
		{
			$phrase=$blog['domain'];
			$phrase_array = explode('.',$phrase);
			$max_words=1;
			if(count($phrase_array) > $max_words && $max_words > 0)
				$phrase = implode(' ',array_slice($phrase_array, 0, $max_words));
			if(strpos($fulladdress,$phrase) == true)
			{
				$subdomain=$phrase;
				$subdomainID=$blog['blog_id'];
				break;
			}
		}
		
		$html = "<link rel='stylesheet' id='smithers-login-css' type='text/css' media='screen' href='".$this->g('sl_style_sheet')." ' />\n";
		
		$phrase=$subdomain;
		$option_logoimg = 'sl_logo';
		if ($subdomainID != 1)
			$option_logoimg.= $subdomainID;
		else
			$option_logoimg.='';
		${$phrase} = array(
			'logoimg' => $this->get_ms_option($option_logoimg),
			'loginbutton' => 'default-button-grad.png'
		);
		$css = '<style type="text/css"> 
			#login { background:url(\''. ${$phrase}["logoimg"].'\') center top no-repeat !important;}
			input.button-primary, button.button-primary, a.button-primary {background: url(\''.plugin_dir_url(__FILE__).'images/'. ${$phrase}["loginbutton"].'\') !important;}
		</style>';
		
		_e($html);
		_e($css);
	}
	
	function change_head_url($s)
	{
		return $this->e('sl_head_url') ? $s : $this->g('sl_head_url');
	}

	function change_head_title($s)
	{
		$this->change_login_error_msgs();
		return $this->e('sl_head_title') ? $s : $this->g('sl_head_title');
	}

	function add_login_msg()
	{
		$html = '<div id="sl_log_form_msg">'.$this->g('sl_login_msg')."</div>\n";
		if (!$this->e('sl_login_msg'))
			_e($html);
	}

	function change_login_error_msgs()
	{
		global $errors;
		foreach ($errors as $k => $v)
		{
			if (!$this->e('sl_login_error_'.$k))
				$errors[$k] = $this->g('sl_login_error_'.$k);
		}
	}

	function add_register_msg()
	{
		$html = '<div id="sl_reg_form_msg">'.$this->g('sl_reg_msg')."</div>\n";
		if (!$this->e('sl_reg_msg'))
			_e($html);
	}

	function add_lostpass_msg()
	{
		$html = '<p class="message sl_pass_form_msg">'.$this->g('sl_pass_msg')."</p>\n";
		if (!$this->e('sl_pass_msg'))
			_e($html);
	}

	function custom_login_head() //outputs the CSS needed to blend custom-message with the normal message
	{
		$css="<style type='text/css'>";
		if (strlen($this->g('sl_msg_css'))>1) {
			$css .= $this->g('sl_msg_css');
		}
		else {
			$css .= "
			
			.login .custom-login-message, .login .custom-logout-message,.custom-message {
			-moz-border-radius:3px 3px 3px 3px;
			border-style:solid;
			border-width:1px;
			margin:15px 26px 0 24px !important;
			padding:12px !important;
			}
			.login .custom-login-message, .login .custom-logout-message, .custom-message  {
			background-color:#FFFFE0;
			border-color:#E6DB55;
			}";
		}
		$css .= "</style>";
		echo $css;
	
	}
	
	function custom_logout_message() {
		$message = '';
		if ( isset($_GET['loggedout']) && TRUE == $_GET['loggedout'] ) //check to see if it's the logout screen
		{
			if (strlen ($this->g('sl_logout_msg')) > 0)
				$message = "<p class='custom-logout-message'>".$this->g('sl_logout_msg')."</p><br />";
		}
		else //they are logged in
		{
			if (strlen ($this->g('sl_login_msg')) > 0)
				$message = "<p class='custom-login-message'>".$this->g('sl_login_msg')."</p><br />";
		}
		return $message;
	}

	// Manage Admin Options
	function admin_menu() 	{
		// Add admin page to the Appearance Tab of the admin section
		add_submenu_page('wpmu-admin.php', 'Smithers Login Options', 'Smithers Login', 8, __FILE__, array(&$this, 'admin_page'));
	}
	
	function get_ms_option($option_name) {
		global $wpdb;
		$blogs = $this->get_ms_sites();
		
		if ($option_name == "sl_logo")
			$option_name = "sl_logo1";
		$select_statement = "SELECT *
				FROM `".DB_NAME."`.`".$wpdb->get_blog_prefix($blogID)."options`
				WHERE `option_name` LIKE '".$option_name."'";
		$option_value = $wpdb->get_results( $select_statement, ARRAY_A );
		return $option_value[0]['option_value'];
	}
	
	function get_ms_sites() {
		global $wpdb;
		
		$query = "SELECT * FROM {$wpdb->blogs} WHERE site_id = '{$wpdb->siteid}' ";
		$query .= " ORDER BY {$wpdb->blogs}.blog_id ";
		$order = "ASC";
		$query .= $order;
		$blog_list = $wpdb->get_results( $query, ARRAY_A );
		
		return $blog_list;
	}
	
	function get_site_id($domain) {
		$blogs = $this->get_ms_sites;
		
		foreach ($blogs as $blog) {
			if ($blog['domain'] == $domain)
				$siteID=$blog['blog_id'];
		}
		
		return $siteID;
	}
	
	function get_site_domain($siteID) {
		$blogs = $this->get_ms_sites;
		
		foreach ($blogs as $blog) {
			if ($blog['blog_id'] == $siteID)
				$domain=$blog['domain'];
		}
		
		return $domain;
	}
	
	function get_ms_options_tables() {
		global $wpdb;
		
		$blogs = $this->get_ms_sites();
		foreach ($blogs as $blog) {
			$tables[] = $wpdb->get_blog_prefix($blog['blog_id']).'options';
		}
		
		return $tables;
	}
	
	function add_option_to_blog_table ($blogID, $option_name, $option_value) {
		global $wpdb;

		$wpdb->query( $wpdb->prepare( "
			INSERT INTO `".DB_NAME."`.`".$wpdb->get_blog_prefix($blogID)."options`
			(
			`option_id` ,
			`blog_id` ,
			`option_name` ,
			`option_value` ,
			`autoload`
			)
			VALUES (NULL, '0', '%s', '%s', 'yes')
			ON DUPLICATE KEY UPDATE `option_name` = VALUES(`option_name`), `option_value` = '%s', `autoload` = VALUES(`autoload`)",
			array($option_name, $option_value, $option_value) ) );
	
	$insertstr = "<br />INSERT INTO `".DB_NAME."`.`".$wpdb->get_blog_prefix($blogID)."options` (
			`option_id` ,
			`blog_id` ,
			`option_name` ,
			`option_value` ,
			`autoload`
			)
			VALUES (NULL, '0','".$option_name."', '".$option_value."', 'yes')
			ON DUPLICATE KEY UPDATE `option_name` = VALUES(`option_name`), `option_value` ='".$option_value."', `autoload` = VALUES(`autoload`) <br />";
	}
	// Admin page
	function admin_page()	{
	if ( current_user_can('manage_options') ) {
	
			if ($_POST['sl_update'])
				_e('<div id="message" class="updated fade"><p>Smithers Login Options Saved</p></div>');

			$html_hints = '<div class="wrap" id="html_hints">
	<h2>XHTML Hints</h2>
	<textarea cols="40" rows="5" disabled>
	Link:
	<a href="http://url">Link</a>

	Line Break:
	<br />

	Ordered List:
	<ol>
		<li>Item 1</li>
		<li>Item 2</li>
	</ol>

	Un-Ordered List:
	<ul>
		<li>Item</li>
		<li>Item</li>
	</ul>
	</textarea>
	</div>';
			//show styles
			_e('
				<style type="text/css">
					.sl_text_area
					{
						width: 600px;
						height: 200px;
					}

					.sl_text_box
					{
						width: 600px;
					}

					form#login_style label
					{
						font-weight: bold;
					}

					#html_hints
					{
						position: absolute;
						left: 670px;
						margin-top: -30px;
					}

					#html_hints textarea
					{
						background: #e3e3ea;
						color: #777;
						width: 280px;
						height: 300px;
						font-size: 12px;
						font-family: courier;
						padding: 15px;
					}
				</style>
			');
			
			//Get imgs for ea domain
			_e(' <div class="wrap">
						<h2>Smithers Login Domain Options</h2>
						<em>This plugin must be Network Activated to work properly.</em>
				');
				
			_e('
				<form id="login_style" method="post">
					<input type="hidden" name="sl_update" id="sl_update" value="ls_update" />
			');	
			
			global $wpdb;	
			
			$blogs = $this->get_ms_sites();		
			$sl_tables = $this->get_ms_options_tables();
			
			foreach($blogs as $blog)
			{
				
				$sl_logo['sl_logo'.$blog['blog_id']]='';
				if(!get_option($optionname))
				{
					$time = current_time('mysql');
					$y = substr( $time, 0, 4 );
					$m = substr( $time, 5, 2 );
					$this->current_settings[$optionname] = WP_CONTENT_URL .'/blogs.dir/'.$blog['blog_id'].'/files/'.$y.'/'.$m.'/logo.jpg';
				}
				else
					$this->current_settings[$optionname] = get_option($optionname);

				$phrase=$blog['domain'];
				$phrase_array = explode('.',$phrase);
				$max_words=1;
				if(count($phrase_array) > $max_words && $max_words > 0)
					$phrase = implode(' ',array_slice($phrase_array, 0, $max_words));
				${$phrase} = array(
					'logoimg' => get_option('sl_logo'.$blog['blog_id']),
					'loginbutton' => 'images/default-button-grad.png'
				);
				
				$option_name = 'sl_logo'.$blog['blog_id'];;
				$option_value = ${$phrase}['logoimg'];
				$sl_logo['sl_logo'.$blog['blog_id']] = $option_value;
						
				_e('	<p>
							<label for="sl_logo">Enter Login Image for Domain: '. $blog['domain'] . ' ('.$blog['blog_id'].') </label> <a href="http://'.$blog['domain'].'/wp-login.php">Visit</a><br />
							<input type="text" name="sl_logo'.$blog["blog_id"].'" id="sl_logo" class="sl_text_box" value="'.$sl_logo['sl_logo'.$blog['blog_id']].'" />
						</p>
				');
			}
			
			
			_e('<p><input type="submit" value="Save  &raquo;"></p>
					</div>');
			//show form
			_e('
					<div class="wrap">
						<h2>General Options</h2>
						<p>
							<label for="sl_style_sheet">Stylesheet:</label><br />
							<input type="text" name="sl_style_sheet" id="sl_style_sheet" class="sl_text_box" value="'.$this->g('sl_style_sheet').'" />
						</p>
						<p>
							<label for="sl_head_url">Header Image Link URL:</label><br />
							<input type="text" name="sl_head_url" id="sl_head_url" class="sl_text_box" value="'.$this->g('sl_head_url').'" />
						</p>
						<p>
							<label for="sl_head_title">Header Image Link Title:</label><br />
							<input type="text" name="sl_head_title" id="sl_head_title" class="sl_text_box" value="'.$this->g('sl_head_title').'" />
						</p>
						<p><input type="submit" value="Save  &raquo;"></p>
					</div>
					<div class="wrap">
						<h2>Page Specific Messages</h2>
						'.$html_hints.'
						<p>
							<label for="sl_msg_css">Messages CSS:</label><br />
							<textarea name="sl_msg_css" id="sl_msg_css" cols="20" rows="5" class="code sl_text_area">'.$this->g('sl_msg_css').'</textarea>
						</p>
						<p>
							<label for="sl_login_msg">Login Page Message:</label><br />
							<textarea name="sl_login_msg" id="sl_login_msg" cols="20" rows="5" class="code sl_text_area">'.$this->g('sl_login_msg').'</textarea>
						</p>
						<p>
							<label for="sl_logout_msg">Logout Page Message:</label><br />
							<textarea name="sl_logout_msg" id="sl_logout_msg" cols="20" rows="5" class="code sl_text_area">'.$this->g('sl_logout_msg').'</textarea>
						</p>
						<p>
							<label for="sl_reg_msg">Registration Page Message:</label><br />
							<textarea name="sl_reg_msg" id="sl_reg_msg" cols="20" rows="5" class="code sl_text_area">'.$this->g('sl_reg_msg').'</textarea>
						</p>
						<p>
							<label for="sl_pass_msg">Forgot Password Page Message:</label><br />
							<textarea name="sl_pass_msg" id="sl_pass_msg" cols="20" rows="5" class="code sl_text_area">'.$this->g('sl_pass_msg').'</textarea>
						</p>
						<br />
						<h2>Error Messages</h2>
						<p>
							<label for="sl_login_error_loggedout">Logged Out:</label><br />
							<input type="text" name="sl_login_error_loggedout" id="sl_login_error_loggedout" class="sl_text_box" value="'.$this->g('sl_login_error_loggedout').'" />
						</p>
						<p>
							<label for="sl_login_error_registerdiabled">Registration Disabled:</label><br />
							<input type="text" name="sl_login_error_registerdiabled" id="sl_login_error_registerdiabled" class="sl_text_box" value="'.$this->g('sl_login_error_registerdiabled').'" />
						</p>
						<p>
							<label for="sl_login_error_confirm">Confirmation Email:</label><br />
							<input type="text" name="sl_login_error_confirm" id="sl_login_error_confirm" class="sl_text_box" value="'.$this->g('sl_login_error_confirm').'" />
						</p>
						<p>
							<label for="sl_login_error_newpass">New Password:</label><br />
							<input type="text" name="sl_login_error_newpass" id="sl_login_error_newpass" class="sl_text_box" value="'.$this->g('sl_login_error_newpass').'" />
						</p>
						<p>
							<label for="sl_login_error_registered">Reg. Complete / Check Mail:</label><br />
							<input type="text" name="sl_login_error_registered" id="sl_login_error_registered" class="sl_text_box" value="'.$this->g('sl_login_error_registered').'" />
						</p>
						<p><input type="submit" value="Save  &raquo;"></p>
					</div>
				</form>
				
			');		
		}
	} // end function admin_page
} // end class

// Instantiate login_style class
$ls = new smithers_login();

?>