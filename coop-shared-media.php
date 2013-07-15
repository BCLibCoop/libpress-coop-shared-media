<?php defined('ABSPATH') || die('No direct access' );

/**
 * @package Coop Shared Media
 * @copyright BC Libraries Coop 2013
 *
 **/
/**
 * Plugin Name: Coop Shared Media
 * Description: Central media and pages repository interface.  NETWORK ACTIVATE.
 * Author: Erik Stainsby, Roaring Sky Software
 * Author URI: http://roaringsky.ca/plugins/coop-shared-media/
 * Version: 0.1.0
 
 **/
 
 
/**
*
*	Load developer assist class if not loaded
*		- provides standard WP table features out of the box
**/
if(!class_exists('WP_List_Table')){
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}


/**
*
*	Our Network Shared Media driver class
*
**/
 
if ( ! class_exists( 'Coop_Shared_Media' )) :
	
class Coop_Shared_Media {

	public function __construct() {
		add_action( 'init', array( &$this, '_init' ));
	}

	public function _init() {
			
		if( is_admin()) {
			
			add_action( 'network_admin_menu', array(&$this,'add_network_admin_page' )); 		
			add_action( 'admin_enqueue_scripts', array( &$this, 'admin_enqueue_styles_scripts' ));
			add_action( 'admin_menu', array( &$this,'add_coop_shared_media_page' ));
			add_action( 'add_meta_boxes', array( &$this,'add_nsm_metaboxen' ));
			add_action( 'save_post',array(&$this,'coop_nsm_shared_text_save_post'));
			add_action( 'wp_ajax_coop-nsm-fetch-preview', array( &$this, 'coop_nsm_fetch_preview_callback' ));
			add_action( 'wp_ajax_coop-nsm-fetch-shared-images', array( &$this, 'coop_nsm_fetch_shared_images_callback' ));
			add_action( 'wp_ajax_coop-nsm-fetch-image-metadata', array( &$this, 'coop_nsm_fetch_image_metadata_callback' ));
		}
		
		add_shortcode( 'coop-nsm-shared-text', array(&$this, 'coop_nsm_shared_text_shortcode_handler'));
	}
	
	public function frontside_enqueue_styles_scripts($hook) {
		
	//	error_log(__CLASS__.'::'.__FUNCTION__ .' '.$hook);
		
	//	wp_enqueue_style( 'coop-nsm' );
	//	wp_enqueue_script( 'coop-nsm-js' );
	}
	
	public function admin_enqueue_styles_scripts($hook) {
	
		if( 'post-new.php' !== $hook && 'post.php' !== $hook ) {
			return;
		}
	
		wp_register_script( 'coop-nsm-admin-js', plugins_url( '/js/network_shared_media_admin.js',__FILE__), array('jquery'));
		wp_register_style( 'coop-nsm-admin', plugins_url( '/css/network_shared_media_admin.css', __FILE__ ), false );
		
		wp_enqueue_style( 'coop-nsm-admin' );
		wp_enqueue_script( 'coop-nsm-admin-js' );

	}
	
	
	public function add_network_admin_page() {
		
		if( ! current_user_can('manage_network')) {
			return;
		}
		
		add_menu_page( 'Network Shared Media', 'Network Shared Media', 'manage_network', 'network-shared-media', array(&$this,'network_shared_media_page'), '', 15 );
	}
	
	
	public function add_coop_shared_media_page() {	
	
		add_submenu_page( 'site-manager', 'Network Shared Media', 'Network Shared Media', 'edit_pages', 'network-shared-media', array(&$this,'admin_network_shared_media_page'));
	}
	
	
	public function walk( $node )
	{
		$updir = wp_upload_dir();
		
		$files = scandir($node);
		$out = array();
		$out[] = '<ul>';
		foreach( $files as $f ) {
			if($f == '.' || $f == '..' ) continue;
			$out[] = '<li>';
			if( is_file( $node .'/'. $f )) {
				
				$url = str_replace($updir['basedir'],$updir['baseurl'],$node);
				$out[] = '<img src="'.$url.'/'. $f .'" width="50" >';
			}
			else {
				$out[] = $this->walk($node.'/'.$f);
			}
			$out[] = '</li>';
		}
		$out[] = '</ul>';
		return implode("\n",$out);
	}
	
	
	/**
	*	Super Admin page
	**/
	public function network_shared_media_page () {
		
		if( ! current_user_can('mangage_network') ) {
			return;
		}
		
		$out = array();
		
		$out[] = '<div class="wrap">';
		
		$out[] = '<div id="icon-options-general" class="icon32">';
		$out[] = '<br>';
		$out[] = '</div>';
		
		$out[] = '<h2>Network Shared Media</h2>';
		$out[] = '<p>Manage content available on the media server. This view is availlable to Super Administrators only.</p>';
	
		
		/*

		$wp_updir = wp_upload_dir();
		$tmp = array();
		$tmp[] = $this->walk($wp_updir['basedir']);
		$out[] = implode( "\n",$tmp );
*/

		echo implode("\n",$out);
		
		$smt = new Shared_Media_Table();	
		$smt->prepare_items();
		$smt->display();
			
		echo '</div><!-- .wrap -->';
		
	}
		
	/**
	*	Regular management page
	**/
	public function admin_network_shared_media_page () {
		
		if( ! current_user_can('edit_pages') ) die('You do not have required permissions to view this page');
		
		$out = array();
		
		$out[] = '<div class="wrap">';
		
		$out[] = '<div id="icon-options-general" class="icon32">';
		$out[] = '<br>';
		$out[] = '</div>';
		
		$out[] = '<h2>Network Shared Media Centre</h2>';
		$out[] = '<p>Browse through content available on the media server. </p>';
		
		$out[] = '<ul class="tab-container">';
		$out[] = '<a href="#tab-one"><li class="tab" id="tab-one">Shared Images</li></a>';
		$out[] = '<a href="#tab-two"><li class="tab" id="tab-two">Shared Texts</li></a>';
		$out[] = '</ul><!-- .tab-container -->';
		
		$out[] = '<div class="tab tab-one">';
		$out[] = '<a name="#tab-one"></a>';
		$out[] = '<h2>Shared images</h2>';
		
		echo implode("\n",$out);
		
		$smt = new Shared_Media_Table();
		$smt->prepare_items();
		$smt->display();
		
		$out = array();
		$out[] = '</div><!-- .tab .tab-one -->';

		$out[] = '<div class="tab tab-two">';
		$out[] = '<p>&nbsp;</p>';
		$out[] = '<a name="#tab-two"></a>';
		$out[] = '<h2>Shared boilerplate text</h2>';
		
		echo implode("\n",$out);
		
		
		$stt = new Shared_Text_Table();
		$stt->prepare_items();
		$stt->display();
		
		
		/*
		$wp_updir = wp_upload_dir();
		$tmp = array();
		$tmp[] = $this->walk($wp_updir['basedir']);
		$out[] = implode( "\n",$tmp );
		
*/

		$out = array();
		$out[] = '</div><!-- .tab .tab-two -->';
		
		
		$out[] = '</div><!-- .wrap -->';
		
		echo implode("\n",$out);
	}
	
	
	/**
	*	adds meta box widgets to the Edit post forms of client sites.
	*
	*
	**/
	public function add_nsm_metaboxen() {
	
		add_meta_box( 'coop-nsm-shared-text-metabox', 'Shared Text Preview', array(&$this,'add_shared_text_viewer_metabox'), 'page', 'advanced', 'core' );
		
	}

	/**
	*	This metabox widget does not provide editing context:
	*	it is a read-only preview of remote sourced text to be included in the published page.
	*
	*	What we do provide is a select list of existing Shared Text entries to choose from.	
	*	This will immediately present the new text in the preview below, fired by AJAX callback
	*	to the shared media server. 
	**/	
	public function add_shared_text_viewer_metabox( $post ) {
	  
		//	This call fetches the wp_XX_postmeta table data
		//	which links this $post to a wp_posts table entry in blog 1.
		$nsm_text_id = get_post_meta( $post->ID, '_coop_nsm_shared_text_id', true );
		
		//	Select from wp_posts (onedomain) blog1
		//	and list titles 
		$sql = "SELECT p.ID, post_author, post_date, post_title, post_parent FROM wp_posts p
				WHERE post_type='post' AND post_status = 'publish' ORDER BY post_title";
		global $wpdb;
		$res = $wpdb->get_results($sql);
		
		$out = array('<select id="coop-nsm-shared-text-selector" name="coop-nsm-shared-text-selector">');
		$out[] = '<option value="-1"></option>';
		foreach( $res as  $r) {
			$out[] = sprintf('<option value="%d"%s>%s</option>',
								$r->ID,
								(($r->ID==$nsm_text_id)?' selected="selected"':''),
								$r->post_title);	 
		}
		$out[] = '</select>';
				
		$out[] = '&nbsp;&nbsp;<input type="checkbox" id="coop-nsm-apply-text" name="coop-nsm-apply-text"
		 >&nbsp;&nbsp;<label for="coop-nsm-apply-text">Use&nbsp;this&nbsp;text</label>';
		
		echo implode("\n",$out);
		echo '<div class="coop-nsm-shared-text-preview"></div>';
		echo '<p>NOTE: The text shown here will appear in the page <em>before</em> any text you enter in the editor above.</p>';
	}
	
	
	public function coop_nsm_fetch_preview_callback() {
		
		$nsm_text_id = $_POST['nsm_text_id'];
	  
		//	Select from wp_posts (onedomain) blog1
		//	and list titles 
		$sql = "SELECT post_content FROM wp_posts WHERE ID=$nsm_text_id";
        global $wpdb;
        $content = $wpdb->get_var($sql);
        
        echo '{"result":"success", "nsm_preview":'.json_encode($content).'}';
        die();
	}
	
	
	public function coop_nsm_fetch_shared_images_callback() {
		
		$data = Network_Shared_Media_Utils::getImageData();
		
		$out = array();
		$out[] = '{"images": [';
		$cnt = count($data);
		$c = 1;
		foreach( $data as $r ) {
		
			if( $c < $cnt ) {
				$comma = ',';
			}
			else {
				$comma = '';
			}
			
			$out[] = '{"id":"'.$r['ID'].'", "date": "'.$r['date'].'", "title":"'.$r['title'].'","thumbnail":"'.$r['thumbnail'].'"}'.$comma;
			$c++;
		}
		$out[] = ']}';
		
		echo implode("",$out);
		die();
	}
	
	
	public function coop_nsm_fetch_image_metadata_callback() {
		
		global $wpdb;
		
		$img_id = $_POST['img_id'];
		
	//	error_log( 'img id: ' . $img_id );
		
		$sql = "SELECT post_date FROM wp_posts WHERE ID = $img_id ";
		$date = $wpdb->get_var($sql);
		$date = date_parse($date);
		$d = new DateTime( $date['year'].'/'.$date['month'].'/'.$date['day']);
		$date = date_format($d, 'F j, Y');
		
		
		$sql = "SELECT meta_value FROM wp_postmeta WHERE meta_key = '_wp_attachment_metadata' AND post_id = $img_id";
    	$meta = $wpdb->get_var($sql);
    	
    	$m = maybe_unserialize($meta);
		
		/*

		foreach( $m as $k => $l ) {
			if( is_array($l)) {
				foreach( $l as $p => $v ) {
					if( is_array($v) ) {
						foreach( $v as $a => $b ) {
							error_log( $k . '[p]'.$p .': '. $a .' <=> '. $b);	
						}
					}
					else {
						error_log( '[k]'.$k .': '. $p .' => '. $v);
					}
				}	
			}
			else {
				error_log($k .' :: '. $l );
			}
		}
		
		*/
		
		$path = explode("/",$m['file']);
		$folder = $path[0].'/'.$path[1] .'/';
		$m['file'] = str_replace($folder,'',$m['file']);
		
		$out = array();
		$out[] = '{"fullsize": {"file": "'.$m['file'].'", "width":"'.$m['width'].'", "height": "'.$m['height'].'"},';
		$out[] = '"midsize":  {"file": "'.$m['sizes']['medium']['file'].'", "width":"'.$m['sizes']['medium']['width'].'", "height": "'.$m['sizes']['medium']['height'].'"},';
		$out[] = '"thumb": {"file": "'.$m['sizes']['thumbnail']['file'].'", "width":"'.$m['sizes']['thumbnail']['width'].'", "height": "'.$m['sizes']['thumbnail']['height'].'"}, ';
		$out[] = ' "folder": "'.$folder.'", "date": "'.$date.'"}';
				
		echo implode("",$out);
		
		die();
			
	}
	
	
	
	public function coop_nsm_shared_text_shortcode_handler($atts) {
	
		global $post, $wpdb;
	
		extract( shortcode_atts( array(
			'id' => -1
		), $atts ) );

		if( $id == null || (int)$id == -1 ) {
			$id = get_post_meta($post->ID,'_coop_nsm_shared_text_id',true);
		}
		if( $id == null || $id < 1 ) {
			return "[ missing id of text to include ]";
		}
		
		//	Select from wp_posts (onedomain) blog1
		//	and list titles 
		$sql = "SELECT post_content FROM wp_posts WHERE ID=$id";
		$content = $wpdb->get_var($sql);
		
		return $content;
	}
	
	public function coop_nsm_shared_text_save_post( $post_id ) {
		
		if ( !wp_is_post_revision( $post_id ) ) {
		
			// we have to really mean to use this included text system
			if( array_key_exists('coop-nsm-apply-text',$_POST)) {
				$apply_text_flag = $_POST['coop-nsm-apply-text'];
			}
			
			if ( !isset($apply_text_flag) ) {
				update_post_meta($post_id,'_coop_nsm_shared_text_id','');	
			}
			
			$nsm_text_id = $_POST['coop-nsm-shared-text-selector'];
			
			if( isset($nsm_text_id) && $nsm_text_id > 0 ) {
				
				$prev_text_id = get_post_meta($post_id,'_coop_nsm_shared_text_id',true);
			//	error_log( 'new text id: ' . $nsm_text_id.', prev: '. $prev_text_id );	

				$content = $_POST['content'];	
			//	error_log( $content );
				
				if( isset($prev_text_id) && FALSE !== strpos($content,'coop-nsm-shared-text') ) {
					$content = str_replace( $prev_text_id, $nsm_text_id, $content );
				}
				else {
					$content = '[coop-nsm-shared-text id="'.$nsm_text_id.'"]'."\n".$content;
				}
				
			//	error_log( $content );
				
				$my_post = array();
				$my_post['ID'] = $post_id;
				$my_post['post_content'] = $content;
				
				remove_action( 'save_post',array(&$this,'coop_nsm_shared_text_save_post'));

				wp_update_post($my_post);
				update_post_meta($post_id,'_coop_nsm_shared_text_id',$nsm_text_id);	

				add_action( 'save_post',array(&$this,'coop_nsm_shared_text_save_post'));
			}
			else {
				$content = $_POST['content'];
				
				if( FALSE !== strpos($content,'coop-nsm-shared-text') ) {
					$search = '/\[.*\]/';
					$content = preg_replace($search, '', $content);
				//	error_log( 'after stripping: ' . $content );
				}
				
				$my_post = array();
				$my_post['ID'] = $post_id;
				$my_post['post_content'] = $content;
				
				remove_action( 'save_post',array(&$this,'coop_nsm_shared_text_save_post'));

				wp_update_post($my_post);

				add_action( 'save_post',array(&$this,'coop_nsm_shared_text_save_post'));
				
			}
		}
	}
	

}

if ( ! isset( $coop_shared_media ) )  {
	require_once( 'inc/nsm-utils.php' );
	require_once( 'inc/shared-media-table.php' );
	require_once( 'inc/shared-text-table.php' );
	$coop_shared_media = new Coop_Shared_Media();
}
	
endif; /* ! class_exists */
