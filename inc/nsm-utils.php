<?php defined('ABSPATH') || die('No direct access' );

/**
 * @package Network Shared Media Utilities
 * @component Shared Media Table 
 * @copyright BC Libraries Coop 2013
 **/
 
/**
*
*	
*
**/

if ( ! class_exists( 'Network_Shared_Media_Utils' )) :
	
class Network_Shared_Media_Utils {

	public function __construct() {
		add_action( 'init', array( &$this, '_init' ));
	}

	public function _init() {
		
	}


	/**
	*	This routine is intentionally hardwired to use Blog 1.
	*	Blog 1 is defined as the media server. 
    *	All shared media / text is owned by blog 1.	
    *	All urls must resolve to the hostname of blog 1.
	*
	**/

	public function getImageData() {
   
		global $wpdb;
		
        $sql = "SELECT domain FROM wp_blogs WHERE blog_id = 1";
        $one_domain = $wpdb->get_var($sql);
  
        /**
        *	This select is HARD-CODED to select ONLY from wp_posts
        *	IT IS DELIBERATELY NOT NETWORK AWARE.
        **/
        
        $sql = "SELECT p.ID, post_author, post_date, post_title, post_parent, post_mime_type, guid, u.user_login
        		FROM wp_posts p JOIN $wpdb->users u ON p.post_author=u.ID WHERE post_type='attachment' ORDER BY post_title";
        $res = $wpdb->get_results($sql);
        
        
        $sql = "SELECT * FROM wp_postmeta WHERE meta_key = '_wp_attachment_metadata'";
    	$meta = $wpdb->get_results($sql, ARRAY_A);
    	
    	$m = array();
    	foreach( $meta as $k => $v ) {
        	$m[$v['post_id']] = maybe_unserialize($v['meta_value']);
    	}
    	
    /*
	echo '<pre>';
    	var_dump($m);
    	echo '</pre>';
*/
    	        
        $data = array();
        $updir = wp_upload_dir();
        
        foreach( $res as $r )
        {   
        	// this follows the current blog_id - not what we want - 
        	// $m = get_post_meta($r->ID,'_wp_attachment_metadata');
        	
        	// so we do this instead ...
        	
	        $data[] = array( 
	        	'ID' 		=> $r->ID,
		        'author' 	=> $r->post_author,
		        'author_name' => $r->user_login,
		        'date'		=> substr($r->post_date,0,10),
		        'title' 	=> $r->post_title,
		        'attached'	=> $r->post_parent,
		        'mime_type' => $r->post_mime_type,
		        'domain'	=> $one_domain,
		        'url'		=> $one_domain . '/wp-uploads',
		        'thumbnail'	=> $r->guid,
		        'filename'  => $m[$r->ID]['file'],
		        'meta' 		=> $m[$r->ID]
	        );
        }
        
        return $data;
    }
    
}

endif; /* ! class_exists */