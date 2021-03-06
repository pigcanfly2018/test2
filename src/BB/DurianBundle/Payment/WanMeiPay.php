<?php

namespace BB\DurianBundle\Payment;

use BB\DurianBundle\Exception\PaymentException;
use BB\DurianBundle\Exception\PaymentConnectionException;

/**
 * 完美支付
 */
class WanMeiPay extends PaymentBase
{
    /**
     * 支付時要傳給平台驗證的參數
     *
     * @var array
     */
    protected $requestData = [
        'orderid' => '', // 訂單號
        'fee' => '', // 金額，整數
        'time' => '',  // 訂單時間戳
        'hash' => '', // 簽名
        'notifyUrl' => '', // 異步通知網址
        'agentid' => '', // 商戶號
        'payType' => '', // 支付方式
    ];

    /**
     * 支付時支付平台參數與內部參數的對應
     *
     * @var array
     */
    protected $requireMap = [
        'orderid' => 'orderId',
        'fee' => 'amount',
        'time' => 'orderCreateDate',
        'notifyUrl' => 'notify_url',
        'agentid' => 'number',
        'payType' => 'paymentVendorId',
    ];

    /**
     * 支付時需要加密的參數
     *
     * @var array
     */
    protected $encodeParams = [
        'orderid',
        'fee',
        'time',
    ];

    /**
     * 支付解密驗證時需要加密的參數
     *     0: 可不返回的參數
     *     1: 必要返回的參數
     *
     * @var array
     */
    protected $decodeParams = [
        'id' => 1,
        'money' => 1,
        'token' => 1,
        'time' => 1,
    ];

    /**
     * 應答機制訊息
     *
     * @var string
     */
    protected $msg = '{"message":"成功"}';

    /**
     * 支付平台支援的銀行對應編號
     *
     * @var array
     */
    protected $bankMap = [
        '1090' => 1, // 微信_二維
        '1092' => 2, // 支付寶_二維
        '1097' => 1, // 微信_手機支付
        '1098' => 2, // 支付寶_手機支付
    ];

    /**
     * 回傳支付時要給支付平台驗證的資料
     *
     * @return array
     */
    public function getVerifyData()
    {
        $this->verifyPrivateKey();
        $this->payVerify();

        // 從內部給定值到參數
        foreach ($this->requireMap as $paymentKey => $internalKey) {
            $this->requestData[$paymentKey] = $this->options[$internalKey];
        }

        // 檢查銀行代碼是否支援
        if (!array_key_exists($this->requestData['payType'], $this->bankMap)) {
            throw new PaymentException('PaymentVendor is not supported by PaymentGateway', 180066);
        }

        // 額外的參數設定
        $this->requestData['payType'] = $this->bankMap[$this->requestData['payType']];
        $orderCreateDate = new \DateTime($this->options['orderCreateDate']);
        $this->requestData['time'] = $orderCreateDate->getTimestamp();

        // 設定支付平台需要的加密串
        $this->requestData['hash'] = $this->encode();

        if (trim($this->options['verify_url']) == '') {
            throw new PaymentException('No verify_url specified', 180140);
        }

        $curlParam = [
            'method' => 'POST',
            'uri' => '/new_order',
            'ip' => $this->options['verify_ip'],
            'host' => $this->options['verify_url'],
            'param' => json_encode($this->requestData),
            'header' => ['Content-Type' => 'application/json'],
        ];
        $result = $this->curlRequest($curlParam);
        $parseData = json_decode($result, true);

        if (isset($parseData['error'])) {
            throw new PaymentConnectionException($parseData['error'], 180130, $this->getEntryId());
        }

        if (!isset($parseData['token'])) {
            throw new PaymentConnectionException('Get pay parameters failed', 180128, $this->getEntryId());
        }

        // 檢查 token 是否為19位的數字
        if (!preg_match('/^\d{19}$/', $parseData['token'])) {
            throw new PaymentConnectionException('Get pay parameters failed', 180128, $this->getEntryId());
        }

        // 檢查是否有postUrl(支付平台提交的url)
        if (trim($this->options['postUrl']) == '') {
            throw new PaymentException('No pay parameter specified', 180145);
        }

        // 調整提交網址
        $postUrl = $this->options['postUrl'] . $parseData['token'];

        // Form使用GET才能正常跳轉
        $this->payMethod = 'GET';

        return [
            'post_url' => $postUrl,
            'params' => [],
        ];
    }

    /**
     * 驗證線上支付是否成功
     *
     * @param array $entry
     */
    public function verifyOrderPayment($entry)
    {
        $this->verifyPrivateKey();
        $this->payResultVerify();

        $encodeData = [];

        foreach (array_keys($this->decodeParams) as $paymentKey) {
            if (array_key_exists($paymentKey, $this->options) && trim($this->options[$paymentKey]) !== '') {
                $encodeData[] = $this->options[$paymentKey];
            }
        }
        $encodeData[] = $this->privateKey;

        $encodeStr = implode('_', $encodeData);

        if (!isset($this->options['hash'])) {
            throw new PaymentException('No return parameter specified', 180137);
        }

        if ($this->options['hash'] != strtoupper(md5($encodeStr))) {
            throw new PaymentConnectionException('Signature verification failed', 180034, $this->getEntryId());
        }

        if ($this->options['state'] != '1') {
            throw new PaymentConnectionException('Payment failure', 180035, $this->getEntryId());
        }

        if ($this->options['id'] != $entry['id']) {
            throw new PaymentException('Order Id error', 180061);
        }

        if ($this->options['money'] != $entry['amount']) {
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
            $encodeData[] = $this->requestData[$key];
        }
        $encodeData[] = $this->privateKey;

        $encodeStr = implode('_', $encodeData);

        return strtoupper(md5($encodeStr));
    }
}
