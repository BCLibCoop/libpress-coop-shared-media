<?php

/**
 * Utility functions
 *
 * @package           BCLibCoop\NetworkSharedMediaUtils
 * @author            Erik Stainsby <eric.stainsby@roaringsky.ca>
 * @author            Sam Edwards <sam.edwards@bc.libraries.coop>
 * @copyright         2013-2021 BC Libraries Cooperative
 * @license           GPL-2.0-or-later
 **/

namespace BCLibCoop\SharedMedia;

class NetworkSharedMediaUtils
{
    /**
     * This routine is intentionally hardwired to use Blog 1.
     * Blog 1 is defined as the media server.
     * All shared media / text is owned by blog 1.
     * All urls must resolve to the hostname of blog 1.
     *
     **/
    public static function getImageData()
    {
        /**
         * This select is HARD-CODED to select ONLY from wp_posts
         **/

        // Get uploads dir of blog 1
        switch_to_blog(1);

        $shared_medias = get_posts([
            'post_type' => 'attachment',
            'posts_per_page' => -1,
            'orderby' => 'post_title',
            'order' => 'ASC',
        ]);

        $data = [];

        foreach ($shared_medias as $shared_media) {
            $data[] = [
                'ID'     => $shared_media->ID,
                'author'   => $shared_media->post_author,
                'author_name' => get_the_author_meta('display_name', $shared_media->post_author),
                'date'    => substr($shared_media->post_date, 0, 10),
                'title'   => $shared_media->post_title,
                'attached'  => $shared_media->post_parent,
                'mime_type' => $shared_media->post_mime_type,
                'url'    => wp_get_attachment_url($shared_media->ID),
                'thumbnail'  => wp_get_attachment_image_url($shared_media->ID, 'thumbnail'),
            ];
        }

        restore_current_blog();

        return $data;
    }
}
