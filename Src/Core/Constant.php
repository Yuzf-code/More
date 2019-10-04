<?php

namespace More\Src\Core;


class Constant
{
    const HTTP_SERVER = "HTTP_SERVER";

    const WEBSOCKET_SERVER = "WEBSOCKET_SERVER";

    // 用户自定义error handler
    const USER_ERROR_HANDLER = 'ERROR_HANDLER';

    // 用户自定义http dispatch时发生的异常handler
    const HTTP_REQUEST_EXCEPTION_HANDLER = 'HTTP_REQUEST_EXCEPTION_HANDLER';
    // 用户自定义websocket dispatch时发生的异常handler
    const WEBSOCKET_MESSAGE_EXCEPTION_HANDLER = 'WEBSOCKET_MESSAGE_EXCEPTION_HANDLER';
}