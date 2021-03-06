<?php

namespace BB\DurianBundle\Payment;

use BB\DurianBundle\Exception\PaymentException;
use BB\DurianBundle\Exception\PaymentConnectionException;

/**
 * 閃入寶支付
 */
class ShanRuBao extends PaymentBase
{
    /**
     * 支付時要傳給平台驗證的參數
     *
     * @var array
     */
    protected $requestData = [
        'uid' => '', // 商號
        'price' => '', // 支付金額，單位:元
        'istype' => '', // 支付通道
        'notify_url' => '', // 通知回調網址
        'return_url' => '', // 跳轉網址
        'orderid' => '', // 訂單號
        'orderuid' => '', // 自定義客戶號
        'goodsname' => '', // 商品名稱
        'key' => '', // 簽名
        'version' => '2', // 協議版本號
    ];

    /**
     * 支付時支付平台參數與內部參數的對應
     *
     * @var array
     */
    protected $requireMap = [
        'uid' => 'number',
        'price' => 'amount',
        'istype' => 'paymentVendorId',
        'notify_url' => 'notify_url',
        'return_url' => 'notify_url',
        'orderid' => 'orderId',
        'orderuid' => 'orderId',
        'goodsname' => 'orderId',
    ];

    /**
     * 支付時需要加密的參數
     *
     * @var array
     */
    protected $encodeParams = [
        'uid',
        'price',
        'istype',
        'notify_url',
        'return_url',
        'orderid',
        'orderuid',
        'goodsname',
        'version',
    ];

    /**
     * 支付解密驗證時需要加密的參數
     *     0: 可不返回的參數
     *     1: 必要返回的參數
     *
     * @var array
     */
    protected $decodeParams = [
        'paysapi_id' => 1,
        'orderid' => 1,
        'price' => 1,
        'realprice' => 1,
        'orderuid' => 1,
    ];

    /**
     * 支付平台支援的銀行對應編號
     *
     * @var array
     */
    protected $bankMap = [
        '1090' => '2', // 微信_二維
    ];

    /**
     * 回傳支付時要給支付平台驗證的資料
     *
     * @return array
     */
    public function getVerifyData()
    {
        // 驗證私鑰
        $this->verifyPrivateKey();

        $this->payVerify();

        // 從內部給定值到參數
        foreach ($this->requireMap as $paymentKey => $internalKey) {
            $this->requestData[$paymentKey] = $this->options[$internalKey];
        }

        // 帶入未支援的銀行就噴例外
        if (!array_key_exists($this->requestData['istype'], $this->bankMap)) {
            throw new PaymentException('PaymentVendor is not supported by PaymentGateway', 180066);
        }

        // 額外的參數設定
        $this->requestData['istype'] = $this->bankMap[$this->requestData['istype']];
        $this->requestData['price'] = sprintf("%.2f", $this->requestData['price']);

        // 設定支付平台需要的加密串
        $this->requestData['key'] = $this->encode();

        if (trim($this->options['verify_url']) == '') {
            throw new PaymentException('No verify_url specified', 180140);
        }

        $curlParam = [
            'method' => 'POST',
            'uri' => '/?format=json',
            'ip' => $this->options['verify_ip'],
            'host' => $this->options['verify_url'],
            'param' => http_build_query($this->requestData),
            'header' => [],
        ];

        $result = $this->curlRequest($curlParam);
        $parseData = json_decode($result, true);

        if (!isset($parseData['code']) || !isset($parseData['msg'])) {
            throw new PaymentConnectionException('Get pay parameters failed', 180128, $this->getEntryId());
        }

        if ($parseData['code'] != '1') {
            throw new PaymentConnectionException($parseData['msg'], 180130, $this->getEntryId());
        }

        if (!isset($parseData['data']['0']['qrcode'])) {
            throw new PaymentConnectionException('Get pay parameters failed', 180128, $this->getEntryId());
        }

        $urlData = $this->parseUrl($parseData['data']['0']['qrcode']);

        // Form使用GET才能正常跳轉
        $this->payMethod = 'GET';

        return [
            'post_url' => $urlData['url'],
            'params' => $urlData['params'],
        ];
    }

    /**
     * 驗證線上支付是否成功
     *
     * @param array $entry
     */
    public function verifyOrderPayment($entry)
    {
        // 驗證私鑰
        $this->verifyPrivateKey();

        $this->payResultVerify();

        // 組合參數驗證加密簽名
        $encodeData = [];

        foreach (array_keys($this->decodeParams) as $paymentKey) {
            if (array_key_exists($paymentKey, $this->options)) {
                $encodeData[$paymentKey] = $this->options[$paymentKey];
            }
        }

        $encodeData['token'] = $this->privateKey;
        ksort($encodeData);

        $encodeStr = urldecode(http_build_query($encodeData));

        // 沒有key就要丟例外，其他的參數在組織加密串的時候驗證過了
        if (!isset($this->options['key'])) {
            throw new PaymentException('No return parameter specified', 180137);
        }

        if ($this->options['key'] != md5($encodeStr)) {
            throw new PaymentConnectionException('Signature verification failed', 180034, $this->getEntryId());
        }

        if ($this->options['orderid'] != $entry['id']) {
            throw new PaymentException('Order Id error', 180061);
        }

        if ($this->options['price'] != $entry['amount']) {
            throw new PaymentException('Order Amount error', 180058);
        }
    }

    /**
     * 支付時的加密
     *
     * @return string
     */
    protected function encode()
    {
        $encodeData = [];

        foreach ($this->encodeParams as $key) {
            $encodeData[$key] = $this->requestData[$key];
        }

        $encodeData['token'] = $this->privateKey;

        // 針對$encodeData按字母做升序排列
        ksort($encodeData);

        // 依key1=value1&key2=value2&...&keyN=valueN
        $encodeStr = urldecode(http_build_query($encodeData));

        return md5($encodeStr);
    }
}