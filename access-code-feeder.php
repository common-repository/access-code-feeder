<?php
/**
 * Plugin Name: Access Code Feeder
 * Description: Feeds unique access codes to chat bots, merchant services, etc.
 * Author: Alexey Trofimov
 * Version: 1.0.3
 * License: GPLv2
 * Text Domain: access-code-feeder
 * Domain Path: /languages
 */

 
class Access_Code_Feeder_Plugin {
	function __construct() {
		add_action( 'init', array( $this, 'init' ) );
		add_action( 'plugins_loaded', array( $this, 'load_plugin_textdomain' ) );
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'admin_init', array( $this, 'admin_init' ) );
		add_action('wp_ajax_base_url_action', array( $this, 'base_url_action' )); //admin base_url_action
		add_action('wp_ajax_nopriv_feeder_action', array( $this, 'feeder_action' )); //user base_url_action
		add_action('wp_ajax_feeder_action', array( $this, 'feeder_action' )); //admin base_url_action	
//		add_action('admin_enqueue_scripts', array($this,'enqueue_scripts_and_styles')); //admin scripts and styles
//		add_action('wp_enqueue_scripts', array($this,'enqueue_scripts_and_styles')); //public scripts and styles	
		add_action( 'parse_request', array($this,'serve_feeder') );		
		add_shortcode('FEEDER_MANAGER', array( $this, 'get_html_feeders') );
		add_filter( "plugin_action_links_" . plugin_basename(  __FILE__ ), array( $this,'add_settings_link') );

	}

	function serve_feeder(){
		global $wpdb; 
		$db_prefix = $wpdb->prefix;
		$table_name = $wpdb->prefix . 'acf_Feeders';
		$base_path = '/'.get_option('access-code-feeder-base-url','feeder').'/';
		if( strpos($_SERVER["REQUEST_URI"],$base_path) === 0 ) { //our url
			header("X-Robots-Tag: noindex, nofollow", true);
			$s_url = str_replace($base_path,'',$_SERVER["REQUEST_URI"]);
			$r_url = trim($s_url,'/'); //remove last slash if present
			$url_params = explode('/',$r_url);
			$feeder_key = $url_params[0];
			$sql_rows = $wpdb->prepare("SELECT `id`,`key`,`wp_owner_id`,`content`,`options` FROM $table_name WHERE `key` = %s ",array($feeder_key));
			$rows = $wpdb->get_results($sql_rows);
			if($rows === FALSE){wp_die('Database Error on Feeder Fetch');}
			if(!isset($rows[0])){die('');} //key is not in the DB - simulate empty feeder
			$row = $rows[0];
			$id = $row->id;
			$content = $row->content;
			$wp_owner_id = $row->wp_owner_id;
			$a_options = json_decode($row->options,true);
			$a_codes = explode(PHP_EOL,$content);
			$count_codes = count($a_codes);
			if($a_codes[0] == ''){ //explode on empty string still returns array
				$count_codes = 0;
			}
			if(count($url_params) === 1){
				if($count_codes > 0){
					if($a_options['feeder_mode'] == 'TR'){
						if( ($count_codes - 1) == ($a_options['feeder_limit']) ){ //to prevent triggering on 0 several times
							$this->notify_feeder_owner_on_low_count($wp_owner_id,$a_options['feeder_name'],$a_options['feeder_location'],$a_options['feeder_limit']);
						}					
						$top_item = array_shift($a_codes);
						echo(stripslashes($top_item));  
						$content = implode(PHP_EOL,$a_codes);
					}//TR
					if($a_options['feeder_mode'] == 'RR'){
						$rand_key = array_rand($a_codes, 1);
						$rand_item = $a_codes[$rand_key];
						if( ($count_codes - 1) == ($a_options['feeder_limit']) ){ //to prevent triggering on 0 several times
							$this->notify_feeder_owner_on_low_count($wp_owner_id,$a_options['feeder_name'],$a_options['feeder_location'],$a_options['feeder_limit']);
						}						
						unset( $a_codes[$rand_key]);
						echo(stripslashes($rand_item));  
						$content = implode(PHP_EOL,$a_codes);
					}//RR	
					if($a_options['feeder_mode'] == 'RK'){
						$rand_key = array_rand($a_codes, 1);
						$rand_item = $a_codes[$rand_key];
						echo(stripslashes($rand_item));  
						$content = implode(PHP_EOL,$a_codes);
					}//RR					
					$sql_update = $wpdb->prepare("UPDATE $table_name SET `content` = %s WHERE `id` = %d",array($content,$id));
					if($wpdb->query($sql_update) === FALSE){wp_die('Database Error updating Feeder' );}	
				}else{
					echo(""); //no items left
				}
				exit(); 
			}else{//url has extra parts
				if($url_params [1] == 'COUNT'){
					if(strlen($a_codes[0]) > 0){
						echo($count_codes); 
					}else{
						echo('0');
					}
					exit();
				}
			}
			exit(); //we always exit here	
		}//our URL
		
	}//serve_feeder

	function add_settings_link( $links ) {
		$img = '<img style="vertical-align: middle;width:24px;height:24px;border:0;" src="'. plugin_dir_url( __FILE__ ) . 'images/icon1.png'.'"></img>';	
		$settings_link = '<a href="' . admin_url('/options-general.php?page=access-code-feeder') . '">' . $img . __( 'Settings' ) . '</a>';
		array_unshift($links , $settings_link);	
		return $links;
	}//add_settings_link()

	
	function notify_feeder_owner_on_low_count($wp_owner_id,$feeder_name,$feeder_edit_url,$feeder_limit){
		$user_info = get_userdata($wp_owner_id);
		$current_locale = get_locale(); //gotta switch back  after email is sent
		$feeder_owner_locale = get_user_locale($wp_owner_id); //gotta send email in language of bond owner
		switch_to_locale($feeder_owner_locale);
	
		$user_name = $user_info->display_name;
		$user_email = $user_info->user_email;
		$site_title = get_bloginfo('name');
		$site_email = get_bloginfo('admin_email');
		$site_description = get_bloginfo('description');
		$site_url = get_bloginfo('url');
		$email_text = __(" 	Hello %%USERNAME%%<br/>
		Feeder '%%FEEDERNAME%%' now has %%CODECOUNT%% access codes left.<br/>
		You've received this email because the Feeder is <a href='%%CONFIGURL%%'>configured</a> to notify the owner on low content count. <br/>
		<br/>		
		Have a nice day!<br/>
		<a href='%%SITEURL%%'>%%SITENAME%%</a> 
		", 'access-code-feeder' );
		$email_text = str_replace('%%USERNAME%%',$user_name,$email_text);
		$email_text = str_replace('%%FEEDERNAME%%',$feeder_name,$email_text);
		$email_text = str_replace('%%CODECOUNT%%',$feeder_limit,$email_text);
		$email_text = str_replace('%%CONFIGURL%%',$feeder_edit_url,$email_text);
		$email_text = str_replace('%%SITENAME%%',$site_description,$email_text);
		$email_text = str_replace('%%SITEURL%%',$site_url,$email_text);
		$email_subject = __("Feeder '%%FEEDERNAME%%' requires attention !", 'access-code-feeder' );
		$email_subject = str_replace('%%FEEDERNAME%%',$feeder_name,$email_subject);
		
		$headers = array(
			'content-type: text/html', //must have
		);
		add_filter( 'wp_mail_from_name', array( $this, 'email_replace_name_from' ));  
		wp_mail( $user_email, $email_subject, $email_text , $headers );
		remove_filter( 'wp_mail_from_name', array( $this, 'email_replace_name_from' )); 	
	
		switch_to_locale($current_locale); //return to normal
	}//notify_feeder_owner_on_low_count	

	function email_replace_name_from($from_name){
		return get_bloginfo('name'); //replace "WordPress" to site name. Headers do not work for some servers
	}	
	
	function init() {
		load_plugin_textdomain( 'access-code-feeder', FALSE, basename( dirname( __FILE__ ) ) . '/languages' );
	}
	
	function load_plugin_textdomain(){
//		load_plugin_textdomain( 'access-code-feeder', FALSE, basename( dirname( __FILE__ ) ) . '/languages' );
	}	

	function prepare_db_tables(){
		global $wpdb; 
		$db_prefix = $wpdb->prefix;
		$charset_collate = $wpdb->get_charset_collate();
		$create_table_feeders_sql = "CREATE TABLE IF NOT EXISTS `".$db_prefix."acf_Feeders` (
			`id` INT(11) NOT NULL AUTO_INCREMENT,
			`wp_owner_id` INT(11) unsigned NOT NULL,	
			`key` VARCHAR(100) NOT NULL DEFAULT 1,
			`options` VARCHAR(4096) NOT NULL DEFAULT '',
			`content` MEDIUMTEXT NOT NULL DEFAULT '',
			`created` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (`id`),
			UNIQUE KEY `feeder_key` (`key`)
		)";
 	

		$wpdb->show_errors();
		if($wpdb->query($create_table_feeders_sql) === FALSE){wp_die();}
	}//prepare_db_tables

	

	function admin_init() {
		register_setting( 'Access_Code_Feeder_Options', 'access-code-feeder-base-url' );
		$this->prepare_db_tables();
	}

	function admin_menu() {
		add_options_page( __( 'Access Code Feeder', 'access-code-feeder' ), __( 'Access Code Feeder', 'access-code-feeder' ), 'manage_options', 'access-code-feeder', array( $this, 'render_options' ) );
	}
	
	public function enqueue_scripts_and_styles(){
		wp_enqueue_style('messagebox-css', plugin_dir_url(__FILE__) . 'assets/messagebox.css');
		wp_enqueue_script( 'messagebox-js', plugin_dir_url(__FILE__) . 'assets/messagebox.js');
		wp_enqueue_style('access-code-feeder-css', plugin_dir_url(__FILE__) . 'assets/accesscodefeeder.css');
		wp_enqueue_script('access-code-feeder-js', plugin_dir_url(__FILE__) . 'assets/accesscodefeeder.js');
	}//enqueue_scripts_and_styles()

	public function bootstrap_scripts_and_styles(){	
		wp_register_style( 'bootstrap.min', plugin_dir_url(__FILE__) . 'assets/bootstrap.min.css' );
		wp_enqueue_style('bootstrap.min');
		wp_register_script( 'bootstrap.min', plugin_dir_url(__FILE__) . 'assets/bootstrap.min.js' );
		wp_enqueue_script('bootstrap.min');	
	}//bootstrap_scripts_and_styles()


	function render_options() {
		echo('<h2>');
		_e( 'Access Code Feeder', 'access-code-feeder' );
		echo('</h2>');
		echo( '<p>' );
		_e( 'Use Feeders to pass unique access code to chat bots, merchant software, etc.', 'access-code-feeder' ) ; 
		echo( ' ' );
		_e( '<a target=_new href="https://www.youtube.com/watch?v=v0WAz8OyY28&list=PLRv0B44q8TR8bWrEwtMd6e17oW8wdRVIv&index=4">Watch Video</a>', 'access-code-feeder' ) ; 
		echo( '</p>' );
		$this->render_base_url();
		$this->render_feeders();
	
	}
	
	function render_base_url() {
		echo('<div class="panel panel-default wrap wrap_base_url_section">');
			echo('<div class="panel-heading">');
				echo( '<h3>' . __( 'Base URL', 'access-code-feeder' ) . '</h3>'); 			
			echo('</div>');//panel-heading
			echo('<div class="panel-body">');		
				echo('<h4 style="display:inline;" class="wrap wrap_base_url">'.__('just a second...', 'access-code-feeder').'</h4>');
			echo('</div>');	//panel-body
		echo('</div>'); //panel
		echo($this->admin_action_js());	
	}

	function get_html_feeders() {
		$this->bootstrap_scripts_and_styles();
		$this->enqueue_scripts_and_styles();
		$must_be_user = '<hr>' . __('Please <a href="/wp-login.php?action=register">Register</a> or <a href="/wp-login.php">Login</a> to access this page !', 'access-code-feeder');
		if(!is_user_logged_in()){
			return($must_be_user); //we done
		}	
		$ret = '';
		$ret .= '<div class="panel panel-default wrap wrap_feeders_section">';
			$ret .= '<div class="panel-heading">';
				$ret .= '<h3>';
					$ret .=  '' . __( 'Feeders', 'access-code-feeder' ) . ''; 
					$ret .= '&nbsp;<button type="button" data-toggle="tooltip" title="'.__( 'Create new Feeder', 'access-code-feeder' ).'" class="btn btn-lg btn-success add_feeder"><span class="glyphicon glyphicon-plus"></span>&nbsp;'.__( 'New Feeder', 'access-code-feeder' ).'</button>';
					$ret .= '&nbsp;<button type="button" data-toggle="tooltip" title="'.__( 'Refresh the list of Feeders', 'access-code-feeder' ).'" class="btn btn-lg btn-info refresh_list"><span class="glyphicon glyphicon-refresh"></span>&nbsp;'.__( 'Refresh List', 'access-code-feeder' ).'</button>';
				$ret .= '<h3>';
			$ret .= '</div>'; //panel-heading
			$ret .= '<div class="panel-body">';
				$ret .= '<table class="table table_feeders table-hover table-condensed">';
					$ret .= '<thead><tr>';	
					$ret .= '<td><h4>'.__('Feeder Name', 'access-code-feeder').'</h4></td>';	
					$ret .= '<td><h4>'.__('Mode', 'access-code-feeder').'</h4></td>';						
					$ret .= '<td><h4>'.__('Items', 'access-code-feeder').'</h4></td>';	
					$ret .= '<td><h4>'.__('Actions', 'access-code-feeder').'</h4></td>';					
					$ret .= '</tr></thead>';		
					$ret .= '<tbody class="table_feeders_body">';	
					$ret .= $this->get_feeder_rows(100);
					$ret .= '</tbody>';				
				$ret .= '</table>';			
			$ret .= '</div>';	// panel-body		
		$ret .= '</div>';	//panel
		$ret .= '<div class="feeders-global-loader"></div>';
		$ret .= $this->feeders_action_js();
		return($ret);
	}	
	
	
	function render_feeders() {
		echo($this->get_html_feeders());
	}	
	
	function base_url_action(){ //ajax!
		if ( isset($_REQUEST) ) {
			$new_url = $_REQUEST['new_url'];
			update_option('access-code-feeder-base-url', $new_url);
			echo(get_option('access-code-feeder-base-url','feeder'));
		}
		wp_die();			
	}
	
	function feeder_action(){ //ajax!
		global $wpdb; 
		$table_name = $wpdb->prefix . 'acf_Feeders';
//wp_die('MSG' . print_r($_REQUEST,true)); //testing
		if ( isset($_REQUEST) ) {
			$command = ''; 	$param0 = ''; $param1 = ''; $param2 = '';
			if ( isset($_REQUEST['command']) )	$command = sanitize_text_field($_REQUEST['command']); //type of operation	
			if ( isset($_REQUEST['param0']) )	$param0 = sanitize_text_field($_REQUEST['param0']); //feeder_id, $feeder_name
			if ( isset($_REQUEST['param1']) )	$param1 = wp_strip_all_tags($_REQUEST['param1']); //save feeder content

			if($command == 'DELETEFEEDER'){
				$feeder_id = $param0;
				$feeder_owner = get_current_user_id();
				$sql_delete = $wpdb->prepare("DELETE FROM $table_name WHERE `wp_owner_id` = %d AND `id` = %d",array($feeder_owner,$feeder_id));
				if($wpdb->query($sql_delete) === FALSE){wp_die('MSG Database Error on deleting Feeder');}	
				wp_die("#feeder-row-".$feeder_id); //we done
			}//DELETEFEEDER		
			if($command == 'REFRESHLIST'){
				wp_die($this->get_feeder_rows(100)); //we done
			}//REFRESHLIST		
			if($command == 'FETCHCONTENT'){
				$feeder_id = $param0;
				$feeder_owner = get_current_user_id();
				$sql_content = $wpdb->prepare("SELECT `content` FROM $table_name WHERE `wp_owner_id` = %d AND `id` = %d",array($feeder_owner,$feeder_id));
				$feeder_content = stripslashes($wpdb->get_var($sql_content));
				if($feeder_content === FALSE){wp_die('MSG Database Error on fetching Feeder Content');}	
				wp_die(''.$feeder_content); //we done
			}//FETCHCONTENT				
			if($command == 'SAVECONTENT'){
				$feeder_id = $param0;
				$feeder_content = $param1;
				$a_content = explode("\n",$feeder_content);
				$a_content_filtered = array();
				for($i=0; ($i < count($a_content)) && ($i < 1000); $i++){
					$item = trim($a_content[$i]);
					if( (strlen($item) >= 1) && (strlen($item) <= 100)){
						array_push($a_content_filtered,$item);
					}
				}
				$feeder_content = implode(PHP_EOL,$a_content_filtered);
				$feeder_owner = get_current_user_id();
				$sql_content = $wpdb->prepare("UPDATE $table_name SET `content` = %s WHERE `wp_owner_id` = %d AND `id` = %d",array($feeder_content,$feeder_owner,$feeder_id));
				if($wpdb->query($sql_content) === FALSE){wp_die('MSG Database Error on saving Feeder Content');}
				wp_die(); //we done
			}//SAVECONTENT		
			if($command == 'FETCHSETTINGS'){
				$feeder_id = $param0;
				if($feeder_id < 0) { //it's new Fer - no id yet
					wp_die(''); //nothing to fetch
				}
				$feeder_owner = get_current_user_id();
				$sql_options = $wpdb->prepare("SELECT `options` FROM $table_name WHERE `wp_owner_id` = %d AND `id` = %d",array($feeder_owner,$feeder_id));
				$feeder_options = ($wpdb->get_var($sql_options));
				if($feeder_options === FALSE){wp_die('MSG Database Error on fetching Feeder Content');}	
				wp_die($feeder_options); //we done
			}//	FETCHSETTINGS
			if($command == 'SAVESETTINGS'){
				$json_options = stripslashes($param0); //get_magic_quotes_gpc()?
				$a_options = json_decode($json_options,true);
				$feeder_owner = get_current_user_id();
				$feeder_id = sanitize_text_field($a_options['feeder_id']);
				$safe_options = array();
				foreach($a_options as $option_key => $option_val) {
					$safe_options[$option_key]	= trim(sanitize_text_field(str_replace(array("\\","'",'"'),array(' ','`','`'),$option_val)));
				}
				$db_options = json_encode($safe_options);
//echo("MSG $feeder_id ");wp_die(print_r($safe_options,true)); //test				
				if($feeder_id < 0){ //create new
					$feeder_key =  md5(rand());
					$feeder_content = "Test Access Code 1" . PHP_EOL . "Test Access Code 2" . PHP_EOL . "Test Access Code 3";
					$sql_count = $wpdb->prepare("SELECT COUNT(*) as feeders from $table_name WHERE `wp_owner_id` = %d",array($feeder_owner));
					$count_feeders = $wpdb->get_var($sql_count);
					if($count_feeders > 100){
						wp_die('MSG' . __('Too many Feeders', 'access-code-feeder') );
					}
					$sql_add = $wpdb->prepare("INSERT IGNORE INTO $table_name (`wp_owner_id`,`options`,`key`,`content`) VALUES (%d,%s,%s,%s)",array($feeder_owner,$db_options,$feeder_key,$feeder_content));
					if($wpdb->query($sql_add) === FALSE){wp_die('MSG Database Error on adding new Feeder');}	
					$ret = $this->get_feeder_rows(1); //last one
					wp_die($ret); //we done
				}else{ //update old
					$sql_add = $wpdb->prepare("UPDATE $table_name SET `options` = %s WHERE `wp_owner_id` = %d AND `id` = %d",array($db_options,$feeder_owner,$feeder_id));
					if($wpdb->query($sql_add) === FALSE){wp_die('MSG Database Error on updating Feeder');}	
					$ret = $this->get_feeder_rows(1); //last one
					wp_die($ret); //we done
				}
				wp_die(''.$feeder_options); //we done			
			}//SAVESETTINGS
			
			wp_die('MSG Unknown command "' . $command . '"'); //if we are here - command is nnot recognized
		}
		wp_die('MSG No params');		
	}	
	
	function get_feeder_rows($limit){
		global $wpdb; 
		$table_name = $wpdb->prefix . 'acf_Feeders';
		$feeder_owner = get_current_user_id();
		$owner_sql = "`wp_owner_id` = %d";
		if(is_admin() && in_array('administrator',  wp_get_current_user()->roles)){
			$extra_sql = "OR 1";
			$limit = 99999;
		}
		$sql_rows = $wpdb->prepare("SELECT `id`, `wp_owner_id`, `options`,`key`, LENGTH( TRIM(`content`)) as `total_len`, ( LENGTH(`content`) - LENGTH(REPLACE(`content`, CHAR(10), '')) ) as line_count   FROM $table_name WHERE (`wp_owner_id` = %d $extra_sql ) ORDER BY `id` DESC LIMIT $limit",array($feeder_owner));
		$rows = $wpdb->get_results($sql_rows);
		if($rows === FALSE){wp_die('MSG Database Error on getting Feeders');}
		$ret = '';
		for($i = 0; $i < count($rows); $i++){
			$row = $rows[$i];
			$line_count = $row->line_count + 1; //last line foes not have newline
			if($row->total_len == 0) //empty
				$line_count = 0; //
			$from_db_options = $row->options;
			$a_options = json_decode($from_db_options,true);
			$feeder_name = $a_options['feeder_name'];
			$ret .= $this->make_feeder_row($row->id,$feeder_name,$a_options['feeder_mode'],$row->key,$line_count,$row->wp_owner_id );
		}
		return($ret);
	}//get_feeder_rows()
	
	function make_feeder_row($id,$name,$mode,$key,$linecount,$wp_owner_id){
		$mode_title = ''; $mode_label = '';
		if($mode == 'TR') {$mode_title = __( 'feed Top item, than Remove it', 'access-code-feeder' ); $mode_label='label-primary';}
		if($mode == 'RR') {$mode_title = __( 'feed Random item, than Remove it', 'access-code-feeder' ); $mode_label='label-success';}
		if($mode == 'RK') {$mode_title = __( 'feed Random item, than Keep it', 'access-code-feeder' ); $mode_label='label-info';}
		
		$feeder_name = $name;
		if( $wp_owner_id != get_current_user_id() ){
			$feeder_name = '(<a href="'.add_query_arg( 'user_id', $wp_owner_id, self_admin_url( 'user-edit.php')).'">' . $wp_owner_id . '</a>)' . $feeder_name;
		}		
		$td1 = "<td class='feeder-name'>" . $feeder_name . "</td>";
		$td2 = "<td><span class='label $mode_label' title='$mode_title' data-toggle='tooltip' >" . $mode . "</span></td>";		
		$td3 = "<td>" . $linecount . "</td>";
		$td4 = "<td nowrap >";
		$td4 .= '<button type="button" data-toggle="tooltip" title="'.__( 'Access Codes', 'access-code-feeder' ).'" class="btn btn-success" onClick="ACF1984S_fetch_codes('.$id.',\''.$name.'\')"><span class="glyphicon glyphicon-edit"></span>&nbsp;'.__( 'Contents', 'access-code-feeder' ).'</button> ';
		$td4 .= '<button type="button" data-toggle="tooltip" title="'.__( 'Feeder URLs', 'access-code-feeder' ).'" class="btn btn-info" onClick="ACF1984S_show_usage(\''.$name.'\',\''.get_site_url().'/'.get_option('access-code-feeder-base-url','feeder').'/'.$key.'\','.$linecount.')"><span class="glyphicon glyphicon-share"></span>&nbsp;'.__( 'Usage', 'access-code-feeder' ).'</button> ';
		$td4 .= '<button type="button" data-toggle="tooltip" title="'.__( 'Settings of the Feeder', 'access-code-feeder' ).'" class="btn btn-warning" onClick="ACF1984S_edit_feeder('.$id.',\''.$name.'\')"><span class="glyphicon glyphicon-cog"></span>&nbsp;'.__( 'Settings', 'access-code-feeder' ).'</button> ';		
		$td4 .= '<button type="button" data-toggle="tooltip" title="'.__( 'Delete Feeder', 'access-code-feeder' ).'" class="btn btn-danger" onClick="ACF1984S_delete_feeder('.$id.',\''.$name.'\')"><span class="glyphicon glyphicon-remove"></span>&nbsp;'.__( 'Delete', 'access-code-feeder' ).'</button> ';		
		$td4 .= "</td>";	
		$ret = "<tr id='feeder-row-".$id."'>" . $td1 . $td2 . $td3 . $td4 . "</tr>";
		return($ret);
	}//make_feeder_row()

	function admin_action_js(){ //returns Js to serve with base_url
		?>
			<script>
			var ACF1984S_site_url = "<?php echo(get_site_url()); ?>";
			var ACF1984S_base_part = "<?php echo(get_option('access-code-feeder-base-url','feeder')); ?>";
			var ACF1984S_feeder_part = "<?php _e('UNIQUE_FEEDER_KEY', 'access-code-feeder'); ?>";

			var ACF1984S_enter_base_url = function(){
				jQuery.MessageBox({
					message	: '<?php _e('Please enter Base Path', 'access-code-feeder'); ?>',
					input	: ACF1984S_base_part,
				    buttonDone  : '<?php _e('Set', 'access-code-feeder'); ?>',
					buttonFail  : '<?php _e('Cancel', 'access-code-feeder'); ?>',

				}).done(function(data,button){
					if (jQuery.trim(data) && (button == 'buttonDone')) {
						var r = data.replace(/[\s`!@#$%^&*()|+\=?;:'",.<>\{\}\[\]\\\/]/gi, '');
						if( (r != ACF1984S_base_part) && (r.length > 0) ){
							jQuery('.wrap_base_url').html("<?php _e('just a second...', 'access-code-feeder'); ?>");
							ACF1984S_show_wait(true);
							jQuery.ajax({
								url : '<?php echo(admin_url('admin-ajax.php'));?>',
								type : 'post',
								data : {
									action : 'base_url_action',
									new_url : r,
								},
								success : function( response ) {
									ACF1984S_base_part = r;
									ACF1984S_show_base_url(true);
									ACF1984S_show_wait(false);
									ACF1984S_refresh_feeders();
								},
								error: function(errorThrown){
									ACF1984S_show_wait(false);
									console.log(errorThrown);							
									alert('<?php _e('Error while saving Base Url', 'access-code-feeder'); ?>');
								}							
							});	//ajax					
						}
					}
					
				});
			}//ACF1984S_enter_base_url()
		
			var ACF1984S_show_base_url = function(show_alert){
				var t = ACF1984S_site_url + '/<a href="javascript:ACF1984S_enter_base_url()" title="<?php _e('Change Path to the Feeder', 'access-code-feeder'); ?>" data-toggle="tooltip" data-placement="bottom" >' + ACF1984S_base_part + '</a>/' + ACF1984S_feeder_part;
				if(show_alert){
					t += '<div class="alert alert-warning alert-dismissible fade in">';
					t += '<a href="#" class="close" data-dismiss="alert" aria-label="close">&times;</a>';
					t += '<strong><?php _e('Please note', 'access-code-feeder'); ?>:</strong>&nbsp;';
					t += '<?php _e('URLs of all your Feeders has been changed. Please adjust your software accordingly!', 'access-code-feeder'); ?>';
					t += '</div>';
				}
				jQuery('.wrap_base_url').html(t);
			}//ACF1984S_show_base_url()
			
			jQuery(document).ready(function() {
				ACF1984S_show_base_url(false);
				ACF1984S_show_wait(false);
			})
			</script>
		<?php
	}	//admin_action_js()

	
	function feeders_action_js(){ //returns Js to server with feeders
		?>
			<script>
			var ACF1984S_delete_feeder = function(acf_id,acf_name){
				var confirm_txt = '<?php _e('Feeder', 'access-code-feeder'); ?>';
				confirm_txt += '\n"' + acf_name + '"\n';
				confirm_txt += '<?php _e('will be permanently deleted!', 'access-code-feeder'); ?>'+"\n\n";
				confirm_txt += '<?php _e('Are you sure?', 'access-code-feeder'); ?>';
				if(confirm(confirm_txt)){
					ACF1984S_show_wait(true);
					jQuery.ajax({
						url : '<?php echo(admin_url('admin-ajax.php'));?>',
						type : 'post',
						data : {
							action : 'feeder_action',
							command : "DELETEFEEDER",
							param0 : acf_id,
						},
						success : function( response ) {
							ACF1984S_show_wait(false);
							if(response.indexOf('MSG') == 0){
								alert(response.substr(3));
							}else{
								jQuery(response).fadeOut(500,function(){jQuery(response).remove();});			
							}
						},
						error: function(errorThrown){
							ACF1984S_show_wait(false);
							console.log(errorThrown);							
							alert('<?php _e('Error deleting Feeder', 'access-code-feeder'); ?>');
						}							
					});	//ajax					
			
				}
			}//ACF1984S_delete_feeder()
			
			var ACF1984S_refresh_feeders = function(acf_id,acf_name){
				ACF1984S_show_wait(true);
				jQuery.ajax({
					url : '<?php echo(admin_url('admin-ajax.php'));?>',
					type : 'post',
					data : {
						action : 'feeder_action',
						command : "REFRESHLIST",
						},
					success : function( response ) {
						ACF1984S_show_wait(false);
						if(response.indexOf('MSG') == 0){
							alert(response.substr(3));
						}else{
							jQuery('.table_feeders_body').html(response);			
						}
					},
					error: function(errorThrown){
						ACF1984S_show_wait(false);
						console.log(errorThrown);							
						alert('<?php _e('Error refreshing Feeders List', 'access-code-feeder'); ?>');
					}							
				});	//ajax					
			}//ACF1984S_refresh_feeders()

			var ACF1984S_fetch_codes = function(acf_id,acf_name){
				ACF1984S_show_wait(true);
				jQuery.ajax({
					url : '<?php echo(admin_url('admin-ajax.php'));?>',
					type : 'post',
					data : {
						action : 'feeder_action',
						command : "FETCHCONTENT",
						param0 : acf_id,
						},
					success : function( response ) {
						ACF1984S_show_wait(false);
						if(response.indexOf('MSG') == 0){
							alert(response.substr(3));
						}else{
							var feeder_content = ''+response;
							var w_width = jQuery(window).width();
							var ta_width = Math.round(w_width / 2);
							var tas = "min-width:"+ta_width+"px;width:100%;margin-top:1rem;white-space:pre;overflow:scroll;";
							var textArea = jQuery("<textarea rows='4' style='"+tas+"' placeholder='<?php _e('Paste Access Codes here', 'access-code-feeder'); ?>' />");							
							textArea.val(feeder_content);							
							var wrapInput = jQuery("<div class='acf-content-wrap' ></div>");
							var msg2 = jQuery("<div class='acf-msg2'><?php _e('Enter one Access Code per line.<br>Lines must be 1-100 chars long, 1000 lines maximum.', 'access-code-feeder'); ?></div>");
							textArea.appendTo(wrapInput);
							msg2.appendTo(wrapInput);
							jQuery.MessageBox({
								message : '<h4><mark><?php _e('Contents of the Feeder', 'access-code-feeder'); ?> "'+acf_name + "\"</mark>\n</h4>" + '<?php _e('Access Codes', 'access-code-feeder'); ?>:',
								input   : wrapInput,
								buttonDone  : {
									one : {
										text    : '<?php _e('Save', 'access-code-feeder'); ?>',
										keyCode : undefined, //so, no 'Enter'
									},
								},
								buttonFail  : '<?php _e('Cancel', 'access-code-feeder'); ?>',								
							}).done(function(data){
								var new_content = data;								
								ACF1984S_show_wait(true);
								jQuery.ajax({
									url : '<?php echo(admin_url('admin-ajax.php'));?>',
									type : 'post',
									data : {
										action : 'feeder_action',
										command : "SAVECONTENT",
										param0 : acf_id,
										param1 : new_content,
										},
									success : function( response ) {
										if(response.indexOf('MSG') == 0){
											ACF1984S_show_wait(false);
											alert(response.substr(3));
										}else{
											ACF1984S_refresh_feeders();			
										}
									},
									error: function(errorThrown){
										ACF1984S_show_wait(false);
										console.log(errorThrown);							
										alert('<?php _e('Error saving Feeder Content', 'access-code-feeder'); ?>');
									}							
								});	//ajax									
							});	//MessageBox done						
						}
					},
					error: function(errorThrown){
						ACF1984S_show_wait(false);
						console.log(errorThrown);							
						alert('<?php _e('Error fetching Feeder Content', 'access-code-feeder'); ?>');
					}							
				});	//ajax					
			}//ACF1984S_fetch_codes()			

			var ACF1984S_show_usage = function(acf_name,acf_url,acf_count){
				var w_width = jQuery(window).width();
				var d_width = Math.round( w_width / 2);
				var ds = "min-width:"+d_width+"px;";
				var dt = "<?php _e('Feeder URL', 'access-code-feeder'); ?>:";
				dt += "<br><span style='white-space:nowrap'>&nbsp;<a class='asf-feeder-url' target=_blank href='"+acf_url+"' >"+acf_url+"</a>&nbsp;</span><br>";
				dt += "<?php _e('When this URL is fetched, Access Code will be presented.', 'access-code-feeder'); ?>";
				dt += "<br>";
				dt += "<?php _e('If no Access Codes left in the Feeder, empty line will be returned.', 'access-code-feeder'); ?>";	
				dt += "<br>";				
				dt += "<?php _e('Using with Chat Bots', 'access-code-feeder'); ?>:";	
				dt += "<br>";		
				dt += "<code>$(urlfetch "+acf_url+")</code>";	
				dt += "<hr>";
				dt += "<?php _e('Feeder Counter URL', 'access-code-feeder'); ?>:";
				dt += "<br><span style='white-space:nowrap'>&nbsp;<a class='asf-feeder-counter-url' target=_blank href='"+acf_url+"/COUNT' >"+acf_url+"/COUNT</a>&nbsp;</span><br>";
				dt += "<?php _e('Counter URL returns number of Access Codes left in the Feeder.', 'access-code-feeder'); ?>";
				
				
				var wrapInput = jQuery("<div style='"+ds+"' class='acf-msg2'>"+dt+"</div>");
				
				jQuery.MessageBox({
					message : '<h4><mark><?php _e('Usage of the Feeder', 'access-code-feeder'); ?> "'+acf_name + "\" </mark>\n</h4>",
					input   : wrapInput,
				}).done(function(data){
					ACF1984S_refresh_feeders();
				});
				
			}//ACF1984S_show_usage()
			
			var ACF1984S_edit_feeder = function(acf_id,acf_name){
				ACF1984S_show_wait(true);
				jQuery.ajax({
					url : '<?php echo(admin_url('admin-ajax.php'));?>',
					type : 'post',
					data : {
						action : 'feeder_action',
						command : "FETCHSETTINGS", 
						param0 : acf_id,
					},
					success : function( response ) {
						ACF1984S_show_wait(false);
						if(response.indexOf('MSG') == 0){
							alert(response.substr(3));
						}else{
							var title = '<?php _e('Create new Feeder', 'access-code-feeder'); ?>'; //new
							var ok_text = '<?php _e('Add Feeder', 'access-code-feeder'); ?>'; //new
							var notify_checked = 'CHECKED'; //or empty
							var notify_limit_disabled = ''; //or DISABLED
							var notify_limit = 0;
							var selTR = '', selRR = '', selRK = '';
							if(acf_id >= 0){ //NOT new feeder
								var title = '<?php _e('Feeder Settings', 'access-code-feeder'); ?>';
								var ok_text = '<?php _e('Save', 'access-code-feeder'); ?>';							
								var op = new Array();
								op = JSON.parse(response);
								if(op.feeder_notify == ''){
									notify_checked = ''; //or empty
									notify_limit_disabled = 'DISABLED'; 
								}
								notify_limit = op.feeder_limit;
								if( op.feeder_mode == 'TR') selTR = 'SELECTED';
								if( op.feeder_mode == 'RR') selRR = 'SELECTED';
								if( op.feeder_mode == 'RK') selRK = 'SELECTED'; 
							}else{
							}
							var wrapInput = jQuery("<div class='acf-content-wrap' ></div>");
							var feederId = jQuery("<input type='hidden' name='feeder_id' value='"+acf_id+"'></input>");
							feederId.appendTo(wrapInput);									
							var wrapName = jQuery("<br><div class='acf-msg2'><?php _e('Feeder Name', 'access-code-feeder'); ?>:<input name='feeder_name' id='ACF1984S_feeder_name' maxlength='30' size='30' id='feeder_name' type='edit' value='"+acf_name+"'></input></div>");
							wrapName.appendTo(wrapInput);
							var mode_sel = "<select name='feeder_mode' id='ACF1984S_feeder_mode'><option value='TR' "+selTR+"><?php _e('[TR] feed Top item, than Remove it', 'access-code-feeder'); ?></option><option value='RR' "+selRR+"><?php _e('[RR] feed Random item, than Remove it', 'access-code-feeder'); ?></option><option value='RK' "+selRK+"><?php _e('[RK] feed Random item, than Keep it', 'access-code-feeder'); ?></option></select>";
							var wrapMode = jQuery("<br><div class='acf-msg2'><?php _e('Feeder Mode', 'access-code-feeder'); ?>:"+mode_sel+"</div>");
							wrapMode.appendTo(wrapInput);
							var note_check = "<input name='feeder_notify' id='ACF1984S_feeder_notify' type='checkbox' "+notify_checked+"  value='note_on'></input>";
							var note_left = "<input name='feeder_limit' id='ACF1984S_feeder_limit' class='ACF1984S_num' type='text' maxlength='3' size='3' value='"+notify_limit+"' "+notify_limit_disabled+" ></input>";
							var wrapNote = jQuery("<br><div class='acf-msg2'>"+note_check+"<?php _e('Notify when feeder contains', 'access-code-feeder'); ?> " + note_left +" <?php _e('elements', 'access-code-feeder'); ?></div>");
							wrapNote.appendTo(wrapInput);
							var decodedloc = decodeURI(window.location).replace(/<\/?[^>]+(>|$)/g, '');
							var refName = jQuery("<input type='hidden' name='feeder_location' value='"+decodedloc+"'></input>");
							refName.appendTo(wrapInput);							
							
							jQuery.MessageBox({
								message : '<h4><mark>'+ title + "</mark>\n</h4>",
								input   : wrapInput,
								buttonDone  : {
									one : {
										customClass         : 'ACF1984S_save',
										text    : ok_text,
										keyCode : undefined, //so, no 'Enter'
									},
								},
								buttonFail  : '<?php _e('Cancel', 'access-code-feeder'); ?>',								
							}).done(function(data){
								var json_settings = JSON.stringify(data);	
console.log(json_settings);								
								ACF1984S_show_wait(true);
								jQuery.ajax({
									url : '<?php echo(admin_url('admin-ajax.php'));?>',
									type : 'post',
									data : {
										action : 'feeder_action',
										command : "SAVESETTINGS",
										param0 : json_settings,
										},
									success : function( response ) {
										ACF1984S_show_wait(false);
										if(response.indexOf('MSG') == 0){
											alert(response.substr(3));
										}else{
											ACF1984S_refresh_feeders();			
										}
									},
									error: function(errorThrown){
										ACF1984S_show_wait(false);
										console.log(errorThrown);							
										alert('<?php _e('Error Saving Feeder', 'access-code-feeder'); ?>');
									}							
								});	//ajax	
							})//MBox done
						}
					},
					error: function(errorThrown){
						ACF1984S_show_wait(false);
						console.log(errorThrown);							
						alert('<?php _e('Error deleting Feeder', 'access-code-feeder'); ?>');
					}							
				});	//ajax					
			}//ACF1984S_edit_feeder()			
			
			jQuery(document).ready(function() {
				jQuery('[data-toggle="tooltip"]').tooltip();   
				jQuery(".add_feeder").click(function(){
					//ACF1984S_add_feeder();
					var event = new Date();
					ACF1984S_edit_feeder(-1,"<?php _e('Feeder', 'access-code-feeder'); ?>" + ' ' + event.toLocaleDateString() + ' ' + event.toLocaleTimeString()); 
				}); 
				jQuery(".refresh_list").click(function(){
					ACF1984S_refresh_feeders();
				}); 	
				
				ACF1984S_show_wait(false);
		
			}) //document ready
			
			jQuery(document).on('change keyup paste', '.ACF1984S_num',function () {
				var s = jQuery(this).val();
				var n = s.replace(/[^0-9]/g,'');
				jQuery(this).val(n);	
				if(n.length == 0)
				{
					jQuery(this).val('0');
				}
				jQuery(this).val(parseInt(jQuery(this).val()));	
			});	//ACF1984S_num on			
			
			jQuery(document).on('change', '#ACF1984S_feeder_notify',function () {
				var isChecked = jQuery(this).prop('checked');
				if(isChecked){
					jQuery('#ACF1984S_feeder_limit').removeAttr('disabled');
				}else{
					jQuery('#ACF1984S_feeder_limit').attr('disabled','disabled');   
				}
			})
			
			jQuery(document).on('change keyup paste', '#ACF1984S_feeder_name',function () {
				var r = jQuery(this).val().trim().substr(0,30).replace(/['"]/gi, '');
				var len = r.length;
				if(len > 0){
					jQuery('.ACF1984S_save').removeAttr('disabled');
				}else{
					jQuery('.ACF1984S_save').attr('disabled','disabled');   
				}
			})			
			</script>
		<?php
	}//feeders_action_js()
} //class
$GLOBALS['access_code_feeder'] = new Access_Code_Feeder_Plugin;

function ACF1984S_delete_plugin_database_table(){
    global $wpdb;
    $table_name = $wpdb->prefix . 'acf_Feeders';
    $sql = "DROP TABLE IF EXISTS $table_name";
    $wpdb->query($sql);
}

register_uninstall_hook(__FILE__, 'ACF1984S_delete_plugin_database_table');
