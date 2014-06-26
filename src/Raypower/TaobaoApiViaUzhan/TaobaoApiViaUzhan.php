<?php namespace Raypower\TaobaoApiViaUzhan;

use stdClass;
use Config;
use Exception;

class TaobaoApiViaUzhan
{
    /**
     * @var
     */
    public $appkey;

    /**
     * @var
     */
    public $secretKey;

    /**
     * @var string
     */
    public $gatewayUrl = "http://gw.api.taobao.com/router/rest";

    /**
     * @var string
     */
    public $format = "json";

    /**
     * @var
     */
    public $connectTimeout;

    /**
     * @var
     */
    public $readTimeout;

    /**
     * 是否打开入参check
     * @var bool
     */
    public $checkRequest = true;

    /**
     * @var string
     */
    protected $signMethod = "md5";

    /**
     * @var string
     */
    protected $apiVersion = "2.0";

    /**
     * @var string
     */
    protected $sdkVersion = "top-sdk-php-20140625";

    /**
     * @var string sitekey for uzhan
     */
    protected $uSiteKey;

    public function __construct()
    {
        //api response data format api响应数据格式
        if (Config::get('taobaoapi::format'))
            $this->format = Config::get('taobaoapi::format');

        //time out for curl
        if (Config::get('taobaoapi::readTimeout'))
            $this->readTimeout = Config::get('taobaoapi::readTimeout');

        //connection time out for curl
        if (Config::get('taobaoapi::connectTimeout'))
            $this->connectTimeout = Config::get('taobaoapi::connectTimeout');

        //set rand appkey
        $this->setRandAppkey();
    }

    /**
     * 保存日志
     * @param string $apiName
     * @param string $requestUrl
     * @param string $errorCode
     * @param string $responseTxt
     */
    protected function logCommunicationError($apiName, $requestUrl, $errorCode, $responseTxt)
    {
        return;
    }

    /**
     * @param      $request
     * @param null $session
     * @return mixed|\SimpleXMLElement|stdClass
     */
    public function execute($request, $session = null)
    {
        $result = new stdClass();
        if ($this->checkRequest) {
            try {
                $request->check();
            } catch (Exception $e) {
                $result->code = $e->getCode();
                $result->msg = $e->getMessage();

                return $result;
            }
        }
        //组装系统参数
        $sysParams["app_key"] = $this->appkey;
        $sysParams["v"] = $this->apiVersion;
        $sysParams["format"] = $this->format;
        $sysParams["sign_method"] = $this->signMethod;
        $sysParams["method"] = $request->getApiMethodName();
        $sysParams["timestamp"] = date("Y-m-d H:i:s");
        $sysParams["partner_id"] = $this->sdkVersion;
        if (null != $session) {
            $sysParams["session"] = $session;
        }

        //获取业务参数
        $apiParams = $request->getApiParas();

        //签名
        $sysParams["sign"] = $this->generateSign(array_merge($apiParams, $sysParams));

        //系统参数放入GET请求串
        $requestUrl = $this->gatewayUrl . "?";
        foreach ($sysParams as $sysParamKey => $sysParamValue) {
            $requestUrl .= "$sysParamKey=" . urlencode($sysParamValue) . "&";
        }
        $requestUrl = substr($requestUrl, 0, -1);

        //发起HTTP请求
        try {
            $resp = $this->curl($requestUrl, $apiParams);
        } catch (Exception $e) {
            $this->logCommunicationError($sysParams["method"], $requestUrl, "HTTP_ERROR_" . $e->getCode(), $e->getMessage());
            $result->code = $e->getCode();
            $result->msg = $e->getMessage();

            return $result;
        }

        //解析TOP返回结果
        $respWellFormed = false;
        if ("json" == $this->format) {
            $respObject = json_decode($resp);
            if (null !== $respObject) {
                $respWellFormed = true;
                foreach ($respObject as $propKey => $propValue) {
                    $respObject = $propValue;
                }
            }
        } else if ("xml" == $this->format) {
            $respObject = @simplexml_load_string($resp);
            if (false !== $respObject) {
                $respWellFormed = true;
            }
        }

        //返回的HTTP文本不是标准JSON或者XML，记下错误日志
        if (false === $respWellFormed) {
            $this->logCommunicationError($sysParams["method"], $requestUrl, "HTTP_RESPONSE_NOT_WELL_FORMED", $resp);
            $result->code = 0;
            $result->msg = "HTTP_RESPONSE_NOT_WELL_FORMED";

            return $result;
        }

        //如果TOP返回了错误码，记录到业务错误日志中
        if (isset($respObject->code)) {
            $this->logCommunicationError($sysParams["method"], $requestUrl, "API_RESPONSE_ERROR", $resp);
        }

        return $respObject;
    }

    /**
     * 设置appkey
     * @param string $value
     * @return $this
     */
    public function setAppkey($value)
    {
        $this->appkey = $value;

        return $this;
    }

    /**
     * 设置secretKey
     * @param string $value
     * @return $this
     */
    public function setSecretKey($value)
    {
        $this->secretKey = $value;

        return $this;
    }

    /**
     * get siteKey
     * @return mixed
     */
    public function getUSiteKey()
    {
        return $this->uSiteKey;
    }

    /**
     * Set rand appkey
     * @return $this
     */
    public function setRandAppkey()
    {
        $appkeys = Config::get('taobaoapi::appkeys');
        $randKey = array_rand($appkeys, 1);
        $randAppkeyStr = $appkeys[$randKey];
        $randAppkey = explode(',', $randAppkeyStr);
        if (isset($randAppkey[0]) && isset($randAppkey[1])) {
            $this->appkey = $randAppkey[0];
            $this->secretKey = $randAppkey[1];
            if (isset($randAppkey[2])) {
                $this->uSiteKey = $randAppkey[2];
            }
        }

        return $this;
    }


    /**
     * Set rand appkeys by group
     * @param $group
     * @return $this
     * @throws Exception
     */
    public function setRandAppkeyByGroup($group)
    {
        $groupAppkeys = Config::get('taobaoapi::groupAppkeys');
        if (!isset($groupAppkeys[$group])) {
            throw new Exception('No available groups!');
        }

        $appkeys = $groupAppkeys[$group];
        $randKey = array_rand($appkeys, 1);
        $randAppkeyStr = $appkeys[$randKey];
        $randAppkey = explode(',', $randAppkeyStr);
        if (isset($randAppkey[0]) && isset($randAppkey[1])) {
            $this->appkey = $randAppkey[0];
            $this->secretKey = $randAppkey[1];
            if (isset($randAppkey[2])) {
                $this->uSiteKey = $randAppkey[2];
            }
        }

        return $this;
    }

    public function curl($url, $postFields = null)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_FAILONERROR, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        if ($this->readTimeout) {
            curl_setopt($ch, CURLOPT_TIMEOUT, $this->readTimeout);
        }
        if ($this->connectTimeout) {
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->connectTimeout);
        }
        //https 请求
        if (strlen($url) > 5 && strtolower(substr($url, 0, 5)) == "https") {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        }

        if (is_array($postFields) && 0 < count($postFields)) {
            $postBodyString = "";
            $postMultipart = false;
            foreach ($postFields as $k => $v) {
                if ("@" != substr($v, 0, 1)) //判断是不是文件上传
                {
                    $postBodyString .= "$k=" . urlencode($v) . "&";
                } else //文件上传用multipart/form-data，否则用www-form-urlencoded
                {
                    $postMultipart = true;
                }
            }
            unset($k, $v);
            curl_setopt($ch, CURLOPT_POST, true);
            if ($postMultipart) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
            } else {
                curl_setopt($ch, CURLOPT_POSTFIELDS, substr($postBodyString, 0, -1));
            }
        }
        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            throw new Exception(curl_error($ch), 0);
        } else {
            $httpStatusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if (200 !== $httpStatusCode) {
                throw new Exception($response, $httpStatusCode);
            }
        }
        curl_close($ch);

        return $response;
    }
}