<?php defined('ABSPATH') || die('No direct access' );

/**
 * @package Network Shared Media
 * @component Shared Media Table 
 * @copyright BC Libraries Coop 2013
 **/
 
/**
*
*	Custom image/media listing table
*
**/

class Shared_Media_Table extends WP_List_Table {

	function __construct(){
        global $status, $page;
                
        //Set parent defaults
        parent::__construct( array(
            'singular'  => 'media',     //singular name of the listed records
            'plural'    => 'media',     //plural name of the listed records
            'ajax'      => false        //does this table support ajax?
        ) );
    }
      
      
    /**
	*	Required. Defines the columns & headers
	*
	**/
	function get_columns(){
        $columns = array(
            'cb'        => '<input type="checkbox" />', //Render a checkbox instead of text
            'thumb'		=> '',
            'title'    	=> 'File',
            'author'    => 'Author',
            'attached'  => 'Attached to',
            'date'		=> 'Date'
        );
        return $columns;
    }
      
      
    function column_default($item, $column_name){
        return print_r($item,true); //Show the whole array for troubleshooting purposes
    }   
      
    
   	function column_thumb($item){
  	   	
        //Return the thumbnail image
        return sprintf('<img src="%1$s" class="smc-thumbnail" alt="%2$s" %3$s>',
            $item['thumbnail'],  // returns guid
            $item['title'],
            'width="100"'
        );
    }

	function column_title($item){
        
        if( current_user_can('manage_network')) {
	        
	        $actions = array(
	            'edit' => sprintf('<a href="http://%1$s/wp-admin/post.php?post=%2$s&action=%3$s">Edit</a>',$item['domain'],$item['ID'],'edit'),
	   //         'delete'    => sprintf('<a href="http://%1$s/wp-admin/post.php?post=%2$s&action=%3$s">Delete Permanently</a>',$item['domain'],$item['ID'],'delete'),
	            'view'    => sprintf('<a href="http://%1$s/%2$s">View</a>',$item['url'],$item['filename'])
	        );
	       
	        //Return the contents
	        return sprintf('<a href="/wp-admin/post.php?post=%1$s&action=edit">%2$s</a><br/><span class="smc-mime-type">%3$s</span><br/>%4$s',
	        	$item['ID'],
	            $item['title'],
	            $item['mime_type'],
	            $this->row_actions($actions)
	        );
	    }
	    else {
	    	$actions = array('view' => sprintf('<a href="http://%1$s/%2$s">View</a>',$item['url'],$item['filename'])
	        );
	    
	        //Return the contents
	        return sprintf('%1$s<br/><span class="smc-mime-type">%2$s</span>',
	            $item['title'],
	            $item['mime_type'],
	            $this->row_actions($actions)
	        );
	    }
    }

	function column_author($item){
        //Return the author contents
        return $item['author_name'];
    }


	function column_attached($item){
   		   		        
        //Return the attachment information
        if( $item['attached'] == 0 ) {
        
        	$msg = '(Unattached)';
        	
        	if( current_user_can( 'manage_network' )) {
        		$msg .= sprintf('<br/><a href="http://%1$s/wp-admin/upload.php#the-list">Attach</a>',
	        		$item['domain']
				);
			}
	        return $msg;
        }
        
        // if this is rendered it must be in the user's blog, not on the media server
        return sprintf( '<a href="/wp-admin/post.php?post=%1$s&action=edit">%2$s</a>',
        	$item['attached'],
        	$item['title']
        );
        
    }


	/** ************************************************************************
     * REQUIRED if displaying checkboxes or using bulk actions! The 'cb' column
     * is given special treatment when columns are processed. It ALWAYS needs to
     * have it's own method.
     * 
     * @see WP_List_Table::::single_row_columns()
     * @param array $item A singular item (one full row's worth of data)
     * @return string Text to be placed inside the column <td> (movie title only)
     **************************************************************************/
    function column_cb($item){
    
    	if( current_user_can('manage_network')) {
    
	        return sprintf(
	            '<input type="checkbox" name="%1$s[]" value="%2$s" />',
	            $this->_args['singular'],  
	            $item['ID']                //The value of the checkbox should be the record's id
	        );
	    }
	    return '';
    }


	function column_date($item){
        //Return the title contents
        return $item['date'];
    }

	/** ************************************************************************
     * Optional. If you want one or more columns to be sortable (ASC/DESC toggle), 
     * you will need to register it here. This should return an array where the 
     * key is the column that needs to be sortable, and the value is db column to 
     * sort by. Often, the key and value will be the same, but this is not always
     * the case (as the value is a column name from the database, not the list table).
     * 
     * This method merely defines which columns should be sortable and makes them
     * clickable - it does not handle the actual sorting. You still need to detect
     * the ORDERBY and ORDER querystring variables within prepare_items() and sort
     * your data accordingly (usually by modifying your query).
     * 
     * @return array An associative array containing all the columns that should be sortable: 'slugs'=>array('data_values',bool)
     **************************************************************************/
    function get_sortable_columns() {
        $sortable_columns = array(
            'title'     => array('title',false),     //true means it's already sorted
            'author'    => array('author',false),
            'attached'  => array('attached',false),
            'date'  	=> array('date',false)
        );
        return $sortable_columns;
    }

     /** ************************************************************************
     * Optional. If you need to include bulk actions in your list table, this is
     * the place to define them. Bulk actions are an associative array in the format
     * 'slug'=>'Visible Title'
     * 
     * If this method returns an empty value, no bulk action will be rendered. If
     * you specify any bulk actions, the bulk actions box will be rendered with
     * the table automatically on display().
     * 
     * Also note that list tables are not automatically wrapped in <form> elements,
     * so you will need to create those manually in order for bulk actions to function.
     * 
     * @return array An associative array containing all the bulk actions: 'slugs'=>'Visible Titles'
     **************************************************************************/
    function get_bulk_actions() {
    	if( current_user_can('manage_network')) {
	        $actions = array(
	            'delete'    => 'Delete'
	        );
	        return $actions;
	    }
	    return '';
    }
    
    
    /** ************************************************************************
     * Optional. You can handle your bulk actions anywhere or anyhow you prefer.
     * For this example package, we will handle it in the class to keep things
     * clean and organized.
     * 
     * @see $this->prepare_items()
     **************************************************************************/
    function process_bulk_action() {
        
        //Detect when a bulk action is being triggered...
        if( 'delete'===$this->current_action() ) {
            wp_die('Items deleted (or they would be if we had items to delete)!');
        }
        
    }
    
    
    /** ************************************************************************
     * REQUIRED! This is where you prepare your data for display. This method will
     * usually be used to query the database, sort and filter the data, and generally
     * get it ready to be displayed. At a minimum, we should set $this->items and
     * $this->set_pagination_args(), although the following properties and methods
     * are frequently interacted with here...
     * 
     * @global WPDB $wpdb
     * @uses $this->_column_headers
     * @uses $this->items
     * @uses $this->get_columns()
     * @uses $this->get_sortable_columns()
     * @uses $this->get_pagenum()
     * @uses $this->set_pagination_args()
     **************************************************************************/
    function prepare_items() {
    
        global $wpdb; //This is used only if making any database queries

        /**
         * First, lets decide how many records per page to show
         */
        $per_page = 10;
        
        
        /**
         * REQUIRED. Now we need to define our column headers. This includes a complete
         * array of columns to be displayed (slugs & titles), a list of columns
         * to keep hidden, and a list of columns that are sortable. Each of these
         * can be defined in another method (as we've done here) before being
         * used to build the value for our _column_headers property.
         */
        $columns	= $this->get_columns();
        $hidden 	= array();
        $sortable 	= $this->get_sortable_columns();
        
        
        /**
         * REQUIRED. Finally, we build an array to be used by the class for column 
         * headers. The $this->_column_headers property takes an array which contains
         * 3 other arrays. One for all columns, one for hidden columns, and one
         * for sortable columns.
         */
        $this->_column_headers = array($columns, $hidden, $sortable);
        
        
        /**
         * Optional. You can handle your bulk actions however you see fit. In this
         * case, we'll handle them within our package just to keep things clean.
         */
        if( current_user_can('manage_network')) {
	        $this->process_bulk_action();
		}
        
        
        // $data = array();
        
        $data = Network_Shared_Media_Utils::getImageData();
        
                
        /**
         * REQUIRED for pagination. Let's figure out what page the user is currently 
         * looking at. We'll need this later, so you should always include it in 
         * your own package classes.
         */
        $current_page = $this->get_pagenum();
        
        /**
         * REQUIRED for pagination.  
         * In real-world use, this would be the total number of items in your database, 
         * without filtering. We'll need this later, so you should always include it 
         * in your own package classes.
         */
        $total_items = count($data);
        
        
        /**
         * The WP_List_Table class does not handle pagination for us, so we need
         * to ensure that the data is trimmed to only the current page. We can use
         * array_slice() to do this
         */
        $data = array_slice($data,(($current_page-1)*$per_page),$per_page);
        
        
        /**
         * REQUIRED. Now we can add our *sorted* data to the items property, where 
         * it can be used by the rest of the class.
         */
        $this->items = $data;
        
        
        /**
         * REQUIRED. We also have to register our pagination options & calculations.
         */
        $this->set_pagination_args( array(
            'total_items' => $total_items,                  //WE have to calculate the total number of items
            'per_page'    => $per_page,                     //WE have to determine how many items to show on a page
            'total_pages' => ceil($total_items/$per_page)   //WE have to calculate the total number of pages
        ) );
    }

}