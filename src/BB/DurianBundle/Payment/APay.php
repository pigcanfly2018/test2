<?php

namespace BB\DurianBundle\Payment;

use BB\DurianBundle\Exception\PaymentException;
use BB\DurianBundle\Exception\PaymentConnectionException;

/**
 * A付123563176
 */
class APay extends PaymentBase
{
    /**
     * 支付時要傳給平台驗證的參數
     *
     * @var array
     */
    protected $requestData = [
        'payKey' => '', // 商戶支付key
        'orderPrice' => '', // 訂單交易，單位：元，保留小數點兩位
        'outTradeNo' => '', // 商戶訂單號
        'productType' => '', // 產品類型
        'orderTime' => '', // 下單時間，格式YmdHis
        'productName' => '', // 支付產品名稱，帶入orderid
        'orderIp' => '', // 下單IP
        'returnUrl' => '', // 頁面通知地址
        'notifyUrl' => '', // 異步通知地址
        'sign' => '', // 簽名
        'remark' => '', // 備註，可空
    ];

    /**
     * 支付時支付平台參數與內部參數的對應
     *
     * @var array
     */
    protected $requireMap = [
        'payKey' => 'number',
        'orderPrice' => 'amount',
        'outTradeNo' => 'orderId',
        'productType' => 'paymentVendorId',
        'orderTime' => 'orderCreateDate',
        'productName' => 'orderId',
        'orderIp' => 'ip',
        'returnUrl' => 'notify_url',
        'notifyUrl' => 'notify_url',
    ];

    /**
     * 支付時需要加密的參數
     *
     * @var array
     */
    protected $encodeParams = [
        'payKey',
        'orderPrice',
        'outTradeNo',
        'productType',
        'orderTime',
        'productName',
        'orderIp',
        'returnUrl',
        'notifyUrl',
        'remark',
    ];

    /**
     * 支付解密驗證時需要加密的參數
     *     0: 可不返回的參數
     *     1: 必要返回的參數
     *
     * @var array
     */
    protected $decodeParams = [
        'orderPrice' => 1,
        'orderTime' => 1,
        'outTradeNo' => 1,
        'payKey' => 1,
        'productName' => 1,
        'productType' => 1,
        'successTime' => 1,
        'tradeStatus' => 1,
        'trxNo' => 1,
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
        '278' => '40000503', // 銀聯在線
        '1088' => '40000503', // 銀聯在線_手機支付
        '1092' => '20000303', // 支付寶_二維
        '1102' => '50000103', // 網銀_收銀台
        '1103' => '70000203', // QQ_二維
        '1104' => '70000204', // QQ_手機支付
        '1107' => '80000203', // 京東_二維
        '1111' => '60000103', // 銀聯_二維
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

        // 驗證支付參數
        $this->payVerify();

        // 從內部給定值到參數
        foreach ($this->requireMap as $paymentKey => $internalKey) {
            $this->requestData[$paymentKey] = $this->options[$internalKey];
        }

        // 帶入未支援的銀行就噴例外
        if (!array_key_exists($this->requestData['productType'], $this->bankMap)) {
            throw new PaymentException('PaymentVendor is not supported by PaymentGateway', 180066);
        }

        // 商家額外的參數設定
        $this->requestData['productType'] = $this->bankMap[$this->requestData['productType']];
        $this->requestData['orderPrice'] = sprintf('%.2f', $this->requestData['orderPrice']);
        $createAt = new \Datetime($this->requestData['orderTime']);
        $this->requestData['orderTime'] = $createAt->format('YmdHis');

        $this->requestData['sign'] = $this->encode();

        // 二維、手機支付
        if (in_array($this->options['paymentVendorId'], [1092, 1103, 1104, 1107, 1111])) {
            if (trim($this->options['verify_url']) == '') {
                throw new PaymentException('No verify_url specified', 180140);
            }

            $curlParam = [
                'method' => 'POST',
                'uri' => '/cnpPay/initPay',
                'ip' => $this->options['verify_ip'],
                'host' => $this->options['verify_url'],
                'param' => urldecode(http_build_query($this->requestData)),
                'header' => [],
            ];

            $result = $this->curlRequest($curlParam);
            $parseData = json_decode($result, true);

            if (!isset($parseData['resultCode']) || !isset($parseData['errMsg'])) {
                throw new PaymentConnectionException('Get pay parameters failed', 180128, $this->getEntryId());
            }

            if ($parseData['resultCode'] !== '0000') {
                throw new PaymentConnectionException($parseData['errMsg'], 180130, $this->getEntryId());
            }

            if (!isset($parseData['payMessage'])) {
                throw new PaymentConnectionException('Get pay parameters failed', 180128, $this->getEntryId());
            }

            // 手機支付
            if ($this->options['paymentVendorId'] == 1104) {
                $fetchedUrl = [];
                preg_match("/action=\"([^\"]+)/", $parseData['payMessage'], $fetchedUrl);

                if (!isset($fetchedUrl[1])) {
                    throw new PaymentConnectionException('Get pay parameters failed', 180128, $this->getEntryId());
                }

                // 解析跳轉網址
                $urlData = $this->parseUrl($fetchedUrl[1]);

                // Form使用GET才能正常跳轉
                $this->payMethod = 'GET';

                return [
                    'post_url' => $urlData['url'],
                    'params' => $urlData['params'],
                ];
            }

            $this->setQrcode($parseData['payMessage']);

            return [];
        }

        // 網銀收銀台調整提交網址
        $postUrl = $this->options['postUrl'] . '/netGateWayPay/initPay';

        // 銀聯在線調整提交網址
        if (in_array($this->options['paymentVendorId'], [278, 1088])) {
            $postUrl = $this->options['postUrl'] . '/quickGateWayPay/initPay';
        }

        return [
           'post_url' => $postUrl,
           'params' => $this->requestData,
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

        foreach (array_keys($this->decodeParams) as $paymentKey) {
            if (array_key_exists($paymentKey, $this->options) && trim($this->options[$paymentKey]) != '') {
                $encodeData[$paymentKey] = $this->options[$paymentKey];
            }
        }

        ksort($encodeData);
        $encodeData['paySecret'] = $this->privateKey;
        $encodeStr = urldecode(http_build_query($encodeData));

        // 若沒有返回簽名需丟例外
        if (!isset($this->options['sign'])) {
            throw new PaymentException('No return parameter specified', 180137);
        }

        if (strcasecmp($this->options['sign'], md5($encodeStr)) != 0) {
            throw new PaymentConnectionException('Signature verification failed', 180034, $this->getEntryId());
        }

        if ($this->options['tradeStatus'] == 'WAITING_PAYMENT') {
            throw new PaymentConnectionException('Unpaid order', 180062, $this->getEntryId());
        }

        if ($this->options['tradeStatus'] != 'SUCCESS') {
            throw new PaymentConnectionException('Payment failure', 180035, $this->getEntryId());
        }

        if ($this->options['outTradeNo'] != $entry['id']) {
            throw new PaymentException('Order Id error', 180061);
        }

        if ($this->options['orderPrice'] != $entry['amount']) {
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

        foreach ($this->encodeParams as $index) {
            if (isset($this->requestData[$index]) && $this->requestData[$index] != '') {
                $encodeData[$index] = $this->requestData[$index];
            }
        }

        ksort($encodeData);
        $encodeData['paySecret'] = $this->privateKey;
        $encodeStr = urldecode(http_build_query($encodeData));

        return strtoupper(md5($encodeStr));
    }
}
