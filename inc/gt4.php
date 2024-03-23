<?php

if (!defined('ABSPATH')) {
    exit;
}

function verify_geetest($captcha_id, $captcha_output, $gen_time, $lot_number, $pass_token)
{
    // 极验参数信息
    $captcha_key = get_option('toolbox_geetest_key');
    $api_server  = 'http://gcaptcha4.geetest.com';

    // 生成签名
    $lotnumber_bytes = utf8_encode($lot_number);
    $prikey_bytes    = utf8_encode($captcha_key);
    $sign_token      = hash_hmac('sha256', $lotnumber_bytes, $prikey_bytes);

    // 请求极验二次验证接口，校验用户验证状态
    $query = array(
        "lot_number"     => $lot_number,
        "captcha_output" => $captcha_output,
        "pass_token"     => $pass_token,
        "gen_time"       => $gen_time,
        "sign_token"     => $sign_token,
    );
    $url = $api_server . '/validate' . '?captcha_id=' . $captcha_id;

    $response = wp_remote_post($url, array(
        'method'      => 'POST',
        'timeout'     => 15,
        'httpversion' => '2.0',
        'headers'     => array(),
        'body'        => $query,
    ));

    return json_decode(wp_remote_retrieve_body($response), true);
}

add_filter('preprocess_comment', 'custom_preprocess_comment_filter');

function custom_preprocess_comment_filter($commentdata)
{
    //获取全局的 POST 内容
    $post_data = $_POST;
    if (get_option('toolbox_verify_is_ajax_comment')) {
        if (!isset($post_data['action']) || $post_data['action'] != 'ajax_comment') {
            wp_send_json_error('请输入验证码');
        }
    }

    if (!isset($post_data['captcha']) || empty($post_data['captcha'])) {
        http_response_code(500);

        if (!get_option('toolbox_verify_is_ajax_comment')) {
            wp_die("验证码不能为空");
        } else {
            wp_send_json_error('验证码不能为空');
        }
    }

    // 存在则判断验证码
    $captcha        = $post_data['captcha'];
    $captcha_id     = $captcha['captcha_id'];
    $captcha_output = $captcha['captcha_output'];
    $gen_time       = $captcha['gen_time'];
    $lot_number     = $captcha['lot_number'];

    $pass_token = $captcha['pass_token'];

    $result = verify_geetest($captcha_id, $captcha_output, $gen_time, $lot_number, $pass_token);

    if ($result['status'] == 'success') {
        if ($result['result'] == 'success') {
            // 二次校验成功
            return $commentdata;
        } else {
            if (!get_option('toolbox_verify_is_ajax_comment')) {
                wp_die("验证码不能为空");
            } else {
                wp_send_json_error('验证码校验失败,请重试');
            }
        }
    } else {
        if (!get_option('toolbox_verify_is_ajax_comment')) {
            wp_die("验证码不能为空");
        } else {
            wp_send_json_error('请求异常,请重试');
        }
    }
}
