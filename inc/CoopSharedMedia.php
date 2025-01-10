<?php

namespace BCLibCoop\SharedMedia;

/**
 * Main Loader for Shared Media Functionality
 */
class CoopSharedMedia
{
    private static $instance;

    public $shortcode = 'coop-nsm-shared-text';
    public $meta_key = '_coop_nsm_shared_text_id';

    public function __construct()
    {
        if (isset(self::$instance)) {
            return;
        }

        self::$instance = $this;

        add_action('init', [$this, 'init']);
    }

    public function init()
    {
        if (is_admin()) {
            add_action('admin_enqueue_scripts', [$this, 'adminEnqueueStylesScripts']);
            add_action('admin_menu', [$this, 'addCoopSharedMediaMenu']);
            add_action('add_meta_boxes', [$this, 'addMetaboxen']);
            add_action('save_post', [$this, 'sharedTextSavePost'], 10, 3);
            add_action('wp_ajax_coop-nsm-fetch-preview', [$this, 'fetchPreviewCallback']);
            add_action('wp_ajax_coop-nsm-fetch-shared-images', [$this, 'fetchSharedImagesCallback']);
            add_action('wp_ajax_coop-nsm-fetch-image-metadata', [$this, 'fetchImageMetadataCallback']);
        }

        add_shortcode($this->shortcode, [$this, 'sharedTextShortcodeHandler']);
    }

    public function adminEnqueueStylesScripts($hook)
    {
        if (!in_array($hook, ['post-new.php', 'post.php', 'upload.php'])) {
            return;
        }

        wp_enqueue_script(
            'coop-nsm-admin-js',
            plugins_url('/js/network_shared_media_admin.js', SHAREDMEDIA_PLUGIN_FILE),
            ['jquery'],
            get_plugin_data(SHAREDMEDIA_PLUGIN_FILE, false, false)['Version']
        );
        wp_enqueue_style(
            'coop-nsm-admin',
            plugins_url('/css/network_shared_media_admin.css', SHAREDMEDIA_PLUGIN_FILE),
            [],
            get_plugin_data(SHAREDMEDIA_PLUGIN_FILE, false, false)['Version']
        );
    }

    public function addCoopSharedMediaMenu()
    {
        add_media_page(
            'Shared Media',
            'Shared Media Images',
            'manage_local_site',
            'coop-shared-media-images',
            [$this, 'adminSharedMediaImagesPage']
        );

        add_media_page(
            'Shared Media',
            'Shared Media Text',
            'manage_local_site',
            'coop-shared-media-text',
            [$this, 'adminSharedMediaTextPage']
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

        $out = [];

        $out[] = '<div class="wrap">';

        $out[] = '<h1 class="wp-heading-inline">Shared Media - Images</h1>';
        $out[] = '<hr class="wp-header-end">';

        $out[] = '<p>Browse through content available on the shared media server. </p>';

        $out[] = '<div class="tab tab-one">';
        $out[] = '<h2>Shared images</h2>';

        ob_start();

        $smt = new SharedMediaTable();
        $smt->prepare_items();
        $smt->display();  // flushes directly - does not 'return' content

        $out[] = ob_get_clean();

        $out[] = '</div><!-- .tab .tab-one -->';

        $out[] = '</div><!-- .wrap -->';

        echo implode("\n", $out);
    }

    public function adminSharedMediaTextPage()
    {
        if (!current_user_can('manage_local_site')) {
            wp_die('You do not have required permissions to view this page');
        }

        $out = [];

        $out[] = '<div class="wrap">';

        $out[] = '<h1 class="wp-heading-inline">Shared Media - Text</h1>';
        $out[] = '<hr class="wp-header-end">';

        $out[] = '<p>Browse through content available on the shared media server. </p>';

        $out[] = '<div class="tab tab-two">';
        $out[] = '<h2>Shared boilerplate text</h2>';

        ob_start();

        $stt = new SharedTextTable();
        $stt->prepare_items();
        $stt->display();  // flushes directly - does not 'return' content

        $out[] = ob_get_clean();

        $out[] = '</div><!-- .tab .tab-two -->';

        $out[] = '</div><!-- .wrap -->';

        echo implode("\n", $out);
    }

    /**
     * Adds meta box widgets to the Edit post forms of client sites.
     **/
    public function addMetaboxen()
    {
        add_meta_box(
            'coop-nsm-shared-text-metabox',
            'Shared Text Preview',
            [$this, 'addSharedTextViewerMetabox'],
            ['page', 'highlight'],
            'advanced',
            'core'
        );
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
        $nsm_text_id = get_post_meta($post->ID, $this->meta_key, true);

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
        $out[] = '<option value="0"></option>';

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
     * @todo: The entire modal could probably be replaced by a wp.media instance
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

        extract(shortcode_atts([
            'id' => (int) get_post_meta($post->ID, $this->meta_key, true)
        ], $atts));

        if (empty($id) || $id < 1) {
            return "[ missing id of text to include ]";
        }

        // Select from wp_posts (onedomain) blog1
        switch_to_blog(1);
        $content = get_the_content(null, false, $id);
        restore_current_blog();

        return $content;
    }

    public function sharedTextSavePost($post_id, $post, $update)
    {
        // Only supported on page or highlight
        if (!in_array(get_post_type($post_id), ['page', 'highlight'])) {
            // Try and clean up posts that we previously added meta to
            delete_post_meta($post_id, $this->meta_key);
            return;
        }

        if (wp_is_post_revision($post_id)) {
            return;
        }

        remove_action('save_post', [$this, 'sharedTextSavePost']);

        // we have to really mean to use this included text system
        $apply_text_flag = (bool) ($_POST['coop-nsm-apply-text'] ?? 0);
        $nsm_text_id = (int) sanitize_text_field($_POST['coop-nsm-shared-text-selector'] ?? '0');

        $old_content = $new_content = $post->post_content;
        $prev_text_id = get_post_meta($post_id, $this->meta_key, true);
        $search ="/\[{$this->shortcode}\s+?id=.*?%d.*?\]\\n?/";
        $replace = "[%s id=\"%d\"]\n";

        if ($apply_text_flag && $nsm_text_id) {
            if (!empty($prev_text_id) && $prev_text_id != $nsm_text_id) {
                $new_content = preg_replace(
                    sprintf($search, $prev_text_id),
                    sprintf($replace, $this->shortcode, $nsm_text_id),
                    $old_content
                );
            }

            if (preg_match(sprintf($search, $nsm_text_id), $new_content) === 0) {
                $new_content = sprintf($replace, $this->shortcode, $nsm_text_id) . $old_content;
            }

            update_post_meta($post_id, $this->meta_key, $nsm_text_id);
        } elseif (!empty($prev_text_id)) {
            delete_post_meta($post_id, $this->meta_key);

            // Remove only the old stored ID's shortcode
            $new_content = preg_replace(sprintf($search, $prev_text_id), '', $old_content);
        }

        if ($old_content !== $new_content) {
            $update_post = [
                'ID' => $post_id,
                'post_content' => $new_content,
            ];

            wp_update_post($update_post);
        }
    }
}
