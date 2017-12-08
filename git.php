<?php
/**
 * fanhaobai.com 网站 webhooks 请求脚本
 * @author fanhaobai & i@fanhaobai.com
 * @date 2017/1/20
 */
include './webhook.class.php';

set_time_limit(0);
date_default_timezone_set('Asia/Shanghai');

$config = array(
    'token_field'   =>  'X-Hub-Signature',
    'access_token'  =>  'xxxxxxxxx',
    'bash_path'     =>  '/data/html/git-update/sh/hexo-pull.sh',
);

$webhook = new Webhook($config);
$webhook->run();
