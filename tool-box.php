<?php
/*
Plugin Name: Tool Box
Plugin URI: https://blog.eswlnk.com/toolbox
Description: HotSpotAI Steam 万能工具箱
Version: 1.1
Author: Eswlnk
Author URI: https://blog.eswlnk.com
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once plugin_dir_path(__FILE__) . '/inc/baidu-content.php';
require_once plugin_dir_path(__FILE__) . '/inc/gt4.php';
require_once plugin_dir_path(__FILE__) . '/inc/callback.php';
require_once plugin_dir_path(__FILE__) . '/inc/ajax.php';

function toolbox_modify_search($query)
{
    if (!is_admin() && $query->is_search) {
        $login_required_search = get_option('toolbox_login_required_search');
        if ($login_required_search && !is_user_logged_in()) {
            // 设置错误消息、
            $site_title = get_bloginfo('name');
            $message    = '只有登录用户才能进行搜索，请先<a href="' . esc_url(wp_login_url()) . '">登录</a>。';
            $title      = `搜索前请登录 - $site_title`;
            $args       = array(
                'response'       => 404,
                'back_link'      => false, // 不显示返回链接
                 'text_direction' => 'ltr', // 文字书写方向，ltr 或 rtl
            );
            // 使用 wp_die 显示错误消息并终止页面加载
            wp_die($message, $title, $args);
        }

        $search_query           = $query->get('s');
        $content_review_enabled = get_option('toolbox_content_review_enabled');
        if ($content_review_enabled) {
            $blocked_keywords = get_specific_blocked_keyword($search_query);
            if (!empty($blocked_keywords)) {
                status_header(404);
                nocache_headers();
                include get_query_template('404');
                exit();
            }

            if (get_option('toolbox_content_baidu_api_enabled')) {
                $client_id     = get_option('toolbox_client_id');
                $client_secret = get_option('toolbox_client_secret');
                $access_token  = get_access_token($client_id, $client_secret);
                $response_data = perform_content_review($search_query, $access_token);
                if (isset($response_data['conclusionType']) && $response_data['conclusionType'] !== 1) {
                    status_header(404);
                    nocache_headers();
                    include get_query_template('404');
                    exit();
                }
            }
        }
    }
    return $query;
}
add_filter('pre_get_posts', 'toolbox_modify_search');

// 添加设置页面到设置菜单
add_action('admin_menu', 'toolbox_add_settings_page');
function toolbox_add_settings_page()
{
    add_options_page('工具箱', '工具箱', 'manage_options', 'toolbox-settings', 'toolbox_settings_page');
}

// 生成 blocked_keywords 表格
function toolbox_blocked_keywords_table($page_number, $per_page)
{
    $offset           = ($page_number - 1) * $per_page;
    $blocked_keywords = get_blocked_keywords_table($per_page, $offset);
    ?>
    <button class="button" id="toolbox-clear-keywords" style="margin-bottom:20px">一键清空</button>
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th scope="col" class="manage-column column-name">id</th>
                <th scope="col" class="manage-column column-name">关键词</th>
                <th scope="col" class="manage-column column-actions">操作</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($blocked_keywords as $keyword) { ?>
                <tr>
                <td><?php echo esc_html($keyword->id); ?></td>
                    <td><?php echo esc_html($keyword->keyword); ?></td>
                    <td>
                        <button class="button btn_deleteBlockedKeyword" data_id="<?php echo esc_attr($keyword->id); ?>" keyword="<?php echo esc_attr($keyword->keyword); ?>">删除</button>
                    </td>
                </tr>
            <?php } ?>
            <tr>
            <td>空</td>
                <td>
                    <input type="text" id="toolbox-new-keyword" name="toolbox-new-keyword" placeholder="添加新关键词">
                </td>
                <td>
                    <button class="button" id="toolbox-add-keyword">添加</button>
        <button id="toolbox-import-keywords" class="button">批量导入</button><input type="file" id="toolbox-import-keywords-file" accept=".txt">
                </td>
            </tr>
            <tr>
                <td colspan="2">
                    <?php
$total_keywords = count_blocked_keywords();
    $total_pages    = ceil($total_keywords / $per_page) - 1;
    $current_page   = max(1, $page_number);
    echo paginate_links(array(
        'base'      => add_query_arg('paged', '%#%'),
        'format'    => '',
        'prev_text' => __('&laquo;'),
        'next_text' => __('&raquo;'),
        'total'     => $total_pages,
        'current'   => $current_page,
    ));
    ?>
                </td>
            </tr>
        </tbody>
    </table>
    <?php
}

function get_blocked_keywords_table($per_page = 10, $offset = 0)
{
    global $wpdb;
    $table_name       = $wpdb->prefix . 'blocked_keywords';
    $query            = $wpdb->prepare("SELECT * FROM $table_name LIMIT %d OFFSET %d", $per_page, $offset);
    $blocked_keywords = $wpdb->get_results($query);
    return $blocked_keywords ? $blocked_keywords : array();
}

// 获取屏蔽关键词总数
function count_blocked_keywords()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'blocked_keywords';
    $count      = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
    return $count ? $count : 0;
}

// 设置页面内容
function toolbox_settings_page()
{

    $current_page = isset($_GET['paged']) ? intval($_GET['paged']) : 1;
    $per_page     = 10; // 每页显示的关键词数量

    ?>
    <div class="wrap">
        <h2>工具箱设置</h2>

        <form method="post" action="options.php">
            <?php settings_fields('toolbox_settings_group'); ?>
            <?php do_settings_sections('toolbox-settings'); ?>
            <?php submit_button('保存设置'); ?>
            <input type="hidden" name="save_blocked_keywords" value="1"> <!-- 新增的隐藏字段 -->
        </form>

        <!-- 添加测试按钮 -->
        <h2>测试获取 Access Token</h2>
        <button id="toolbox-test-access-token" class="button">测试获取 Access Token</button>
        <div id="toolbox-access-token-result"></div>

        <!-- 添加测试文本审核的按钮和输入框 -->
        <h2>测试文本审核</h2>
        <input type="text" id="toolbox-test-text" name="toolbox-test-text" placeholder="输入要审核的文本">
        <button id="toolbox-test-content-review" class="button">测试文本审核</button>
        <div id="toolbox-content-review-result"></div>

        <h2>屏蔽关键词列表</h2>
        <?php toolbox_blocked_keywords_table($current_page, $per_page); ?>
    </div>

    <script>
    jQuery(document).ready(function ($) {
        // 删除关键词的函数
        $('.btn_deleteBlockedKeyword').on('click', function () {
            var id = $(this).attr('data_id');
            var keyword = $(this).attr('keyword');
            if (confirm('确定要删除关键词 "' + keyword + '" 吗？')) {
                $.ajax({
                    type: 'POST',
                    url: ajaxurl,
                    data: {
                        action: 'toolbox_delete_blocked_keyword',
                        id: id,
                        _wpnonce: '<?php echo wp_create_nonce('toolbox_delete_blocked_keyword_nonce'); ?>'
                    },
                    success: function (response) {
                        // 刷新页面
                        location.reload();
                    }
                });
            }
        })

        // 当获取 Access Token 按钮点击时执行操作
        $('#toolbox-test-access-token').on('click', function () {
            $.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                type: 'POST',
                data: {
                    action: 'toolbox_test_access_token',
                    _wpnonce: '<?php echo wp_create_nonce('toolbox_test_access_token_nonce'); ?>'
                },
                success: function (response) {
                    $('#toolbox-access-token-result').html('<p><strong>Access Token:</strong> ' + JSON.stringify(response) + '</p>');
                },
                error: function (xhr, status, error) {
                    $('#toolbox-access-token-result').html('<p style="color: red;">获取 Access Token 失败</p>');
                }
            });
        });

        // 当测试文本审核按钮点击时执行操作
        $('#toolbox-test-content-review').on('click', function () {
            var textToReview = $('#toolbox-test-text').val();
            $.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                type: 'POST',
                data: {
                    action: 'toolbox_test_content_review',
                    text: textToReview,
                    _wpnonce: '<?php echo wp_create_nonce('toolbox_test_content_review_nonce'); ?>'
                },
                success: function (response) {
                    $('#toolbox-content-review-result').html('<p><strong>审核结果:</strong> ' + JSON.stringify(response) + '</p>');
                },
                error: function (xhr, status, error) {
                    $('#toolbox-content-review-result').html('<p style="color: red;">测试文本审核失败</p>');
                }
            });
        });

        // 添加关键词按钮点击时执行操作
        $('#toolbox-add-keyword').on('click', function () {
            var newKeyword = $('#toolbox-new-keyword').val().trim();
            if (newKeyword !== '') {
                jQuery.ajax({
                    type: 'POST',
                    url: ajaxurl,
                    data: {
                        action: 'toolbox_add_blocked_keyword',
                        keyword: newKeyword,
                        _wpnonce: '<?php echo wp_create_nonce('toolbox_add_blocked_keyword_nonce'); ?>'
                    },
                    success: function (response) {
                        // 刷新页面
                        location.reload();
                    }
                });
            }
        });

        // 清空关键词按钮点击时执行操作
        $('#toolbox-clear-keywords').on('click', function () {
            if (confirm('确定要清空所有关键词吗？')) {
                jQuery.ajax({
                    type: 'POST',
                    url: ajaxurl,
                    data: {
                        action: 'toolbox_clear_blocked_keywords',
                        _wpnonce: '<?php echo wp_create_nonce('toolbox_clear_blocked_keywords_nonce'); ?>'
                    },
                    success: function (response) {
                        // 刷新页面
                        location.reload();
                    }
                });
            }
        });

        // 导入关键词按钮点击时执行操作
        $('#toolbox-import-keywords').on('click', function () {
            var file_data = $('#toolbox-import-keywords-file').prop('files')[0];
            var form_data = new FormData();
            form_data.append('file', file_data);
            form_data.append('action', 'toolbox_import_keywords');
            form_data.append('_wpnonce', '<?php echo wp_create_nonce('toolbox_import_blocked_keywords_nonce'); ?>');
            $.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                type: 'POST',
                contentType: false,
                processData: false,
                data: form_data,
                success: function (response) {
                    alert(response.data);
                    location.reload();
                },
                error: function (xhr, status, error) {
                    alert('批量导入失败');
                }
            });
        });
    });
</script>
    <?php
}

// 初始化设置
add_action('admin_init', 'toolbox_initialize_settings');
function toolbox_initialize_settings()
{
    if (isset($_POST['save_blocked_keywords'])) {
        $blocked_keywords = explode("\n", sanitize_textarea_field($_POST['toolbox_blocked_keywords']));
        // 保存关键词到数据库
        foreach ($blocked_keywords as $keyword) {
            save_blocked_keyword(trim($keyword));
        }
    }
    register_setting('toolbox_settings_group', 'toolbox_blocked_keywords', 'sanitize_textarea_field');

    register_setting('toolbox_settings_group', 'toolbox_login_required_search');

    register_setting('toolbox_settings_group', 'toolbox_content_review_enabled');
    register_setting('toolbox_settings_group', 'toolbox_content_baidu_api_enabled');

    register_setting('toolbox_settings_group', 'toolbox_content_search_cache');

    register_setting('toolbox_settings_group', 'toolbox_client_id');
    register_setting('toolbox_settings_group', 'toolbox_client_secret');
    register_setting('toolbox_settings_group', 'toolbox_comment_captcha_enabled');
    register_setting('toolbox_settings_group', 'toolbox_geetest_id');
    register_setting('toolbox_settings_group', 'toolbox_geetest_key');
    register_setting('toolbox_settings_group', 'toolbox_verify_button_id');
    register_setting('toolbox_settings_group', 'toolbox_verify_comment_form_id');
    register_setting('toolbox_settings_group', 'toolbox_verify_is_ajax_comment');
    register_setting('toolbox_settings_group', 'toolbox_verify_ajax_key');

    add_settings_section('toolbox_settings_section', '屏蔽关键词和内容审核', 'toolbox_section_callback', 'toolbox-settings');

    add_settings_field('content_review_field', '关键词屏蔽', 'toolbox_content_review_field_callback', 'toolbox-settings', 'toolbox_settings_section');
    add_settings_field('only_logged_search_enabled_field', '仅登录用户可搜索', 'only_logged_search_enabled_field_callback', 'toolbox-settings', 'toolbox_settings_section');
    add_settings_field('content_search_cache_field', '搜索&审核缓存', 'content_search_cache_field_callback', 'toolbox-settings', 'toolbox_settings_section');

    add_settings_field('content_baidu_api_enabled_field', '内容审核检测', 'toolbox_content_baidu_api_enabled_field_callback', 'toolbox-settings', 'toolbox_settings_section');
    add_settings_field('client_id_field', '百度文本内容审核 API Key', 'toolbox_client_id_field_callback', 'toolbox-settings', 'toolbox_settings_section');
    add_settings_field('client_secret_field', '百度文本内容审核 Secret Key', 'toolbox_client_secret_field_callback', 'toolbox-settings', 'toolbox_settings_section');
    add_settings_field('comment_captcha_enabled_field', '是否开启评论验证码', 'toolbox_comment_captcha_enabled_field_callback', 'toolbox-settings', 'toolbox_settings_section');
    add_settings_field('geetest_id_field', '极验验证 ID', 'toolbox_geetest_id_field_callback', 'toolbox-settings', 'toolbox_settings_section');
    add_settings_field('geetest_key_field', '极验验证 Key', 'toolbox_geetest_key_field_callback', 'toolbox-settings', 'toolbox_settings_section');
    add_settings_field('button_id_field', '绑定评论按钮ID', 'toolbox_verify_button_id_field_callback', 'toolbox-settings', 'toolbox_settings_section');
    add_settings_field('comment_form_id_field', '绑定评论表单ID', 'toolbox_verify_comment_form_id_field_callback', 'toolbox-settings', 'toolbox_settings_section');
    add_settings_field('comment_is_ajax_comment_field', '评论是否为AJAX', 'toolbox_verify_is_ajax_comment_field_callback', 'toolbox-settings', 'toolbox_settings_section');
    add_settings_field('comment_ajax_key_field', 'AJAX关键字', 'toolbox_verify_ajax_key_field_callback', 'toolbox-settings', 'toolbox_settings_section');
}

function save_content_review_result($text, $result)
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'content_review_results';
    $wpdb->insert(
        $table_name,
        array(
            'text_to_review' => $text,
            'result'         => serialize($result), // 将结果序列化以便存储
        )
    );
}

// 从自定义表中获取审核结果，添加对象缓存支持
function get_content_review_result($text)
{
    // 尝试从缓存中获取审核结果
    $cached_result = wp_cache_get('content_review_result_' . md5($text), 'content_review_results');
    if ($cached_result !== false) {
        return unserialize($cached_result); // 直接返回缓存的结果
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'content_review_results';
    $result     = $wpdb->get_var($wpdb->prepare("SELECT result FROM $table_name WHERE text_to_review = %s", $text));

    $cache_time = intval(get_option("toolbox_content_search_cache"));

    if ($result !== false) {
        wp_cache_set('content_review_result_' . md5($text), $result, 'content_review_results', $cache_time);
    }

    return $result ? unserialize($result) : false; // 返回反序列化的结果
}

// 在插件激活时创建表格
register_activation_hook(__FILE__, 'toolbox_create_review_table');

function toolbox_create_review_table()
{
    global $wpdb;
    $table_name      = $wpdb->prefix . 'content_review_results';
    $charset_collate = $wpdb->get_charset_collate();
    $sql             = "CREATE TABLE IF NOT EXISTS $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        text_to_review text NOT NULL,
        result text NOT NULL,
        PRIMARY KEY  (id),
        INDEX text_to_review_index (text_to_review(191))
    ) $charset_collate;";
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}

register_activation_hook(__FILE__, 'toolbox_create_block_table');
function toolbox_create_block_table()
{
    global $wpdb;
    $table_name      = $wpdb->prefix . 'blocked_keywords'; // 正确的表名
    $charset_collate = $wpdb->get_charset_collate();
    $sql             = "CREATE TABLE IF NOT EXISTS $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        keyword text NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}

// 修改查询屏蔽关键词的方式，使用缓存查询
function get_blocked_keywords()
{
    // 尝试从缓存中获取屏蔽关键词
    $cached_keywords = wp_cache_get('blocked_keywords', 'toolbox');
    if ($cached_keywords !== false) {
        return $cached_keywords;
    }

    global $wpdb;
    $table_name    = $wpdb->prefix . 'blocked_keywords'; // 正确的表名
    $blocked_words = $wpdb->get_col("SELECT keyword FROM $table_name");

    $cache_time = intval(get_option("toolbox_content_search_cache"));

    if ($blocked_words !== false) {
        wp_cache_set('blocked_keywords', $blocked_words, 'toolbox', $cache_time);
    }

    return $blocked_words ? $blocked_words : array();
}

function get_specific_blocked_keyword($keyword)
{
    if (empty($keyword)) {
        return false; // 如果关键词为空，则直接返回false
    }

    // 尝试从缓存中获取指定关键词
    $cached_keyword = wp_cache_get('specific_blocked_keyword_' . $keyword, 'toolbox');
    if ($cached_keyword !== false) {
        return $cached_keyword;
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'blocked_keywords'; // 正确的表名
    $keyword    = sanitize_text_field($keyword); // 确保关键词是安全的

    // 查询指定的关键词
    $blocked_word = $wpdb->get_var($wpdb->prepare("SELECT keyword FROM $table_name WHERE keyword LIKE %s", '%' . $keyword . '%'));

    $cache_time = intval(get_option("toolbox_content_search_cache"));

    if ($blocked_word !== null) {
        wp_cache_set('specific_blocked_keyword_' . $keyword, $blocked_word, 'toolbox', $cache_time);
    }

    return $blocked_word !== null ? $blocked_word : false;
}

// 将关键词存储到自定义表中
function save_blocked_keyword($keyword)
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'blocked_keywords';
    $wpdb->insert(
        $table_name,
        array(
            'keyword' => $keyword,
        )
    );
}

// 删除关键词
function delete_blocked_keyword($id)
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'blocked_keywords';
    $wpdb->delete($table_name, array('id' => $id));
}

// 在插件卸载时删除表格
register_deactivation_hook(__FILE__, 'toolbox_delete_custom_table');
function toolbox_delete_custom_table()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'content_review_results';
    $sql        = "DROP TABLE IF EXISTS $table_name";
    $wpdb->query($sql);
}

// 加入验证码文件
function register_and_enqueue_comment_script()
{
    if (comments_open() && get_option('toolbox_comment_captcha_enabled') && !is_home()) {
        wp_register_script('gt4', plugins_url('/', __FILE__) . 'assets/js/gt4.js', array('jquery'), '1.0', true);
        wp_register_script('init', plugins_url('/', __FILE__) . 'assets/js/init.js', array('jquery'), '1.0', true);
        wp_register_script('filter', plugins_url('/', __FILE__) . 'assets/js/filter.js', array('jquery'), '1.0', true);
        wp_enqueue_script('gt4');
        wp_enqueue_script('init');
        wp_enqueue_script('filter');
        wp_localize_script('gt4', 'comment_verify', array(
            "ajaxurl"         => admin_url('admin-ajax.php'),
            "captchaId"       => get_option('toolbox_geetest_id'),
            "buttonId"        => get_option('toolbox_verify_button_id'),
            "formId"          => get_option('toolbox_verify_comment_form_id'),
            "is_ajax_comment" => get_option('toolbox_verify_is_ajax_comment') ? 1 : 0,
            "ajax_key"        => get_option('toolbox_verify_ajax_key'),
        ));
    }

}
add_action('wp_enqueue_scripts', 'register_and_enqueue_comment_script');
