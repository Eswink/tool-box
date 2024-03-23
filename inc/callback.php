<?php

if (!defined('ABSPATH')) {
    exit;
}

// 设置页面部分回调函数
function toolbox_section_callback()
{
    echo '<p>请完成相关设置，相关教程请前往→<a href="https://blog.eswlnk.com/toolbox" target="_blank">「ToolBox | WordPress安全优化工具箱」</a></p>';
}

function toolbox_verify_ajax_key_field_callback()
{
    $button_id = get_option('toolbox_verify_ajax_key');
    echo '<input type="text" id="toolbox_verify_ajax_key" name="toolbox_verify_ajax_key" value="' . esc_attr($button_id) . '" />';
}

function toolbox_verify_button_id_field_callback()
{
    $button_id = get_option('toolbox_verify_button_id');
    echo '<input type="text" id="toolbox_verify_button_id" name="toolbox_verify_button_id" value="' . esc_attr($button_id) . '" />';
}

function toolbox_verify_comment_form_id_field_callback()
{
    $comment_form_id = get_option('toolbox_verify_comment_form_id');
    echo '<input type="text" id="toolbox_verify_comment_form_id" name="toolbox_verify_comment_form_id" value="' . esc_attr($comment_form_id) . '" />';
}

function toolbox_verify_is_ajax_comment_field_callback()
{
    $ajax_enabled = get_option('toolbox_verify_is_ajax_comment');
    $checked      = $ajax_enabled ? 'checked="checked"' : '';
    echo '<label><input type="checkbox" name="toolbox_verify_is_ajax_comment" value="1" ' . $checked . '> 如果为AJAX评论请开启此选项</label>';
}

function toolbox_content_baidu_api_enabled_field_callback()
{
    $enabled = get_option('toolbox_content_baidu_api_enabled');
    $checked = $enabled ? 'checked="checked"' : '';
    echo '<label><input type="checkbox" name="toolbox_content_baidu_api_enabled" value="1" ' . $checked . '> 是否开启第三方搜索文本审核</label>';
}

// 工具箱设置页面内容审核开关
function toolbox_content_review_field_callback()
{
    $content_review_enabled = get_option('toolbox_content_review_enabled');
    $checked                = $content_review_enabled ? 'checked="checked"' : '';
    echo '<label><input type="checkbox" id="toolbox_content_review_enabled" name="toolbox_content_review_enabled" value="1" ' . $checked . '> 开启内容审核检测</label>';
}

function only_logged_search_enabled_field_callback()
{
    $login_required_search = get_option('toolbox_login_required_search');
    $checked               = $login_required_search ? 'checked="checked"' : '';
    echo '<label><input type="checkbox" id="toolbox-login-required-search" name="toolbox_login_required_search" value="1" ' . $checked . '> 仅登录用户可搜索</label>';
}

// Content Search Cache 字段回调函数
function content_search_cache_field_callback()
{
    $toolbox_content_search_cache_time = get_option("toolbox_content_search_cache");
    echo '<input type="number" id="toolbox_content_search_cache_time" name="toolbox_content_search_cache" min="0" step="1" placeholder="建议604800(7天)" value="' . esc_attr($toolbox_content_search_cache_time) . '" />';
    echo '<p class="description">请输入内容搜索缓存时间（以秒为单位）。设置为 0 表示禁用缓存。</p>';
}

// Client ID 字段回调函数
function toolbox_client_id_field_callback()
{
    $client_id = get_option('toolbox_client_id');
    echo '<input type="text" id="toolbox_client_id" name="toolbox_client_id" value="' . esc_attr($client_id) . '" />';
}

// Client Secret 字段回调函数
function toolbox_client_secret_field_callback()
{
    $client_secret = get_option('toolbox_client_secret');
    echo '<input type="text" id="toolbox_client_secret" name="toolbox_client_secret" value="' . esc_attr($client_secret) . '" />';
}

// 在插件列表中增加设置链接
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'toolbox_add_settings_link');
function toolbox_add_settings_link($links)
{
    $settings_link = '<a href="options-general.php?page=toolbox-settings">设置</a>';
    array_unshift($links, $settings_link);
    return $links;
}

// 设置页面字段回调函数
function toolbox_comment_captcha_enabled_field_callback()
{
    $comment_captcha_enabled = get_option('toolbox_comment_captcha_enabled');
    $checked                 = $comment_captcha_enabled ? 'checked="checked"' : '';
    echo '<label><input type="checkbox" name="toolbox_comment_captcha_enabled" value="1" ' . $checked . '> 开启评论验证码</label>';
}

// 极验验证 ID 字段回调函数
function toolbox_geetest_id_field_callback()
{
    $geetest_id = get_option('toolbox_geetest_id');
    echo '<input type="text" name="toolbox_geetest_id" value="' . esc_attr($geetest_id) . '" />';
}

// 极验验证 Key 字段回调函数
function toolbox_geetest_key_field_callback()
{
    $geetest_key = get_option('toolbox_geetest_key');
    echo '<input type="text" name="toolbox_geetest_key" value="' . esc_attr($geetest_key) . '" />';
}
