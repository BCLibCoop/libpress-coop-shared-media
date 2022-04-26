<?php

/**
 * Custom text posts listing table
 *
 * Extends WP_List_Table, so can't follow PSR12 standards for method names
 *
 * @package           BCLibCoop\SharedTextTable
 * @author            Erik Stainsby <eric.stainsby@roaringsky.ca>
 * @author            Sam Edwards <sam.edwards@bc.libraries.coop>
 * @copyright         2013-2021 BC Libraries Cooperative
 * @license           GPL-2.0-or-later
 **/

namespace BCLibCoop\SharedMedia;

class SharedTextTable extends \WP_List_Table
{
    public function __construct()
    {
        parent::__construct([
            'singular'  => 'text', // singular name of the listed records
            'plural'    => 'texts', // plural name of the listed records
            'ajax'      => false // does this table support ajax?
        ]);
    }

    /**
     * Required. Defines the columns & headers
     **/
    public function get_columns() //phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    {
        return [
            'title'     => 'Text',
            'author'    => 'Author',
            'attached'  => 'Attached to',
            'date'      => 'Date',
        ];
    }

    public function column_title($item) //phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    {
        if (current_user_can('manage_network')) {
            // Switch to get right admin_url, etc.
            switch_to_blog(1);

            $actions = [
                'edit' => sprintf(
                    '<a href="%1$s">Edit</a>',
                    admin_url('post.php?post=' . $item['ID'] . '&action=edit')
                ),
                'delete' => sprintf(
                    '<a href="%1$s">Delete Permanently</a>',
                    admin_url('post.php?post=' . $item['ID'] . '&action=delete')
                ),
            ];

            // Return the contents
            $cell = sprintf(
                '<a href="%1$s">%2$s</a><br/>%3$s',
                admin_url('post.php?post=' . $item['ID'] . '&action=edit'),
                $item['title'],
                $this->row_actions($actions)
            );

            restore_current_blog();

            return $cell;
        } else {
            $actions = [
                // 'view' => sprintf('<a href="%1$s">View</a>', esc_url($item['url'])),
            ];

            // Return the contents
            return sprintf(
                '%1$s<br/>%2$s',
                $item['title'],
                $this->row_actions($actions)
            );
        }
    }

    public function column_author($item) //phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    {
        // Return the author contents
        return $item['author_name'];
    }

    public function column_attached($item) //phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    {
        // Return the attachment information
        if ($item['attached'] == 0) {
            $msg = '(Unattached)';

            if (current_user_can('manage_network')) {
                // switch_to_blog(1);
                // $msg .= sprintf(
                //   '<br/><a href="%1$s">Attach</a>',
                //   admin_url('upload.php#the-list')
                // );
                // restore_current_blog();
            }

            return $msg;
        }

        // if this is rendered it must be in the user's blog, not on the media server
        // return sprintf(
        //   '<a href="%1$s">%2$s</a>',
        //   admin_url('post.php?post=' . $item['attached'] . '&action=edit'),
        //   $item['title']
        // );
        return '';
    }

    public function column_date($item) //phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    {
        // Return the title contents
        return $item['date'];
    }

    /** ************************************************************************
     * REQUIRED! This is where you prepare your data for display. This method will
     * usually be used to query the database, sort and filter the data, and generally
     * get it ready to be displayed. At a minimum, we should set $this->items and
     * $this->set_pagination_args(), although the following properties and methods
     * are frequently interacted with here...
     *
     * @uses $this->_column_headers
     * @uses $this->items
     * @uses $this->get_columns()
     * @uses $this->get_sortable_columns()
     * @uses $this->get_pagenum()
     * @uses $this->set_pagination_args()
     **************************************************************************/
    public function prepare_items() //phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    {
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
        $columns  = $this->get_columns();
        $hidden   = [];
        $sortable   = $this->get_sortable_columns();

        /**
         * REQUIRED. Finally, we build an array to be used by the class for column
         * headers. The $this->_column_headers property takes an array which contains
         * 3 other arrays. One for all columns, one for hidden columns, and one
         * for sortable columns.
         */
        $this->_column_headers = [$columns, $hidden, $sortable];

        /**
         * Blog 1 is defined by WP as the owner of the Network.
         * Blog 1 is defined by us as the media/shared-text server.
         * All shared media / text is owned by blog 1.
         * All urls must resolve to the hostname of blog 1.
         **/
        switch_to_blog(1);

        $shared_posts = get_posts([
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'post_title',
            'order' => 'ASC',
        ]);

        $data = [];

        foreach ($shared_posts as $shared_post) {
            $data[] = [
                'ID'     => $shared_post->ID,
                'author'   => $shared_post->post_author,
                'author_name' => get_the_author_meta('display_name', $shared_post->post_author),
                'date'    => substr($shared_post->post_date, 0, 10),
                'title'   => $shared_post->post_title,
                'attached'  => $shared_post->post_parent,
            ];
        }

        restore_current_blog();

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
        $data = array_slice($data, (($current_page - 1) * $per_page), $per_page);

        /**
         * REQUIRED. Now we can add our *sorted* data to the items property, where
         * it can be used by the rest of the class.
         */
        $this->items = $data;

        /**
         * REQUIRED. We also have to register our pagination options & calculations.
         */
        $this->set_pagination_args([
            'total_items' => $total_items,                    // WE have to calculate the total number of items
            'per_page'    => $per_page,                       // WE have to determine how many items to show on a page
            'total_pages' => ceil($total_items / $per_page),  // WE have to calculate the total number of pages
        ]);
    }
}
