<?php
/*
Plugin Name: TypoFixer
Plugin URI: http://thespellchecker.com
Description: A simple tool to fix typos from a list of suspect words on your website. To get a list, email me asafmalin at gmail or run script as detailed on readme file.
Author: asafma
Version: 1.0
Author URI: https://www.linkedin.com/in/asaf-malin-018b9a10b/
*/

#call DB installation functions on plugin activation
register_activation_hook( __FILE__, 'tpfx_install' );
register_activation_hook( __FILE__, 'tpfx_install_data' );

#define current version
global $tpfx_db_version;
$tpfx_db_version = '3.1';

#installation: create db table and its version as option, update old version
function tpfx_install() {
	global $wpdb;
	global $tpfx_db_version;
	$table_name = $wpdb->prefix . 'typos';
	$charset_collate = $wpdb->get_charset_collate();
	$sql = "CREATE TABLE $table_name (
		id mediumint(9) NOT NULL AUTO_INCREMENT,
		typo tinytext NOT NULL,
		correction text NOT NULL,
		context text DEFAULT '' NOT NULL,
		longcontext text DEFAULT '' NOT NULL,
		postlink varchar(400) DEFAULT '' NOT NULL, 
		donechange varchar(55) DEFAULT '' NOT NULL,
		PRIMARY KEY  (id)
	) $charset_collate;";

	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	dbDelta( $sql );
	add_option( 'tpfx_db_version', $tpfx_db_version );
	
	global $wpdb;
	$installed_ver = get_option( "tpfx_db_version" );

	if ( $installed_ver != $tpfx_db_version ) {
		$table_name = $wpdb->prefix . 'typos';
		$sql = "CREATE TABLE $table_name (
		id mediumint(9) NOT NULL AUTO_INCREMENT,
		typo tinytext NOT NULL,
		correction text NOT NULL,
		context text DEFAULT '' NOT NULL,
		longcontext text DEFAULT '' NOT NULL,
		postlink varchar(400) DEFAULT '' NOT NULL, 
		donechange varchar(55) DEFAULT '' NOT NULL,
		PRIMARY KEY  (id)
	) $charset_collate;";

	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	dbDelta( $sql );

	update_option( "tpfx_db_version", $tpfx_db_version );
}	
}

#check db version
function tpfx_update_db_check() {
    global $tpfx_db_version;
    if ( get_site_option( 'tpfx_db_version' ) != $tpfx_db_version ) {
        tpfx_install();
    }
}
#run version check on plugin load
add_action( 'plugins_loaded', 'tpfx_update_db_check' );

#initial data is empty table so this table deletes old table content on activation
function tpfx_install_data() {
	global $wpdb;
	$results = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}typos", ARRAY_A );
	$table_name = $wpdb->prefix . 'typos';
	for ($row = 0; $row < sizeof($results); $row++) {
	$id=$results[$row][id];
	$wpdb->delete($table_name,array('id'=>$id));}
}


#add plugin button to admin side menu
add_action('admin_menu', 'tpfx_setup_menu');

function tpfx_setup_menu(){
        add_menu_page( 'Fix typos', 'Fix typos', 'manage_options', 'fix-typos', 'tpfx_init' );
}

#setting plugin page
function tpfx_init(){
#handle user input
        tpfx_handle_post();
        tpfx_handle_upload();
#General explanation about the plugin and a form to upload file
?>
		<h1>Fix typos in one click!</h1>
		<h2><br/>The scanned link gives its typos and typos of other posts the crawler found, up to 1MB. 
		<br/>Download the list and upload here to correct in one click.<br/>Waiting for feedback: asafmalin at gmail.</h2>
	<form name="contactform" method="post" action="https://meaning.wiki/send_form_email.php">
<br />Link to scan <br />
<input  type="text" name="link" maxlength="500" size="100">
<input type="submit" value="Submit"> 
</form>
	
		<form method="post" enctype="multipart/form-data">
  <div class="file-upload">
    <div class="file-select">
      <input type="file" name="chooseFile" id="chooseFile">
    </div>
  </div>
  <div class="form-group" style="margin-top: 10px;">
    <input type="submit" name="upload" class="form-control btn-warning">
  </div>
</form>
<?php
#prepare replacements list based on db table
global $wpdb;
	   $results = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}typos", ARRAY_A );
for ($row = 0; $row < sizeof($results); $row++) {
	if($results[$row][donechange]!='')continue;
	echo esc_html($results[$row][longcontext]);
#for each typo row, show form to correct or dismiss
	?>
	<form  method="post">
		   <?php echo '# '.($row+1) ?>
				<a href="<?php echo esc_url($results[$row][postlink]) ?>">link</a>
				<input  type="hidden" name="postid" value="<?php echo esc_url($results[$row][postlink]) ?>"></input>
				<input  type="hidden" name="context" value="<?php echo esc_html($results[$row][context]) ?>"></input>
				<input  type="text" name="find" value="<?php echo esc_html($results[$row][typo]) ?>" maxlength="50" size="15"></input>
				<input  type="text" name="replace" value="<?php echo esc_html($results[$row][correction]) ?>" maxlength="50" size="15"></input>
				<input type="submit" name="submit" id="submit" value="replace" />  
				<input type="submit" name="dismiss" id="submit" value="dismiss" />  
    </form>
	<?php
}
}
 
function tpfx_handle_post(){
	#case of submit button clicked
        if(isset($_POST['submit'])){ 
		$context=sanitize_text_field($_POST['context']);
		$id=url_to_postid(esc_url_raw($_POST["postid"])); 
		if ($id==0){echo 'URL is not of a valid post';return;}
		$post = get_post($id); 
		$oldpost=apply_filters('the_content', $post->post_content); 
		$replace=sanitize_text_field($_POST["replace"]);
		$find=sanitize_text_field($_POST["find"]);		
		if($replace==''){echo '<br/>Missing replacement for '.$find;return;}
		$newcontext = str_replace( $find, $replace, $context);
		if($newcontext==$context){echo esc_html($find).' not on context line';return;}
		$newpost=str_replace($context, $newcontext, $oldpost);
		if($newpost==$oldpost){echo 'Could not find context line: '.esc_html($context).' on post id '.esc_html($id);return;}
		#prepare post for db
		$my_post = array(
        	'ID' => ($id),
        	'post_content' => ($newpost),);
		#Update the post into the database
		wp_update_post( $my_post );
		#update typos table about action taken
		global $wpdb;
		$table_name = $wpdb->prefix . 'typos';
		$wpdb->update($table_name,array('donechange' => sanitize_text_field($replace)),array('context' => sanitize_text_field($context)));	
		echo '<br/>'.esc_html($find).'==>'.esc_html($replace).' was corrected';		
        }
	    #case of dismiss button clicked
        if(isset($_POST['dismiss'])){
		global $wpdb;			
		$table_name = $wpdb->prefix . 'typos';
		$replace=sanitize_text_field($_POST["replace"]);
		$find=sanitize_text_field($_POST["find"]);
		$wpdb->update($table_name,array('donechange' => 'dismissed'),array('typo' => $find));
		echo '<br/>'.esc_html($find).'->'.esc_html($replace).' was dismissed';}
}

function tpfx_handle_upload(){
        if(isset($_POST['upload'])){
        $file = $_FILES['chooseFile']['name'];
        $filetype = wp_check_filetype($file);
        if($filetype['ext']!='txt'&&$filetype['ext']!='csv'){
        echo 'Wrong file type, should be .txt or .csv'; return;} 
        $file_data = $_FILES['chooseFile']['tmp_name'];
        $handle = fopen($file_data, "r");
        $c = 0;
        while(($filesop = fgetcsv($handle, 1000, ",")) !== false){
          $data = array(
            'typo' => sanitize_text_field($filesop[0]),
            'correction' => sanitize_text_field($filesop[1]),
            'postlink' => esc_url_raw($filesop[2]),
            'context' => sanitize_text_field($filesop[3]),
            'longcontext' => sanitize_text_field($filesop[4]),
          );
		  global $wpdb;
		  $table_name = $wpdb->prefix . 'typos';
          $wpdb->insert( $table_name , $data );
        }
    }	
}
?>
