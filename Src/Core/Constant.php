<?php

namespace More\Src\Core;


class Constant
{
    // 服务类型
    const HTTP_SERVER = 'HTTP_SERVER';
    const WEBSOCKET_SERVER = 'WEBSOCKET_SERVER';

    // 请求类型
    const HTTP_REQUEST = 'HTTP_REQUEST';
    const WEBSOCKET_REQUEST = 'WEBSOCKET_REQUEST';

    // 用户自定义error handler
    const USER_ERROR_HANDLER = 'ERROR_HANDLER';

    // 用户自定义http dispatch时发生的异常handler
    const HTTP_REQUEST_EXCEPTION_HANDLER = 'HTTP_REQUEST_EXCEPTION_HANDLER';
    // 用户自定义websocket dispatch时发生的异常handler
    const WEBSOCKET_MESSAGE_EXCEPTION_HANDLER = 'WEBSOCKET_MESSAGE_EXCEPTION_HANDLER';
}