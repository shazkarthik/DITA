<?php

/**
 * Plugin Name: DITA
 * Plugin URI: http://www.medialeg.ch
 * Description: ...coming soon...
 * Author: Reto Schneider
 * Version: 1.0
 * Author URI: http://www.medialeg.ch
 */

require_once 'vendors/php-csv-utils-0.3/Csv/Dialect.php';
require_once 'vendors/php-csv-utils-0.3/Csv/Writer.php';

libxml_use_internal_errors(true);

function dita_delete($directory)
{
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($files AS $file) {
        if ($file->isDir()) {
            rmdir($file->getRealPath());
        } else {
            unlink($file->getRealPath());
        }
    }
}

function dita_uasort($one, $two)
{
    preg_match("|[a-zA-Z]|", $one['string'], $match);
    $one = $match[0];
    preg_match("|[a-zA-Z]|", $two['string'], $match);
    $two = $match[0];
    if ($one === $two) {
        return 0;
    }
    return ($one < $two)? -1: 1;
}

function dita_get_file($items)
{
    array_unshift($items, 'files');
    array_unshift($items, rtrim(plugin_dir_path(__FILE__), '/'));
    return implode(DIRECTORY_SEPARATOR, $items);
}

function dita_get_directory($items)
{
    $directory = dita_get_file($items);
    if (!@is_dir($directory)) {
        @mkdir($directory, 0777, true);
    }
    return $directory;
}

function dita_get_prefix()
{
    return sprintf('%sdita_', $GLOBALS['wpdb']->prefix);
}

// function dita_get_shortcodes($contents)
// {
// }

function dita_get_items($xml)
{
    $items = array();

    foreach (@simplexml_load_string($xml)->xpath('//bookmap') AS $key => $value) {
        try {
            $item = array();

            $item['title'] = (string) array_pop($value->xpath('title'));

            $item['abstract'] = (string) array_pop($value->xpath('abstract'));

            $item['chapter'] = array();
            foreach ($value->xpath('//chapter') AS $chapter) {
                $item['chapter'][] = (string) array_pop($chapter->xpath('@href'));
                $item['chapter'][] = array(
                    'topichead' => (string) (array_pop($chapter->xpath('topichead/@navtitle'))),
                    'topicref' => (string) (array_pop($chapter->xpath('topicref/@href'))),
                );
            }

            $items[] = $item;
        } catch (Exception $exception) {
            return array(sprintf('dita_get_items() - %s', $exception->getMessage()), array());
        }
    }
    echo "<br/>";
    return array(array(), $items);
}

function dita_init()
{
    if (!session_id()) {
        session_start();
    }
    ob_start();
    if (get_magic_quotes_gpc()) {
        $temporary = array(&$_GET, &$_POST, &$_COOKIE, &$_REQUEST);
        while (list($key, $value) = each($temporary)) {
            foreach ($value AS $k => $v) {
                unset($temporary[$key][$k]);
                if (is_array($v)) {
                    $temporary[$key][stripslashes($k)] = $v;
                    $temporary[] = &$temporary[$key][stripslashes($k)];
                } else {
                    $temporary[$key][stripslashes($k)] = stripslashes($v);
                }
            }
        }
        unset($temporary);
    }
    add_action('wp_enqueue_scripts', 'dita_scripts');
    add_action('wp_enqueue_scripts', 'dita_styles');
}

function dita_admin_init()
{
    add_action('admin_print_scripts', 'dita_scripts');
    add_action('admin_print_styles', 'dita_styles');
}

function dita_scripts()
{
    wp_enqueue_script(
        'all_js', sprintf('%s/dita.js', plugins_url('/dita')), array('jquery')
    );
}

function dita_styles()
{
    wp_enqueue_style(
        'all_css', sprintf('%s/dita.css', plugins_url('/dita'))
    );
}

function dita_admin_menu()
{
    add_menu_page(
        'DITA',
        'DITA',
        'manage_options',
        '/dita',
        'dita_dashboard',
        ''
    );
    add_submenu_page(
        '/dita',
        'F.A.Q',
        'F.A.Q',
        'manage_options',
        '/dita/faq',
        'dita_faq'
    );
}

function dita_flashes()
{
    ?>
    <?php if (!empty($_SESSION['dita']['flashes'])) : ?>
        <?php foreach ($_SESSION['dita']['flashes'] AS $key => $value) : ?>
            <div class="<?php echo $key; ?>">
                <p><strong><?php echo $value; ?></strong></p>
            </div>
        <?php endforeach; ?>
        <?php $_SESSION['dita']['flashes'] = array(); ?>
    <?php endif; ?>
    <?php
}

function dita_dashboard()
{
    if (!current_user_can('manage_options')) {
        wp_die('You do not have permissions to access this page.');
    }
    $action = $_REQUEST['action']? $_REQUEST['action']: '';
    ?>
    <div class="dita wrap">
        <?php
        switch ($action) {
        case 'upload':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $id = get_the_ID();
                list($errors, $items) = dita_get_items(
                    file_get_contents($_FILES['file_1']['tmp_name']));
                    for($i=0; $i<count($_FILES['file_2']['name']); $i++) {
                        $a = str_replace('<', '&lt;', file_get_contents($_FILES['file_2']['tmp_name'][$i]));
                    }
// To insert POST
/*                $my_post = array();
                $my_post['post_title']    = 'Table of contents';
                $my_post['post_content']  = 'My Post';
                $my_post['post_status']   = 'publish';
                $my_post['post_author']   = 1;
                $my_post['post_category'] = array(0);
                wp_insert_post($my_post);
*/
                if ($errors) {
                    $_SESSION['dita']['flashes'] = array(
                        'error' => 'The document was not uploaded successfully. Please try again.',
                    );
                    ?>
                    <meta content="0;url=<?php echo admin_url('admin.php?action=upload&page=dita'); ?>"http-equiv="refresh" >
                    <?php
                    die();
                }
                if (!$items) {
                    $_SESSION['dita']['flashes'] = array(
                        'error' => 'The document was not uploaded successfully. Please try again.',
                    );
                    ?>
                    <meta content="0;url=<?php echo admin_url('admin.php?action=upload&page=dita'); ?>"http-equiv="refresh" >
                    <?php
                    die();
                }
                $GLOBALS['wpdb']->insert(
                    sprintf('%sdocuments', dita_get_prefix()),
                    array(
                        'name' => $_FILES['file_1']['name'],
                    )
                );
                $document_id = $GLOBALS['wpdb']->insert_id;
                dita_get_directory(array($document_id));
                copy(
                    $_FILES['file_1']['tmp_name'],
                    dita_get_file(array($document_id, $_FILES['file_1']['name']))
                );
                $_SESSION['dita']['flashes'] = array(
                    'updated' => 'The document was uploaded successfully.',
                );
                ?>
                <meta content="0;url=<?php echo admin_url('admin.php?action=&page=dita'); ?>"http-equiv="refresh">
                <?php
                die();
            } else {
                ?>
                <h1>Documents - Upload</h1>
                <form
                    action="<?php echo admin_url('admin.php?action=upload&page=dita'); ?>"
                    enctype="multipart/form-data"
                    method="post"
                    >
                    <table class="bordered widefat wp-list-table">
                        <tr>
                            <td class="label">
                                <label for="file_1">ditamap</label>
                            </td>
                            <td><input id="file" name="file_1" type="file"></td>
                        </tr>
                        <tr>
                            <td class="label">
                                <label for="file_2">dita</label>
                            </td>
                            <td><input id="myfile" name="file_2[]" type="file" multiple></td>
                        </tr>
                    </table>
                    <p class="submit"><input class="button-primary" type="submit" value="Submit"></p>
                </form>
                <?php
            }
            break;
        default:
            $documents = $GLOBALS['wpdb']->get_results(
                sprintf('SELECT * FROM `%sdocuments` ORDER BY `id` DESC', dita_get_prefix()),
                ARRAY_A
            );
            ?>
            <h1>
                Documents
                <a
                    class="page-title-action"
                    href="<?php echo admin_url('admin.php?action=upload&page=dita'); ?>"
                    >Upload</a>
            </h1>
            <?php dita_flashes(); ?>
            <?php if ($documents) : ?>

            <?php else: ?>
                <div class="error">
                    <p><strong>There are no documents in the database.</strong></p>
                </div>
            <?php endif; ?>
            <?php
            break;
        }
        ?>
        </div>
    </div>
    <?php
}

// function dita_faq()
// {
// }

// function dita_save_post($page_id)
// {
// }

register_activation_hook(__FILE__, 'dita_register_activation_hook');
register_deactivation_hook(__FILE__, 'dita_register_deactivation_hook');

add_action('init', 'dita_init');

add_action('admin_init', 'dita_admin_init');
add_action('admin_menu', 'dita_admin_menu');
add_action('add_meta_boxes', 'dita_add_meta_boxes');
add_action('save_post', 'dita_save_post');
add_action('wp_head', 'dita_wp_head', 90);

add_filter('the_content', 'dita_the_content', 90);
