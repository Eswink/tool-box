<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * 发起 http post 请求(REST API), 并获取 REST 请求的结果
 * @param string $url
 * @param string $param
 * @return - http response body if succeeds, else false.
 */
function request_post($url = '', $param = '')
{
    // 检查传入的 URL 和参数是否为空
    if (empty($url) || empty($param)) {
        return false;
    }

    // 设置 POST 请求参数
    $args = array(
        'body' => $param,
    );

    // 发送 POST 请求
    $response = wp_remote_post($url, $args);

    // 检查请求是否成功
    if (is_wp_error($response)) {
        $error_message = $response->get_error_message();
        return false;
    } else {
        // 获取响应内容
        $data = wp_remote_retrieve_body($response);
        return $data;
    }
}

// 开放的接口函数，用于执行内容审核检测
function perform_content_review($search_query, $token)
{
    // 检查是否有缓存的审核结果
    $cached_result = get_content_review_result($search_query);
    if ($cached_result !== false) {
        return $cached_result;
    }

    $url = 'https://aip.baidubce.com/rest/2.0/solution/v1/text_censor/v2/user_defined?access_token=' . $token;

    // 发起内容审核检测请求
    $res = request_post($url, array(
        'text' => $search_query,
    ));

    // 解析响应数据
    $response_data = json_decode($res, true);

    // 将结果存储到自定义表中
    save_content_review_result($search_query, $response_data);

    return $response_data;
}

function get_access_token($client_id, $client_secret)
{
    // 设置请求参数
    $url    = 'https://aip.baidubce.com/oauth/2.0/token';
    $params = array(
        'grant_type'    => 'client_credentials',
        'client_id'     => $client_id,
        'client_secret' => $client_secret,
    );

    // 执行 HTTP POST 请求
    $response = wp_remote_post($url, array(
        'body'    => $params,
        'timeout' => 30,
    ));

    // 检查是否发生错误
    if (is_wp_error($response)) {
        $error_message = $response->get_error_message();
        // 在实际应用中，您可能希望以某种方式记录或处理错误信息
        return false;
    }

    // 获取响应数据
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    // 检查响应是否包含访问令牌
    if (isset($data['access_token'])) {
        return $data['access_token'];
    } else {
        // 在实际应用中，您可能希望以某种方式处理获取令牌失败的情况
        return false;
    }
}
