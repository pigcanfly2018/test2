<?php

namespace BB\DurianBundle\Payment;

use BB\DurianBundle\Exception\PaymentException;
use BB\DurianBundle\Exception\PaymentConnectionException;

/**
 * 天下付
 */
class TianXiaPay extends PaymentBase
{
    /**
     * 支付提交參數
     *
     * @var array
     */
    protected $requestData = [
        'service' => 'TRADE.B2C', // 接口名稱
        'version' => '1.0.0.0', // 接口版本
        'merId' => '', // 商戶號
        'tradeNo' => '', // 訂單號
        'tradeDate' => '', // 交易日期 Ymd
        'amount' => '', // 支付金額，保留小數點兩位，單位：元
        'notifyUrl' => '', // 通知網址
        'extra' => '', // 支付完成原樣回調，可空
        'summary' => '', // 交易摘要，不可空
        'expireTime' => '', // 超時時間，單位：秒，可空
        'clientIp' => '', // 付款人ip
        'bankId' => '', // 銀行代碼
        'sign' => '', // 簽名
    ];

    /**
     * 支付提交參數與內部參數的對應
     *
     * @var array
     */
    protected $requireMap = [
        'merId' => 'number',
        'tradeNo' => 'orderId',
        'tradeDate' => 'orderCreateDate',
        'amount' => 'amount',
        'notifyUrl' => 'notify_url',
        'summary' => 'orderId',
        'clientIp' => 'ip',
        'bankId' => 'paymentVendorId',
    ];

    /**
     * 支付時需要加密的參數
     *
     * @var array
     */
    protected $encodeParams = [
        'service',
        'version',
        'merId',
        'typeId',
        'tradeNo',
        'tradeDate',
        'amount',
        'notifyUrl',
        'extra',
        'summary',
        'expireTime',
        'clientIp',
        'bankId',
    ];

    /**
     * 返回驗證時需要加密的參數
     *     0: 有返回就要加密的參數
     *     1: 必要返回的參數
     *
     * @var array
     */
    protected $decodeParams = [
        'service' => 1,
        'merId' => 1,
        'tradeNo' => 1,
        'tradeDate' => 1,
        'opeNo' => 1,
        'opeDate' => 1,
        'amount' => 1,
        'status' => 1,
        'extra' => 1,
        'payTime' => 1,
    ];

    /**
     * 應答機制訊息
     *
     * @var string
     */
    protected $msg = 'SUCCESS';

    /**
     * 支付平台支援的銀行對應編號
     *
     * @var array
     */
    protected $bankMap = [
        1 => 'ICBC', // 工商銀行
        2 => 'COMM', // 交通銀行
        3 => 'ABC', // 農業銀行
        4 => 'CCB', // 建設銀行
        5 => 'CMB', // 招商銀行
        6 => 'CMBC', // 民生銀行總行
        8 => 'SPDB', // 上海浦東發展銀行
        10 => 'CIB', // 興業銀行
        11 => 'CNCB', // 中信銀行
        12 => 'CEB', // 光大銀行
        13 => 'HXB', // 華夏銀行
        14 => 'GDB', // 廣東發展銀行
        15 => 'PAB', // 平安銀行
        16 => 'PSBC', // 中國郵政
        17 => 'BOC', // 中國銀行
        217 => 'CBHB', // 渤海銀行
        1103 => '3', // QQ_二維
        1104 => '3', // QQ_手機支付
        1111 => '4', // 銀聯_二維
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

        // 從內部設值到支付參數
        foreach ($this->requireMap as $paymentKey => $internalKey) {
            $this->requestData[$paymentKey] = $this->options[$internalKey];
        }

        // 檢查銀行代碼是否支援
        if (!array_key_exists($this->requestData['bankId'], $this->bankMap)) {
            throw new PaymentException('PaymentVendor is not supported by PaymentGateway', 180066);
        }

        // 額外的參數設定
        $this->requestData['bankId'] = $this->bankMap[$this->requestData['bankId']];
        $this->requestData['amount'] = sprintf('%.2f', $this->requestData['amount']);

        $createAt = new \Datetime($this->requestData['tradeDate']);
        $this->requestData['tradeDate'] = $createAt->format('Ymd');

        // 二維支付需調整參數
        if (in_array($this->options['paymentVendorId'], [1103, 1111])) {
            $this->requestData['service'] = 'TRADE.SCANPAY';
            $this->requestData['typeId'] = $this->requestData['bankId'];
            unset($this->requestData['bankId']);

            $this->requestData['sign'] = $this->encode();

            if (trim($this->options['verify_url']) == '') {
                throw new PaymentException('No verify_url specified', 180140);
            }

            $curlParam = [
                'method' => 'POST',
                'uri' => '/cooperate/gateway.cgi',
                'ip' => $this->options['verify_ip'],
                'host' => $this->options['verify_url'],
                'param' => http_build_query($this->requestData),
                'header' => [],
            ];
            $result = $this->curlRequest($curlParam);
            $parseData = $this->xmlToArray($result);

            if (!isset($parseData['detail'])) {
                throw new PaymentConnectionException('Get pay parameters failed', 180128, $this->getEntryId());
            }

            $detail = $parseData['detail'];

            if (!isset($detail['code']) || !isset($detail['desc'])) {
                throw new PaymentConnectionException('Get pay parameters failed', 180128, $this->getEntryId());
            }

            if ($detail['code'] !== '00') {
                throw new PaymentConnectionException($detail['desc'], 180130, $this->getEntryId());
            }

            if (!isset($detail['qrCode'])) {
                throw new PaymentConnectionException('Get pay parameters failed', 180128, $this->getEntryId());
            }

            $this->setQrcode(base64_decode($detail['qrCode']));

            return [];
        }

        // 手機支付需調整參數
        if ($this->options['paymentVendorId'] == 1104) {
            $this->requestData['service'] = 'TRADE.H5PAY';
            $this->requestData['typeId'] = $this->requestData['bankId'];
            unset($this->requestData['bankId']);
        }

        // 設定加密簽名
        $this->requestData['sign'] = $this->encode();

        return $this->requestData;
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

        $encodeStr = urldecode(http_build_query($encodeData));
        $encodeStr .= $this->privateKey;

        // 沒有返回sign就要丟例外
        if (!isset($this->options['sign'])) {
            throw new PaymentException('No return parameter specified', 180137);
        }

        if (strcasecmp($this->options['sign'], md5($encodeStr)) != 0) {
            throw new PaymentConnectionException('Signature verification failed', 180034, $this->getEntryId());
        }

        if ($this->options['status'] != '1') {
            throw new PaymentConnectionException('Payment failure', 180035, $this->getEntryId());
        }

        if ($this->options['tradeNo'] != $entry['id']) {
            throw new PaymentException('Order Id error', 180061);
        }

        if ($this->options['amount'] != $entry['amount']) {
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

        /**
         * 組織加密簽名，排除sign(加密簽名)
         * 參數存在都要納入加密
         */
        foreach ($this->encodeParams as $index) {
            if (isset($this->requestData[$index])) {
                $encodeData[$index] = $this->requestData[$index];
            }
        }

        // 依key1=value1&key2=value2&...&keyN=valueN之後做md5
        $encodeStr = urldecode(http_build_query($encodeData));
        $encodeStr .= $this->privateKey;

        return md5($encodeStr);
    }
}
