<?php
return [
    /**
     * api response data format, support: json|xml
     * api响应数据格式，目前支持: json|xml
     */
    'format'         => 'json',

    /**
     * CURLOPT_TIMEOUT option for curl
     * curl执行超时时间
     */
    'readTimeout'    => 30,

    /**
     * CURLOPT_CONNECTTIMEOUT option for curl
     * curl请求超时时间
     */
    'connectTimeout' => 10,

    /**
     * 密码
     */
    'password'       => '',

    /**
     * appkeys
     *
     * Example:
     * 'appkeys'        => [
     *    [
     *        'name'      => '',
     *        'apiUrl'    => '',
     *        'appkey'    => '',
     *        'secretKey' => '',
     *        'uSiteKey'   => '',
     *    ],
     * ],
     */
    'appkeys'        => [
        [
            'name'      => '',
            'apiUrl'    => '',
            'appkey'    => '',
            'secretKey' => '',
            'uSiteKey'  => '',
        ],
    ],

    /**
     * uzhan appkeys
     * like appkey example
     */
    'groupAppkeys'   => array(
        'groupA' => [
            [
                'name'      => '',
                'apiUrl'    => '',
                'appkey'    => '',
                'secretKey' => '',
                'uSiteKey'  => '',
            ],
        ],
        'groupB' => [
            [
                'name'      => '',
                'apiUrl'    => '',
                'appkey'    => '',
                'secretKey' => '',
                'uSiteKey'  => '',
            ],
        ],
    ),
];