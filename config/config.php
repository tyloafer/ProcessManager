<?php
/**
 * @配置 manager 设置的一些属性
 * @authors TY loafer (tyloafer@gmail.com)
 * @date    2019-01-11 17:13:17
 */

return [
    /* 是否守护进程方式启动 */
    'daemon' => false,

    /* 单个进程最多处理的请求数 */
    'max_handle_requests' => 1000,

    /* 单个任务最多子进程数 */
    'max_children' => 10,
];