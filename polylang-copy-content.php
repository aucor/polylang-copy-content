<?php
/*
Plugin Name: Polylang Copy Content
Plugin URI: https://github.com/aucor/polylang-copy-content
Version: 1.0.0
Author: Aucor Oy, leemon
Author URI: https://github.com/aucor
Description: Copy content, title and attachments when creating a new translation in Polylang
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Text Domain: polylang-copy-content
*/

class PolylangCopyContent {

  /**
   * Constructor
   */
  public function __construct() {

    // Check that Polylang is active
    global $polylang;

    if (isset($polylang)) {
      add_action('rest_api_init', array(&$this, 'copy_content_and_title'), 2 ); // gutenberg
      add_action('add_meta_boxes', array(&$this, 'copy_content_and_title'), 5); // classic editor
      add_filter('wp_generate_attachment_metadata', array(&$this, 'wp_generate_attachment_metadata'), 10, 2);
    }
  }

  /**
   * Copy content and title
   *
   * Send filtered content and title to editor
   *
   * @param string post_type of the translated post
   * @param obj post the new translated post
   *
   */
  function copy_content_and_title() {

    // copying is done only when new translation is created
    if ($GLOBALS['pagenow'] == 'post-new.php' && isset($_GET['from_post'], $_GET['new_lang'])) {

      global $post;

      if (!($post instanceof WP_Post)) {
        return; // invalid post object
      }

      if (!PLL()->model->is_translated_post_type($post->post_type))
        return; // post type not translatable

      if(!empty($post->post_content)) {
        return; // post content not empty, let's not mess with it
      }

      // if Polylang Pro and content duplication is active, don't do anything
      $duplicate_options = get_user_meta( get_current_user_id(), 'pll_duplicate_content', true );
      $is_polylang_pro_duplication_active = ! empty( $duplicate_options ) && ! empty( $duplicate_options[ $post->post_type ] );
      if($is_polylang_pro_duplication_active) {
        //return; // Polylang Pro will handle content duplication
      }

      $from_post_id = (int) $_GET['from_post'];
      $new_lang = PLL()->model->get_language($_GET['new_lang']);

      $from_post_obj = get_post($from_post_id);

      // copy content
      add_filter('polylang_addon_copy_content', array(&$this, 'filter_content'), 10, 3);
      $filtered_content = apply_filters( 'polylang_addon_copy_content', $from_post_obj->post_content, $post, $new_lang->slug );
      $post->post_content = $filtered_content; // copy filtered content

      // copy title
      add_filter('polylang_addon_copy_title', array(&$this, 'filter_title'), 10, 2);
      $filtered_title = apply_filters( 'polylang_addon_copy_title', $from_post_obj->post_title, $new_lang->slug );
      $post->post_title = $filtered_title; // copy filtered title

      // copy featured image
      $this->copy_featured_image($post, $from_post_id, $new_lang->slug);

      // copy all images attached to post
      $this->copy_attached_media($post, $from_post_id, $new_lang->slug);

      // show notice
      // @TODO: create UI for undo
      add_action( 'admin_notices', function() {
        $from_post_id = (int) $_GET['from_post'];
          ?>
          <div class="notice notice-success is-dismissible">
              <p><b>Success:</b> The content and title was succesfully copied from "<?php echo get_post($from_post_id)->post_title; ?>" (in <?php echo pll_get_post_language($from_post_id, 'name'); ?>).</p>
          </div>
          <?php
      });
    }
  }

  /**
   * Filter content to be inserted in translation
   *
   * @param string $content html post content
   * @param string $new_lang_slug slug of translated language
   *
   * @return string filtered content
   */

  function filter_content($content, $post, $new_lang_slug) {

    // if media is translatable, replace media in content with translated versions
    if(PLL()->model->options['media_support']) {
      add_filter('polylang_addon_copy_content_filter_content', array(&$this, 'replace_content_img'), 10, 3);
      add_filter('polylang_addon_copy_content_filter_content', array(&$this, 'replace_content_caption'), 10, 3);
      add_filter('polylang_addon_copy_content_filter_content', array(&$this, 'replace_content_gallery'), 10, 3);
      $content = apply_filters( 'polylang_addon_copy_content_filter_content', $content, $post, $new_lang_slug );
    }

    return $content;

  }

  /**
   * Replace images in content with translated versions
   *
   * @param string $content html post content
   * @param obj $post current post object
   * @param string $new_lang_slug slug of translated language
   *
   * @return string filtered content
   */

  function replace_content_img($content, $post, $new_lang_slug) {

    // get all images in content (full <img> tags)
    preg_match_all('/<img[^>]+>/i', $content, $img_array);

    // no images in content
    if(empty($img_array))
      return $content;

    // prepare nicer array structure
    $img_and_meta = array();
    for ($i=0; $i < count($img_array[0]); $i++) {
      $img_and_meta[$i] = array('tag' => $img_array[0][$i]);
    }

    foreach($img_and_meta as $i=>$arr) {

      // get classes
      preg_match('/ class="([^"]*)"/i', $img_array[0][$i], $class_temp);
      $img_and_meta[$i]['class'] = !empty($class_temp) ? $class_temp[1] : '';

      // only proceed if image is created by WordPress (has wp-image-{ID} class)
      if(!strstr($img_and_meta[$i]['class'], 'wp-image-'))
        continue;

      // get the attachment id
      preg_match('/wp-image-(\d+)/i', $img_array[0][$i], $id_temp);

      if(empty($id_temp))
        continue;

      $img_and_meta[$i]['id'] = (int) $id_temp[1];

      $attachment = get_post($img_and_meta[$i]['id']);

      // check if given ID is really attachment (or copied from some other WordPress)
      if(empty($attachment) || $attachment->post_type !== 'attachment')
        continue;

      $img_and_meta[$i]['new_id'] = $this->translate_attachment($img_and_meta[$i]['id'], $new_lang_slug, $post->ID);

      // check if already in right language
      if($img_and_meta[$i]['new_id'] == $img_and_meta[$i]['id']) {
        continue;
      }

      // create new class clause (don't want to risk replacing something else that has "wp-image-")
      $img_and_meta[$i]['new_class'] = preg_replace('/wp-image-(\d+)/i', 'wp-image-' . $img_and_meta[$i]['new_id'], $img_and_meta[$i]['class']);

      // create new tag that is ready to replace the original
      $img_and_meta[$i]['new_tag'] = preg_replace('/class="([^"]*)"/i', 'class="' . $img_and_meta[$i]['new_class'] . '"', $img_and_meta[$i]['tag']);

      // replace data-id="123" from Gutenberg markup
      $img_and_meta[$i]['new_tag'] = preg_replace('/data-id="([^"]*)"/i', 'data-id="' . $img_and_meta[$i]['new_id'] . '"', $img_and_meta[$i]['new_tag']);

      // replace image inside content
      $content = str_replace($img_and_meta[$i]['tag'], $img_and_meta[$i]['new_tag'], $content);

      // replace links to attachment page
      $attachment_permalink = get_permalink( $attachment->ID );
      if(strpos($content, $attachment_permalink) !== false) {
        $new_attachment_permalink = get_permalink( $img_and_meta[$i]['new_id'] );
        $content = str_replace($attachment_permalink, $new_attachment_permalink, $content);

        // replace rel part as well
        $content = str_replace('rel="attachment wp-att-' . $attachment->ID . '"', 'rel="attachment wp-att-' . $img_and_meta[$i]['new_id'] . '"', $content);

      }

      // find HTML comments like <!-- wp:image {"id":123} --> and replace them
      preg_match_all('/<!-- wp:image {[^>]+} -->/i', $content, $comment_array);

      if (empty($comment_array)) {
        continue;
      }

      for ($j=0; $j < count($comment_array[0]); $j++) {

        $comment_tag = $comment_array[0][$j];

        // search for "id":123 pattern
        preg_match('/"id":(\d*)/i', $comment_tag, $comment_tag_id);

        // first match is enough, replace id carefully
        if (isset($comment_tag_id[0]) && isset($comment_tag_id[1]) && $comment_tag_id[1] == $img_and_meta[$i]['id']) {

          $new_id_tag = str_replace($comment_tag_id[1], $img_and_meta[$i]['new_id'], $comment_tag_id[0]);
          $new_comment_tag = str_replace($comment_tag_id[0], $new_id_tag, $comment_tag);
          $content = str_replace($comment_tag, $new_comment_tag, $content);
        }

      }

    }

    return $content;

  }

  /**
   * Replace caption shortcodes in content by setting correct attachment information
   *
   * The <img> tags inside shortcode are replaced already by replace_content_img function
   *
   * @param string $content html post content
   * @param string $new_lang_slug slug of translated language
   *
   * @return string filtered content
   */

  function replace_content_caption($content, $post, $new_lang_slug) {

    preg_match_all('/\[caption(.*?)\](.*?)\[\/caption\]/i', $content, $caption_array);

    // no captions in content
    if(empty($caption_array))
      return $content;

    // prepare nicer array structure
    $caption_and_meta = array();

    for ($i=0; $i < count($caption_array[0]); $i++) {
      $caption_and_meta[$i] = array('shortcode' => $caption_array[0][$i]);
    }

    foreach($caption_and_meta as $i=>$arr) {

      // get ids (comma separated list)
      preg_match('/ id="([^"]*)"/i', $caption_and_meta[$i]['shortcode'], $ids_temp);
      $caption_and_meta[$i]['id'] = !empty($ids_temp) ? $ids_temp[1] : '';


      // only proceed if id is in right format (attachment_{ID})
      if(!strstr($caption_and_meta[$i]['id'], 'attachment_'))
        continue;

      // get the attachment id
      preg_match('/attachment_(\d+)/i', $caption_and_meta[$i]['id'], $attachment_id_temp);

      if(empty($attachment_id_temp))
        continue;

      $caption_and_meta[$i]['attachment_id'] = (int) $attachment_id_temp[1];

      $attachment = get_post($caption_and_meta[$i]['attachment_id']);

      // check if given ID is really attachment (or copied from some other WordPress)
      if(empty($attachment) || $attachment->post_type !== 'attachment')
        continue;

      $caption_and_meta[$i]['new_attachment_id'] = $this->translate_attachment($caption_and_meta[$i]['attachment_id'], $new_lang_slug, $post->ID);

      // create new id clause (don't want to risk replacing something else that has "attachment_")
      $caption_and_meta[$i]['new_id'] = preg_replace('/attachment_(\d+)/i', 'attachment_' . $caption_and_meta[$i]['new_attachment_id'], $caption_and_meta[$i]['id']);

      // create new shortcode that is ready to replace the original
      $caption_and_meta[$i]['new_shortcode'] = preg_replace('/ id="([^"]*)"/i', ' id="' . $caption_and_meta[$i]['new_id'] . '"', $caption_and_meta[$i]['shortcode']);

      // add translation mark in caption by removing original and replacing it with the new attachment's caption
      preg_match('/ \/>(.*?)\[\/caption\]/i', $caption_and_meta[$i]['new_shortcode'], $txt_temp);
      $caption_and_meta[$i]['txt'] = !empty($txt_temp) ? $txt_temp[1] : '';

      if(!empty($caption_and_meta[$i]['txt'])) {

        $new_attachment = get_post($caption_and_meta[$i]['new_attachment_id']);

        $new_caption = !empty($new_attachment->post_excerpt) ? $new_attachment->post_excerpt : '';

        $caption_and_meta[$i]['new_txt'] = apply_filters( 'polylang_addon_copy_content_filter_caption_txt', $new_caption, $new_attachment, $new_lang_slug );

        if(!empty($caption_and_meta[$i]['new_txt'])) {

          // replace the caption in the embedded caption
          $caption_and_meta[$i]['new_shortcode'] = preg_replace('/ \/>(.*?)\[\/caption\]/i', '/>' . $caption_and_meta[$i]['new_txt'] . '[/caption]', $caption_and_meta[$i]['new_shortcode']);
        }

      }

      // replace image inside content
      $content = str_replace($caption_and_meta[$i]['shortcode'], $caption_and_meta[$i]['new_shortcode'], $content);

    }

    return $content;

  }

  /**
   * Replace gallery shortcodes in content by translating attachments
   *
   * @param string $content html post content
   * @param string $new_lang_slug slug of translated language
   *
   * @return string filtered content
   */

  function replace_content_gallery($content, $post, $new_lang_slug) {

    preg_match_all('/\[gallery (.*?)\]/i', $content, $gallery_array);

    // no galleries in content
    if(empty($gallery_array))
      return $content;

    // prepare nicer array structure
    $gallery_and_meta = array();
    for ($i=0; $i < count($gallery_array[0]); $i++) {
      $gallery_and_meta[$i] = array('shortcode' => $gallery_array[0][$i]);
    }

    foreach($gallery_and_meta as $i=>$arr) {

      // get ids (comma separated list)
      preg_match('/ ids="([^"]*)"/i', $gallery_and_meta[$i]['shortcode'], $ids_temp);
      $gallery_and_meta[$i]['ids'] = !empty($ids_temp) ? $ids_temp[1] : '';

      // ids empty, skip this shortcode
      if(empty($gallery_and_meta[$i]['ids']))
        continue;

      // make id list into array for easier handeling
      $gallery_ids_array = explode(',', str_replace(' ', '', $gallery_and_meta[$i]['ids']));

      $gallery_ids_new_array = array();

      // go through all images and get ids of translated attachments
      foreach ($gallery_ids_array as $id) {
        array_push($gallery_ids_new_array, $this->translate_attachment($id, $new_lang_slug, $post->ID));
      }

      $gallery_and_meta[$i]['ids_new'] = implode(',', $gallery_ids_new_array);

      $gallery_and_meta[$i]['shortcode_new'] = preg_replace('/ ids="([^"]*)"/i', ' ids="' . $gallery_and_meta[$i]['ids_new'] . '"', $gallery_and_meta[$i]['shortcode']);

      // replace galleries in content
      $content = str_replace($gallery_and_meta[$i]['shortcode'], $gallery_and_meta[$i]['shortcode_new'], $content);

    }

    // find HTML comments like <!-- wp:gallery {"ids":[123,321]} --> and replace them (outside shortcode)
    preg_match_all('/<!-- wp:gallery {[^>]+} -->/i', $content, $comment_array);

    if (!empty($comment_array)) {
      for ($j=0; $j < count($comment_array[0]); $j++) {

        $comment_tag = $comment_array[0][$j];

        // search for "ids":[123,321] pattern
        preg_match('/"ids":\[(.*)\]/i', $comment_tag, $comment_tag_id);

        // first match is enough, replace ids carefully
        if (isset($comment_tag_id[0]) && isset($comment_tag_id[1])) {

          $old_ids = explode(',', $comment_tag_id[1]);
          $new_ids = array();
          foreach ($old_ids as $id) {
            $new_ids[] = $this->translate_attachment($id, $new_lang_slug, $post->ID);
          }

          $new_id_tag = str_replace($comment_tag_id[1], implode(',', $new_ids), $comment_tag_id[0]);
          $new_comment_tag = str_replace($comment_tag_id[0], $new_id_tag, $comment_tag);
          $content = str_replace($comment_tag, $new_comment_tag, $content);
        }

      }
    }

    return $content;

  }

  /**
   * Filter title
   *
   * Add language informtion in the title (helps translators)
   *
   * @param string $content html post content
   * @param string $new_lang_slug slug of translated language
   *
   * @return string filtered content
   */

  function filter_title($title, $new_lang_slug) {
    return $title .= ' (' . $new_lang_slug . ' translation)';
  }

  /**
   * Copy featured image
   *
   * @param obj post new post object
   * @param int ID of the post we copy from
   * @param string slug of the new translation language
   */

  function copy_featured_image($post, $from_post_id, $new_lang_slug) {
    if(has_post_thumbnail( $from_post_id )) {
      $post_thumbnail_id = get_post_thumbnail_id( $from_post_id );
      if(PLL()->model->options['media_support']) {
        $post_thumbnail_id = $this->translate_attachment($post_thumbnail_id, $new_lang_slug, $post->ID);
      }
      set_post_thumbnail( $post, $post_thumbnail_id );
    }
  }

  /**
   * Copy all attached media
   *
   * Media is translated already from content and featured image but there might be more
   *
   * @param obj post new post object
   * @param int ID of the post we copy from
   * @param string slug of the new translation language
   *
   */

  function copy_attached_media($post, $from_post_id, $new_lang_slug) {

    if(PLL()->model->options['media_support']) {

      $from_lang = pll_get_post_language($from_post_id, 'slug');
        $args = array(
          'post_type' => 'attachment',
          'posts_per_page' => -1,
          'no_found_rows' => true,
          'parent' => $from_post_id,
          'post_status' => null,
          'lang' => $from_lang,
        );
        $attachments = new WP_Query( $args );
      while ( $attachments->have_posts() ) : $attachments->the_post();
        // attachments are translated only once so don't worry about this
        $this->translate_attachment(get_the_ID(), $new_lang_slug, $post->ID);
      endwhile;
      wp_reset_query();
    }

  }

  /**
   * Translate attachment
   *
   * @param int $attachment_id id of the attachment in original language
   * @param string $new_lang new language slug
   * @param int $parent_id id of the parent of the translated attachments (post ID)
   *
   * @return int translated id
   */
  function translate_attachment($attachment_id, $new_lang, $parent_id) {

    global $polylang_copy_content_attachment_cache;

    if (empty($polylang_copy_content_attachment_cache)) {
      $polylang_copy_content_attachment_cache = array();
    }

    // don't create multiple translations of same image on one request
    if (isset($polylang_copy_content_attachment_cache[$attachment_id])) {
      return $polylang_copy_content_attachment_cache[$attachment_id];
    }

    $post = get_post($attachment_id);

    if(empty($post) || is_wp_error($post) || !in_array($post->post_type, array('attachment'))) {
      return $attachment_id;
    }

    $post_id = $post->ID;

    // if there's existing translation, use it
    $existing_translation = pll_get_post($post_id, $new_lang);
    if(!empty($existing_translation)) {
      return $existing_translation; // existing translated attachment
    }

    $post->ID = null; // will force the creation
    $post->post_parent = $parent_id ? $parent_id : 0;

    // Append language code to caption (excerpt)
    $append_str = ' (' . $new_lang . ' translation)';
    $post->post_excerpt = empty($post->post_excerpt) ? '' : $post->post_excerpt . $append_str;

    $tr_id = wp_insert_attachment($post);
    add_post_meta($tr_id, '_wp_attachment_metadata', get_post_meta($post_id, '_wp_attachment_metadata', true));
    add_post_meta($tr_id, '_wp_attached_file', get_post_meta($post_id, '_wp_attached_file', true));

    // copy alternative text to be consistent with title, caption and description copied when cloning the post
    if ($meta = get_post_meta($post_id, '_wp_attachment_image_alt', true)) {
      add_post_meta($tr_id, '_wp_attachment_image_alt', $meta);
    }

    // set language of the attachment
    PLL()->model->post->set_language($tr_id, $new_lang);

    $translations = PLL()->model->post->get_translations($post_id);
    if (!$translations && $lang = PLL()->model->post->get_language($post_id)) {
      $translations[$lang->slug] = $post_id;
    }

    $translations[$new_lang] = $tr_id;
    PLL()->model->post->save_translations($tr_id, $translations);

    // save ids to cache for multiple calls in same request
    $polylang_copy_content_attachment_cache[$attachment_id] = $tr_id;

    return $tr_id; // newly translated attachment
  }

  /**
   * Generate attachment metadata
   *
   * @param int $metadata metadata of the attachment from which we copy informations
   * @param int $attachment_id id of the attachment to copy the metadata to
   */
  function wp_generate_attachment_metadata( $metadata, $attachment_id ) {

    $attachment_lang = PLL()->model->post->get_language($attachment_id);
    $translations = PLL()->model->post->get_translations($attachment_id);

    foreach ($translations as $lang => $tr_id) {
      if (!$tr_id)
        continue;

      if ($attachment_lang->slug !== $lang) {
        update_post_meta($tr_id, '_wp_attachment_metadata', $metadata);
      }
    }

    return $metadata;

  }

}

add_action('plugins_loaded', function () {
    global $polylang_copy_content;
    $polylang_copy_content = new PolylangCopyContent();
} );
