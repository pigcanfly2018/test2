<?php

namespace BB\DurianBundle\Payment;

use BB\DurianBundle\Exception\PaymentException;
use BB\DurianBundle\Exception\PaymentConnectionException;

/**
 * 億勝付
 */
class YiShengFu extends PaymentBase
{
    /**
     * 支付提交參數
     *
     * @var array
     */
    protected $requestData = [
        'apiName' => 'WEB_PAY_B2C', // WEB_PAY_B2C: 網頁支付、WAP_PAY_B2C: 手機支付
        'apiVersion' => '1.0.0.1', // 接口版本
        'platformID' => '', // 平台號ID
        'merchNo' => '', // 商號
        'orderNo' => '', // 訂單號
        'tradeDate' => '', // 交易日期(格式：Ymd)
        'amt' => '', // 支付金額，保留小數點兩位，單位：元
        'merchUrl' => '', // 支付結果通知網址, 不能串參數
        'merchParam' => '', // 商戶參數，可空
        'tradeSummary' => '', // 交易摘要，必填
        'customerIP' => '', // 客户端ip地址，必填
        'signMsg' => '', // 加密簽名
        'bankCode' => '', // 銀行代碼，不納入加密簽名
        'choosePayType' => '', // 支付方式，可空
    ];

    /**
     * 支付提交參數與內部參數的對應
     *
     * @var array
     */
    protected $requireMap = [
        'merchNo' => 'number',
        'orderNo' => 'orderId',
        'tradeDate' => 'orderCreateDate',
        'amt' => 'amount',
        'merchUrl' => 'notify_url',
        'tradeSummary' => 'orderId',
        'customerIP' => 'ip',
        'bankCode' => 'paymentVendorId',
    ];

    /**
     * 支付時需要加密的參數
     *
     * @var array
     */
    protected $encodeParams = [
        'apiName',
        'apiVersion',
        'platformID',
        'merchNo',
        'orderNo',
        'tradeDate',
        'amt',
        'merchUrl',
        'merchParam',
        'tradeSummary',
        'customerIP',
    ];

    /**
     * 返回驗證時需要加密的參數
     *     0: 有返回就要加密的參數
     *     1: 必要返回的參數
     *
     * @var array
     */
    protected $decodeParams = [
        'apiName' => 1,
        'notifyTime' => 1,
        'tradeAmt' => 1,
        'merchNo' => 1,
        'merchParam' => 1,
        'orderNo' => 1,
        'tradeDate' => 1,
        'accNo' => 1,
        'accDate' => 1,
        'orderStatus' => 1,
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
        '1' => 'ICBC', // 工商銀行
        '2' => 'COMM', // 交通銀行
        '3' => 'ABC', // 農業銀行
        '4' => 'CCB', // 建設銀行
        '5' => 'CMB', // 招商銀行
        '6' => 'CMBC', // 民生銀行總行
        '8' => 'SPDB', // 上海浦東發展銀行
        '9' => 'BOBJ', // 北京銀行
        '10' => 'CIB', // 興業銀行
        '11' => 'CNCB', // 中信銀行
        '12' => 'CEB', // 光大銀行
        '13' => 'HXB', // 華夏銀行
        '14' => 'GDB', // 廣東發展銀行
        '15' => 'PAB', // 平安銀行
        '16' => 'PSBC', // 中國郵政
        '17' => 'BOC', // 中國銀行
        '19' => 'BOSH', // 上海銀行
        '217' => 'CBHB', // 渤海银行
        '221' => 'CZSB', // 浙商银行
        '223' => 'BEA', // 東亞銀行
        '228' => 'CRBANK', // 上海農商銀行
        '234' => 'CRBANK', // 北京農商銀行
        '311' => 'BOHF', // 恒豐银行
        '312' => 'BOCD', // /成都银行
        '361' => 'JZTLYH', // 浙江泰隆商業银行
        '1103' => 'QQWLT_PAY', // QQ_二維
        '1107' => 'JDWLT_PAY', // 京東_二維
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

        // 從內部設值到支付參數
        foreach ($this->requireMap as $paymentKey => $internalKey) {
            $this->requestData[$paymentKey] = $this->options[$internalKey];
        }

        // 檢查銀行代碼是否支援
        if (!array_key_exists($this->requestData['bankCode'], $this->bankMap)) {
            throw new PaymentException(
                'PaymentVendor is not supported by PaymentGateway',
                180066
            );
        }

        // 額外的參數設定
        $this->requestData['bankCode'] = $this->bankMap[$this->requestData['bankCode']];
        $this->requestData['amt'] = sprintf('%.2f', $this->requestData['amt']);
        $createAt = new \Datetime($this->requestData['tradeDate']);
        $this->requestData['tradeDate'] = $createAt->format('Ymd');

        // 商家額外的參數設定
        $extra = $this->getMerchantExtraValue(['platformID']);
        $this->requestData['platformID'] = $extra['platformID'];

        // 二維支付
        if (in_array($this->options['paymentVendorId'], [1103, 1107])) {
            $this->requestData['apiName'] = $this->requestData['bankCode'];
            $this->requestData['apiVersion'] = '1.0.0.0';
            $this->requestData['overTime'] = '';
            unset($this->requestData['bankCode']);

            // 更新加密參數陣列
            array_splice($this->encodeParams, -1, 0, 'overTime');

            // 設定加密簽名
            $this->requestData['signMsg'] = $this->encode();

            if (trim($this->options['verify_url']) == '') {
                throw new PaymentException('No verify_url specified', 180140);
            }

            $curlParam = [
                'method' => 'POST',
                'uri' => '/cgi-bin/netpayment/pay_gate.cgi',
                'ip' => $this->options['verify_ip'],
                'host' => $this->options['verify_url'],
                'param' => http_build_query($this->requestData),
                'header' => [],
            ];

            $result = $this->curlRequest($curlParam);

            // 檢查返回參數
            $parseData = $this->xmlToArray($result)['respData'];

            if (!isset($parseData, $parseData['respDesc'])) {
                throw new PaymentConnectionException('Get pay parameters failed', 180128, $this->getEntryId());
            }

            if ($parseData['respCode'] !== '00') {
                throw new PaymentConnectionException($parseData['respDesc'], 180130, $this->getEntryId());
            }

            if (!isset($parseData['codeUrl'])) {
                throw new PaymentConnectionException('Get pay parameters failed', 180128, $this->getEntryId());
            }

            $this->setQrcode(base64_decode($parseData['codeUrl']));

            return [];
        }

        // 設定加密簽名
        $this->requestData['signMsg'] = $this->encode();

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

        // 驗證返回參數
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

        // 沒有返回signMsg就要丟例外
        if (!isset($this->options['signMsg'])) {
            throw new PaymentException('No return parameter specified', 180137);
        }

        if (strcasecmp($this->options['signMsg'], md5($encodeStr)) != 0) {
            throw new PaymentConnectionException('Signature verification failed', 180034, $this->getEntryId());
        }

        if ($this->options['orderStatus'] == '0') {
            throw new PaymentConnectionException('Unpaid order', 180062, $this->getEntryId());
        }

        if ($this->options['orderStatus'] != '1') {
            throw new PaymentConnectionException('Payment failure', 180035, $this->getEntryId());
        }

        if ($this->options['orderNo'] != $entry['id']) {
            throw new PaymentException('Order Id error', 180061);
        }

        if ($this->options['tradeAmt'] != sprintf('%.2f', $entry['amount'])) {
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

        // 加密設定
        foreach ($this->encodeParams as $index) {
            $encodeData[$index] = $this->requestData[$index];
        }

        $encodeStr = urldecode(http_build_query($encodeData));
        $encodeStr .= $this->privateKey;

        return md5($encodeStr);
    }
}
