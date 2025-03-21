<?php

declare(strict_types=1);

const CHOIR_PSR_HTTP_VERSION = '1.0.3';

// Choir TCP 连接状态
const CHOIR_TCP_INITIAL = 0;
const CHOIR_TCP_CONNECTING = 1;
const CHOIR_TCP_ESTABLISHED = 2;
const CHOIR_TCP_CLOSING = 4;
const CHOIR_TCP_CLOSED = 8;

// Choir TCP 错误码
const CHOIR_TCP_SEND_FAILED = 2;

const CHOIR_WS_CLOSE_NORMAL = 1000;
const CHOIR_WS_CLOSE_GOING_AWAY = 1001;
const CHOIR_WS_CLOSE_PROTOCOL_ERROR = 1002;
const CHOIR_WS_CLOSE_DATA_ERROR = 1003;
const CHOIR_WS_CLOSE_STATUS_ERROR = 1005;
const CHOIR_WS_CLOSE_ABNORMAL = 1006;
const CHOIR_WS_CLOSE_MESSAGE_ERROR = 1007;
const CHOIR_WS_CLOSE_POLICY_ERROR = 1008;
const CHOIR_WS_CLOSE_MESSAGE_TOO_BIG = 1009;
const CHOIR_WS_CLOSE_EXTENSION_MISSING = 1010;
const CHOIR_WS_CLOSE_SERVER_ERROR = 1011;
const CHOIR_WS_CLOSE_TLS = 1015;
