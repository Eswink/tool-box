<?php

if (!defined('ABSPATH')) {
    exit;
}

// 处理批量导入的 AJAX 请求
add_action('wp_ajax_toolbox_import_keywords', 'toolbox_import_keywords_callback');
function toolbox_import_keywords_callback()
{
    // 管理员权限检查
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permission denied');
    }

    if (!check_ajax_referer('toolbox_import_blocked_keywords_nonce', '_wpnonce')) {
        wp_send_json_error('Permission denied');
    }

    // 验证安全性和文件上传
    if (isset($_FILES['file']['tmp_name']) && !empty($_FILES['file']['tmp_name'])) {
        $file = $_FILES['file']['tmp_name'];

        // 检查文件类型
        $file_info = wp_check_filetype(basename($_FILES['file']['name']));
        if (!$file_info || $file_info['ext'] !== 'txt') {
            wp_send_json_error('请选择正确的文本文件');
        }

        // 验证文件上传
        $upload_overrides = array('test_form' => false);
        $movefile         = wp_handle_upload($_FILES['file'], $upload_overrides);

        if ($movefile && !isset($movefile['error'])) {
            $keywords_file = $movefile['file'];
            $keywords      = file($keywords_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if ($keywords === false) {
                wp_send_json_error('文件读取失败');
            } else {
                // 插入关键词到数据库
                foreach ($keywords as $keyword) {
                    save_blocked_keyword(trim($keyword));
                }
                wp_send_json_success('批量导入成功');
            }
        } else {
            wp_send_json_error('文件上传失败');
        }
    } else {
        wp_send_json_error('未选择文件');
    }
}

// 处理添加关键词的 AJAX 请求
add_action('wp_ajax_toolbox_add_blocked_keyword', 'toolbox_add_blocked_keyword_callback');
function toolbox_add_blocked_keyword_callback()
{
    // 管理员权限检查
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permission denied');
    }

    if (!check_ajax_referer('toolbox_add_blocked_keyword_nonce', '_wpnonce')) {
        wp_send_json_error('Permission denied');
    }

    $new_keyword = isset($_POST['keyword']) ? sanitize_text_field($_POST['keyword']) : '';

    if (!empty($new_keyword)) {
        save_blocked_keyword($new_keyword);
        wp_send_json_success('success');
    } else {
        wp_send_json_error('error');
    }
}

// 处理测试获取 Access Token 的 AJAX 请求
add_action('wp_ajax_toolbox_test_access_token', 'toolbox_test_access_token_callback');
function toolbox_test_access_token_callback()
{
    // 管理员权限检查
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permission denied');
    }
    if (!check_ajax_referer('toolbox_test_access_token_nonce', '_wpnonce')) {
        wp_send_json_error('Permission denied');
    }

    // 获取 Client ID 和 Client Secret
    $client_id     = get_option('toolbox_client_id');
    $client_secret = get_option('toolbox_client_secret');
    // 获取访问令牌
    $access_token = get_access_token($client_id, $client_secret);
    if ($access_token) {
        wp_send_json_success($access_token);
    } else {
        wp_send_json_error('Failed to get Access Token');
    }
}

// 处理删除关键词的 AJAX 请求
add_action('wp_ajax_toolbox_delete_blocked_keyword', 'toolbox_delete_blocked_keyword_callback');
function toolbox_delete_blocked_keyword_callback()
{
    // 管理员权限检查
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permission denied');
    }

    if (!check_ajax_referer('toolbox_delete_blocked_keyword_nonce', '_wpnonce')) {
        wp_send_json_error('Permission denied');
    }

    $id = isset($_POST['id']) ? sanitize_text_field($_POST['id']) : '';

    if (!empty($id)) {
        delete_blocked_keyword($id);
        wp_send_json_success('success');
    } else {
        wp_send_json_error('error');
    }
}

// 处理测试文本审核的 AJAX 请求
add_action('wp_ajax_toolbox_test_content_review', 'toolbox_test_content_review_callback');
function toolbox_test_content_review_callback()
{
    // 管理员权限检查
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permission denied');
    }

    if (!check_ajax_referer('toolbox_test_content_review_nonce', '_wpnonce')) {
        wp_send_json_error('Permission denied');
    }

    // 获取要审核的文本
    $text_to_review = isset($_POST['text']) ? sanitize_text_field($_POST['text']) : '';
    if (empty($text_to_review)) {
        wp_send_json_error('请输入要审核的文本');
    }

    // 获取 Client ID 和 Client Secret
    $client_id     = get_option('toolbox_client_id');
    $client_secret = get_option('toolbox_client_secret');
    // 获取访问令牌
    $access_token = get_access_token($client_id, $client_secret);
    if (!$access_token) {
        wp_send_json_error('Failed to get Access Token');
    }

    // 执行文本审核
    $response_data = perform_content_review($text_to_review, $access_token);

    if (isset($response_data['conclusionType']) && $response_data['conclusionType'] === 1) {
        wp_send_json_success('文本审核通过');
    } else {
        wp_send_json_error('文本审核未通过');
    }
}

// 处理一键清空关键词的 AJAX 请求
add_action('wp_ajax_toolbox_clear_blocked_keywords', 'toolbox_clear_blocked_keywords_callback');
function toolbox_clear_blocked_keywords_callback()
{

    // 管理员权限检查
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permission denied');
    }
    if (!check_ajax_referer('toolbox_clear_blocked_keywords_nonce', '_wpnonce')) {
        wp_send_json_error('Permission denied');
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'blocked_keywords';
    $wpdb->query("TRUNCATE TABLE $table_name");

    wp_die();
}
