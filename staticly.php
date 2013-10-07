<?php
/*
Plugin Name: Staticly
Plugin URI: http://www.mgvmedia.com/
Description: Generates static html files while normal loading.
Version: 1.0
Author: georf
Author URI: http://www.mgvmedia.com
License: GPL3
*/


add_action('admin_menu',                 'MGVmediaStaticly::create_menu');
// add_action('clean_attachment_cache',     'MGVmediaStaticly::clean_clean_attachment_cache');
// add_action('clean_object_term_cache',    'MGVmediaStaticly::clean_clean_object_term_cache');
// add_action('clean_page_cache',           'MGVmediaStaticly::clean_clean_page_cache');
// add_action('clean_post_cache',           'MGVmediaStaticly::clean_clean_post_cache');
// add_action('clean_term_cache',           'MGVmediaStaticly::clean_clean_term_cache');
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

    private static function log($hook) {
        $content .= "\n...............................\n";
        $content .= date('Y-m-d H:i:s').' '.$hook."\n";
        $content .= 'POST '.print_r($_POST, true);
        $content .= 'GET '.print_r($_GET, true);
        $content .= 'SERVER '.print_r($_SERVER, true);
        $content .= "DEBUG \n";
        foreach (debug_backtrace() as $line) {
            $arguments = array();
            foreach ($line['args'] as $arg) {
                if (is_array($arg)) {
                    $arguments[] = json_encode($arg);
                } elseif (is_object($arg)) {
                    $arguments[] = get_class($arg);
                } else {
                    $arguments[] = substr(trim($arg), 0, 30);
                }
            }
            $content .= sprintf("%100s:%-6d %-30s %s", $line['file'], $line['line'], $line['function'], implode(',', $arguments))."\n";
        }
        $content .= "\n...............................\n";

        file_put_contents(__DIR__.'/debug.log', $content, FILE_APPEND);
    }

    public static function clean_save_post($post_id) {

	$post_status = get_post_status($post_id);
	if ($post_status == 'auto-draft' && $post_status == "inherit") return;
	else self::clean_other_save_post($post_id);
    }

    public static function __callStatic($name, $arguments) {
        if (substr($name, 0, 6) != 'clean_') return;
        self::log(substr($name, 6));
        self::clean();
    }

    function create_menu() {
        add_submenu_page('options-general.php', 'Staticly', 'Staticly', 7, "staticly", "MGVmediaStaticly::settingPage");
    }

    function settingPage() {
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
        $output = explode("\n",shell_exec('find ../static/ -type f -name index.html'));
        foreach ($output as $line) {
                echo '<tr><td>',
                    htmlspecialchars(substr($line,2)),
                '</td><td>',
                    filesize($line),
                '</td></tr>';
        }
        echo '</tbody></table>';

    }

    function clean() {
        $root = $_SERVER['DOCUMENT_ROOT'];
        while (!is_file($root.'/wp-config.php')) $root .= '/..';

        $root .= '/static/';
        if (is_dir($root))
        exec('rm -rf '.escapeshellarg($root));
    }

    function perform() {
        if (is_preview()) return;

        $request_uri = $_SERVER['REQUEST_URI'];
        if (substr($request_uri, strlen($request_uri) -1) == '/'
        && $_SERVER['SCRIPT_NAME'] == '/index.php'
        && $_SERVER['REMOTE_ADDR'] != '127.0.0.1'
        && $_SERVER['SERVER_ADDR'] != $_SERVER['REMOTE_ADDR']) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $_SERVER['SCRIPT_URI']);
            curl_setopt($ch, CURLOPT_FAILONERROR, false);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);

            $content = curl_exec($ch);

            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode != 200) return;


            $content = file_get_contents($_SERVER['SCRIPT_URI']);
            $root = $_SERVER['DOCUMENT_ROOT'].'/static/';
            if (!is_dir($root.$request_uri)) mkdir($root.$request_uri, 0755, true);
            file_put_contents($root.$request_uri.'index.html', $content);
            echo $content;
            exit();
        }
    }
}


