<?php
/*
Plugin Name: Staticly
Plugin URI: http://www.mgvmedia.com/
Description: Generates static html files while normal loading.
Version: 1.2
Author: georf
Author URI: http://www.mgvmedia.com
License: GPL3
*/

add_action('admin_menu',                 'MGVmediaStaticly::create_menu');
add_action('comment_post',               'MGVmediaStaticly::check_comment_post', 10, 2);
add_action('plugins_loaded',             'MGVmediaStaticly::perform');
add_action('save_post',                  'MGVmediaStaticly::clean_save_post');
add_action('transition_comment_status',  'MGVmediaStaticly::check_comment_status', 10, 3);


class MGVmediaStaticly {

  public static function check_comment_status($new_status, $old_status, $comment) {
    $show = array('deleted', 'unapproved', 'spam');
    if (!(in_array($old_status, $show) xor !in_array($new_status, $show))) {
      self::clean_comment_status();
    }
  }

  public static function check_comment_post($commentId, $status) {
    if ($status !== 'spam' || $status == 1) {
      self::clean_comment_post();
    }
  }

  public static function clean_save_post($post_id) {
  	$post_status = get_post_status($post_id);
	   if ($post_status == 'auto-draft' && $post_status == "inherit") return;
	   else self::clean_other_save_post($post_id);
  }

  public static function __callStatic($name, $arguments) {
    if (substr($name, 0, 6) != 'clean_') return;
    self::clean();
  }

  public static function create_menu() {
    add_submenu_page('options-general.php', 'Staticly', 'Staticly', 7, "staticly", "MGVmediaStaticly::setting_page");
  }

  public static function setting_page() {
    if (isset($_POST['remove_static_files'])) {
      self::clean();
    }
    echo '
        <div id="postbox-container-1" class="postbox-container">
          <div id="side-sortables" class="meta-box-sortables ui-sortable">
            <div id="submitdiv" class="postbox">
              <h3 class="hndle"><span>Static files</span></h3>
              <div class="inside">
                <form method="post">
                  <input type="hidden" name="remove_static_files" value="1">
                  <button class="button-primary" type="submit">Remove static files</button>
                </form>
              </div>
            </div>
          </div>
        </div>';


    echo '<table class="widefat">';
    echo '<thead>';
    echo '<tr><th>filename</th><th>size</th></tr>';


    echo '</thead><tbody>';
    $output = explode("\n",shell_exec('find '.self::path_to_statics().' -type f -name index.html'));
    foreach ($output as $line) {
      echo '<tr><td>',
        htmlspecialchars(str_replace(self::path_to_statics(), '', $line)),
      '</td><td>',
        filesize($line),
      '</td></tr>';
    }
    echo '</tbody></table>';
  }

  private static function path_to_statics() {
    $root = $_SERVER['DOCUMENT_ROOT'];
    while (!is_file($root.'/wp-config.php')) $root .= '/..';

    $root .= '/static/';
    return $root; 
  }

  public static function clean() {
    $root = self::path_to_statics();
    if (is_dir($root)) exec('rm -rf '.escapeshellarg($root));
  }

  public static function perform() {
    if (is_preview() || is_404() || $_SERVER['REQUEST_METHOD'] === 'POST') return;

    $request_uri = $_SERVER['REQUEST_URI'];
    if (substr($request_uri, strlen($request_uri) -1) == '/'
      && $_SERVER['SCRIPT_NAME'] == '/index.php'
      && $_SERVER['REMOTE_ADDR'] != '127.0.0.1'
      && $_SERVER['SERVER_ADDR'] != $_SERVER['REMOTE_ADDR']
      && !is_user_logged_in()
      && !isset($_COOKIE['comment_author_'.COOKIEHASH])) {
        ob_start('MGVmediaStaticly::save');
    }
  }

  public static function save($content) {
    $root = $_SERVER['DOCUMENT_ROOT'].'/static/';
    $request_uri = $_SERVER['REQUEST_URI'];
    if (!is_dir($root.$request_uri)) {
      mkdir($root.$request_uri, 0755, true);
    }
    $filepath = $root.$request_uri.'index.html';
    file_put_contents($filepath, $content);
    if (filesize($filepath) == 0) {
      unlink($filepath);
    }
    return $content;
  }
}


