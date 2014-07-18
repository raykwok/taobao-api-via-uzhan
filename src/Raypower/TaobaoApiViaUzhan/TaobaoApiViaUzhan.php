<?php namespace Raypower\TaobaoApiViaUzhan;

use stdClass;
use Config;
use Exception;
use Log;

class TaobaoApiViaUzhan
{
    /**
     * @var
     */
    public $apiUrl;

    /**
     * @var
     */
    public $password;

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
        if (Config::get('taobao-api-via-uzhan::format'))
            $this->format = Config::get('taobao-api-via-uzhan::format');

        //time out for curl
        if (Config::get('taobao-api-via-uzhan::readTimeout'))
            $this->readTimeout = Config::get('taobao-api-via-uzhan::readTimeout');

        //connection time out for curl
        if (Config::get('taobao-api-via-uzhan::connectTimeout'))
            $this->connectTimeout = Config::get('taobao-api-via-uzhan::connectTimeout');

        //password for uzhan
        if (Config::get('taobao-api-via-uzhan::password'))
            $this->password = Config::get('taobao-api-via-uzhan::password');

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
        Log::alert('taobao api via uzhan failed', [
            'apiName' => $apiName,
            'requestUrl' => $requestUrl,
            'errorCode' => $errorCode,
            'responseTxt' => $responseTxt,
        ]);
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
        $result = new stdClass();

        //获取业务参数
        $apiParams = $request->getApiParas();
        $apiParams["method"] = $request->getApiMethodName();
        //appkey
        $apiParams['appkey'] = $this->appkey;
        $apiParams['secret_key'] = $this->secretKey;
        //密码
        $apiParams["xf_password"] = $this->password;
        if (null != $session) {
            $apiParams["session"] = $session;
        }

        //转码
        foreach($apiParams as $apiParamsK => $apiParamsV){
            $apiParams[$apiParamsK] = iconv('UTF-8', 'GBK//IGNORE', $apiParamsV);
        }

        //发起HTTP请求
        try {
            $resp = $this->curl($this->apiUrl, $apiParams);
        } catch (Exception $e) {
            $this->logCommunicationError($apiParams["method"], $this->apiUrl, "HTTP_ERROR_" . $e->getCode(), $e->getMessage());
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
            $this->logCommunicationError($apiParams["method"], $this->apiUrl, "HTTP_RESPONSE_NOT_WELL_FORMED", $resp);
            $result->code = 0;
            $result->msg = "HTTP_RESPONSE_NOT_WELL_FORMED";

            return $result;
        }

        //如果TOP返回了错误码，记录到业务错误日志中
        if (isset($respObject->code)) {
            $this->logCommunicationError($apiParams["method"], $this->apiUrl, "API_RESPONSE_ERROR", $resp);
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
        $appkeys = Config::get('taobao-api-via-uzhan::appkeys');
        $randKey = array_rand($appkeys, 1);
        $randAppkey = $appkeys[$randKey];
        $this->apiUrl = $randAppkey['apiUrl'];
        $this->appkey = $randAppkey['appkey'];
        $this->secretKey = $randAppkey['secretKey'];
        $this->uSiteKey = $randAppkey['uSiteKey'];

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
        $groupAppkeys = Config::get('taobao-api-via-uzhan::groupAppkeys');
        if (!isset($groupAppkeys[$group])) {
            throw new Exception('No available groups!');
        }

        $appkeys = $groupAppkeys[$group];
        $randKey = array_rand($appkeys, 1);
        $randAppkey = $appkeys[$randKey];
        $this->apiUrl = $randAppkey['apiUrl'];
        $this->appkey = $randAppkey['appkey'];
        $this->secretKey = $randAppkey['secretKey'];
        $this->uSiteKey = $randAppkey['uSiteKey'];

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