<?php

/**
 * Coop Shared Media
 *
 * A plugin to list shared text and media from the home network blog, and
 * provide an interface to insert the media, or include the next content by the
 * use of a shortcode.
 *
 * PHP Version 7
 *
 * @package           BCLibCoop\CoopSharedMedia
 * @author            Erik Stainsby <eric.stainsby@roaringsky.ca>
 * @author            Sam Edwards <sam.edwards@bc.libraries.coop>
 * @copyright         2013-2021 BC Libraries Cooperative
 * @license           GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       Coop Shared Media
 * Description:       Central media and pages repository interface
 * Version:           1.0.2
 * Network:           true
 * Requires at least: 5.2
 * Requires PHP:      7.0
 * Author:            BC Libraries Cooperative
 * Author URI:        https://bc.libraries.coop
 * Text Domain:       coop-shared-media
 * License:           GPL v2 or later
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 */

namespace BCLibCoop;

/**
 * Our Network Shared Media driver class
 **/
class CoopSharedMedia
{
    private static $instance;

    public function __construct()
    {
        if (isset(self::$instance)) {
            return;
        }

        self::$instance = $this;

        add_action('init', array(&$this, 'init'));
    }

    public function init()
    {
        if (is_admin()) {
            add_action('admin_enqueue_scripts', array(&$this, 'adminEnqueueStylesScripts'));
            add_action('admin_menu', array(&$this, 'addCoopSharedMediaMenu'));
            add_action('add_meta_boxes', array(&$this, 'addMetaboxen'));
            add_action('save_post', array(&$this, 'sharedTextSavePost'));
            add_action('wp_ajax_coop-nsm-fetch-preview', array(&$this, 'fetchPreviewCallback'));
            add_action('wp_ajax_coop-nsm-fetch-shared-images', array(&$this, 'fetchSharedImagesCallback'));
            add_action('wp_ajax_coop-nsm-fetch-image-metadata', array(&$this, 'fetchImageMetadataCallback'));
        }

        add_shortcode('coop-nsm-shared-text', array(&$this, 'sharedTextShortcodeHandler'));
    }

    public function adminEnqueueStylesScripts($hook)
    {
        if (!in_array($hook, ['post-new.php', 'post.php', 'upload.php'])) {
            return;
        }

        wp_enqueue_script('coop-nsm-admin-js', plugins_url('/js/network_shared_media_admin.js', __FILE__), ['jquery']);
        wp_enqueue_style('coop-nsm-admin', plugins_url('/css/network_shared_media_admin.css', __FILE__), false);
    }

    public function addCoopSharedMediaMenu()
    {
        add_media_page(
            'Shared Media',
            'Shared Media Images',
            'manage_local_site',
            'coop-shared-media-images',
            [&$this, 'adminSharedMediaImagesPage']
        );

        add_media_page(
            'Shared Media',
            'Shared Media Text',
            'manage_local_site',
            'coop-shared-media-text',
            [&$this, 'adminSharedMediaTextPage']
        );
    }

    /**
     * Regular management page
     **/
    public function adminSharedMediaImagesPage()
    {
        if (!current_user_can('manage_local_site')) {
            wp_die('You do not have required permissions to view this page');
        }

        if (! class_exists('WP_List_Table')) {
            include_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
        }
        include_once 'inc/shared-media-table.php';

        $out = [];

        $out[] = '<div class="wrap">';

        $out[] = '<div id="icon-options-general" class="icon32">';
        $out[] = '<br>';
        $out[] = '</div>';

        $out[] = '<h2>Shared Media - Images</h2>';
        $out[] = '<p>Browse through content available on the shared media server. </p>';

        $out[] = '<div class="tab tab-one">';
        $out[] = '<h2>Shared images</h2>';

        echo implode("\n", $out);

        $smt = new SharedMediaTable();
        $smt->prepare_items();
        $smt->display();  // flushes directly - does not 'return' content

        $out = [];
        $out[] = '</div><!-- .tab .tab-one -->';

        $out[] = '</div><!-- .wrap -->';

        echo implode("\n", $out);
    }

    public function adminSharedMediaTextPage()
    {
        if (!current_user_can('manage_local_site')) {
            wp_die('You do not have required permissions to view this page');
        }

        if (! class_exists('WP_List_Table')) {
            include_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
        }

        include_once 'inc/shared-text-table.php';

        $out = [];

        $out[] = '<div class="wrap">';

        $out[] = '<div id="icon-options-general" class="icon32">';
        $out[] = '<br>';
        $out[] = '</div>';

        $out[] = '<h2>Shared Media - Text</h2>';
        $out[] = '<p>Browse through content available on the shared media server. </p>';

        $out[] = '<div class="tab tab-two">';
        $out[] = '<h2>Shared boilerplate text</h2>';

        echo implode("\n", $out);

        $stt = new SharedTextTable();
        $stt->prepare_items();
        $stt->display();  // flushes directly - does not 'return' content

        $out = [];
        $out[] = '</div><!-- .tab .tab-two -->';

        $out[] = '</div><!-- .wrap -->';

        echo implode("\n", $out);
    }

    /**
     * Adds meta box widgets to the Edit post forms of client sites.
     **/
    public function addMetaboxen()
    {
        foreach (['page', 'highlight'] as $screen) {
            add_meta_box(
                'coop-nsm-shared-text-metabox',
                'Shared Text Preview',
                [&$this, 'addSharedTextViewerMetabox'],
                $screen,
                'advanced',
                'core'
            );
        }
    }

    /**
     * This metabox widget does not provide editing context:
     * it is a read-only preview of remote sourced text to be included in the published page.
     *
     * What we do provide is a select list of existing Shared Text entries to choose from.
     * This will immediately present the new text in the preview below, fired by AJAX callback
     * to the shared media server.
     **/
    public function addSharedTextViewerMetabox($post)
    {
        // This call fetches the wp_XX_postmeta table data
        // which links this $post to a wp_posts table entry in blog 1.
        $nsm_text_id = get_post_meta($post->ID, '_coop_nsm_shared_text_id', true);

        // Select from wp_posts (onedomain) blog1
        // and list titles
        switch_to_blog(1);
        $shared_posts = get_posts([
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'post_title',
            'order' => 'ASC',
            'lang' => '', // Avoid Polylang adding terms to this query
        ]);
        restore_current_blog();

        $out = [];
        $out[] = '<select id="coop-nsm-shared-text-selector" name="coop-nsm-shared-text-selector">';
        $out[] = '<option value="-1"></option>';

        foreach ($shared_posts as $shared_post) {
            $out[] = sprintf(
                '<option value="%d"%s>%s</option>',
                $shared_post->ID,
                (($shared_post->ID == $nsm_text_id) ? ' selected="selected"' : ''),
                $shared_post->post_title
            );
        }

        $out[] = '</select>';

        $out[] = '&nbsp;&nbsp;<input type="checkbox" id="coop-nsm-apply-text" name="coop-nsm-apply-text">';
        $out[] = '&nbsp;&nbsp;<label for="coop-nsm-apply-text">Use&nbsp;this&nbsp;text</label>';

        $out[] = '<div class="coop-nsm-shared-text-preview"></div>';
        $out[] = '<p>NOTE: The text shown here will appear in the page <em>before</em>'
                 . ' any text you enter in the editor above.</p>';

        echo implode("\n", $out);
    }

    public function fetchPreviewCallback()
    {
        if (!array_key_exists('nsm_text_id', $_POST)) {
            wp_send_json(['result' => 'blocked']);
        }

        $nsm_text_id = (int) $_POST['nsm_text_id'];

        switch_to_blog(1);
        $content = get_the_content(null, false, $nsm_text_id);
        restore_current_blog();

        wp_send_json([
            'result' => 'success',
            'nsm_preview' => $content,
        ]);
    }

    /**
     * Returns all attachments from blog 1 as JSON for insertion modal
     *
     * @todo: The entire modal could probaly be replaced by a wp.media instance
     * with a similar custom library query
     */
    public function fetchSharedImagesCallback()
    {
        $data = NetworkSharedMediaUtils::getImageData();

        $out = ['images' => []];

        foreach ($data as $r) {
            $out['images'][] = [
                'id' => $r['ID'],
                'date' => $r['date'],
                'title' => $r['title'],
                'thumbnail' => $r['thumbnail'],
            ];
        }

        wp_send_json($out);
    }

    public function fetchImageMetadataCallback()
    {
        $img_id = (int) $_POST['img_id'];

        switch_to_blog(1);
        $img = get_post($img_id);

        if ($img && $img->post_type === 'attachment') {
            $m = wp_get_attachment_metadata($img->ID);
            $img_url = wp_get_attachment_url($img->ID);
            $img_url_basename = wp_basename($img_url);
            $folder = trailingslashit(str_replace($img_url_basename, '', $img_url));
            $date = substr($img->post_date, 0, 10);
        }
        restore_current_blog();

        if ($img && $img->post_type === 'attachment') {
            $out = [
                'result' => 'success',
                'fullsize' => [
                    'file' => $img_url_basename,
                    'width' => $m['width'],
                    'height' => $m['height'],
                ],
                'midsize' => [
                    'file' => '',
                ],
                'thumb' => [
                    'file' => '',
                ],
                'folder' => $folder,
                'date' => $date,
            ];

            if (!empty($m['sizes']['medium'])) {
                $out['midsize'] = [
                    'file' => $m['sizes']['medium']['file'],
                    'width' => $m['sizes']['medium']['width'],
                    'height' => $m['sizes']['medium']['height'],
                ];
            }

            if (!empty($m['sizes']['thumbnail'])) {
                $out['thumb'] = [
                    'file' => $m['sizes']['thumbnail']['file'],
                    'width' => $m['sizes']['thumbnail']['width'],
                    'height' => $m['sizes']['thumbnail']['height'],
                ];
            }

            wp_send_json($out);
        }

        wp_send_json(['result' => 'failed']);
    }

    public function sharedTextShortcodeHandler($atts)
    {
        global $post;

        extract(shortcode_atts(array(
            'id' => -1
        ), $atts));

        if ($id == null || (int) $id == -1) {
            $id = get_post_meta($post->ID, '_coop_nsm_shared_text_id', true);
        }

        if ($id == null || $id < 1) {
            return "[ missing id of text to include ]";
        }

        // Select from wp_posts (onedomain) blog1
        switch_to_blog(1);
        $content = get_the_content(null, false, $id);
        restore_current_blog();

        return $content;
    }

    public function sharedTextSavePost($post_id)
    {
        if (!wp_is_post_revision($post_id)) {
            // we have to really mean to use this included text system
            if (array_key_exists('coop-nsm-apply-text', $_POST)) {
                $apply_text_flag = (bool) $_POST['coop-nsm-apply-text'];
            }

            if (!isset($apply_text_flag)) {
                update_post_meta($post_id, '_coop_nsm_shared_text_id', '');
            }

            if (array_key_exists('coop-nsm-shared-text-selector', $_POST)) {
                $nsm_text_id = (int) $_POST['coop-nsm-shared-text-selector'];
            }

            if (isset($nsm_text_id) && $nsm_text_id > 0) {
                $prev_text_id = get_post_meta($post_id, '_coop_nsm_shared_text_id', true);
                // error_log( 'new text id: ' . $nsm_text_id.', prev: '. $prev_text_id );

                $content = $_POST['content'];
                // error_log( $content );

                if (isset($prev_text_id) && false !== strpos($content, 'coop-nsm-shared-text')) {
                    $content = str_replace($prev_text_id, $nsm_text_id, $content);
                } else {
                    $content = '[coop-nsm-shared-text id="' . $nsm_text_id . '"]' . "\n" . $content;
                }

                // error_log( $content );

                $my_post = array();
                $my_post['ID'] = $post_id;
                $my_post['post_content'] = $content;

                remove_action('save_post', array(&$this, 'sharedTextSavePost'));

                wp_update_post($my_post);
                update_post_meta($post_id, '_coop_nsm_shared_text_id', $nsm_text_id);

                add_action('save_post', array(&$this, 'sharedTextSavePost'));
            } else {
                if (array_key_exists('content', $_POST)) {
                    $content = $_POST['content'];

                    if (false !== strpos($content, 'coop-nsm-shared-text')) {
                        $search = '/\[.*\]/';
                        $content = preg_replace($search, '', $content);
                        // error_log( 'after stripping: ' . $content );
                    }

                    $my_post = array();
                    $my_post['ID'] = $post_id;
                    $my_post['post_content'] = $content;

                    remove_action('save_post', array(&$this, 'sharedTextSavePost'));

                    wp_update_post($my_post);

                    add_action('save_post', array(&$this, 'sharedTextSavePost'));
                }
            }
        }
    }
}

// No Direct Access
defined('ABSPATH') || die('No direct access');

include_once 'inc/nsm-utils.php';

new CoopSharedMedia();
