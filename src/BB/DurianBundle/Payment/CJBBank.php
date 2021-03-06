<?php

namespace BB\DurianBundle\Payment;

use BB\DurianBundle\Exception\PaymentException;
use BB\DurianBundle\Exception\PaymentConnectionException;

/**
 * 快捷宝支付
 *
 * 支付驗證：
 * 1. 驗證不可為空的參數
 * 2. 設定參數
 * 3. 額外處理的參數
 * 4. 設定encodeStr(加密後的字串)
 *
 * 解密驗證：
 * 1. 驗證key
 * 2. 設定參數
 * 3. 驗證結果是否相符
 */
class CJBBank extends PaymentBase
{
    /**
     * 支付時要傳給平台驗證的參數
     *
     * @var array
     */
    protected $requestData = [
        'p0_Cmd'          => 'Buy', //業務類型
        'p1_MerId'        => '', //商戶編號
        'p2_Order'        => '', //商戶訂單號
        'p3_Amt'          => '', //支付金額
        'p4_Cur'          => 'CNY', //交易幣種
        'p5_Pid'          => '', //商品名稱
        'p6_Pcat'         => '', //商品種類
        'p7_Pdesc'        => '', //商品描述
        'p8_Url'          => '', //商戶接收支付成功資料的位址
        'p9_SAF'          => '0', //送貨地址
        'pa_MP'           => '', //商戶擴展資訊
        'pd_FrpId'        => '', //支付通道編碼
        'pr_NeedResponse' => '1', //應答機制
        'hmac'            => '', //簽名數據
    ];

    /**
     * 支付時支付平台參數與內部參數的對應
     *
     * @var array
     */
    protected $requireMap = [
        'p1_MerId' => 'number',
        'p2_Order' => 'orderId',
        'p3_Amt' => 'amount',
        'p8_Url' => 'notify_url',
        'pd_FrpId' => 'paymentVendorId'
    ];

    /**
     * 支付時需要加密的參數
     *
     * @var array
     */
    protected $encodeParams = [
        'p0_Cmd',
        'p1_MerId',
        'p2_Order',
        'p3_Amt',
        'p4_Cur',
        'p5_Pid',
        'p6_Pcat',
        'p7_Pdesc',
        'p8_Url',
        'p9_SAF',
        'pa_MP',
        'pd_FrpId',
        'pr_NeedResponse',
    ];

    /**
     * 支付解密驗證時需要加密的參數
     *     0: 可不返回的參數
     *     1: 必要返回的參數
     *
     * @var array
     */
    protected $decodeParams = [
        'p1_MerId' => 1, //商戶編號
        'r0_Cmd' => 1, //業務類型
        'r1_Code' => 1, //支付結果
        'r2_TrxId' => 1, //支付交易流水號
        'r3_Amt' => 1, //支付金額
        'r4_Cur' => 1, //交易幣種
        'r5_Pid' => 1, //商品名稱
        'r6_Order' => 1, //商戶訂單號
        'r7_Uid' => 1, //會員ID
        'r8_MP' => 1, //商戶擴展資訊
        'r9_BType' => 1 //交易結果返回類型
    ];

    /**
     * 支付平台支援的銀行對應編號
     *
     * @var array
     */
    protected $bankMap = [
        1   => 'ICBC-KJB-B2C', //工商銀行
        2   => 'BOCO-KJB-B2C', //交通銀行
        3   => 'ABC-KJB-B2C', //農業銀行
        4   => 'CCB-KJB-B2C', //建設銀行
        5   => 'CMBCHINA-KJB-B2C', //招商銀行
        6   => 'CMBC-KJB-B2C', //民生銀行
        7   => 'SDB-KJB-B2C', //深圳發展銀行
        8   => 'SPDB-KJB-B2C', //上海浦東發展銀行
        9   => 'BCCB-KJB-B2C', //北京銀行
        10  => 'CIB-KJB-B2C', //興業銀行
        11  => 'ECITIC-KJB-B2C', //中信銀行
        12  => 'CEB-KJB-B2C', //光大銀行
        14  => 'GDB-KJB-B2C', //廣東發展銀行
        15  => 'PINGANBANK-KJB', //平安銀行
        16  => 'POST-KJB-B2C', //中國郵政儲蓄銀行
        17  => 'BOC-KJB-B2C', //中國銀行
        19  => 'SHB-KJB-B2C', //上海銀行
        217 => 'CBHB-KJB-B2C', //渤海銀行
        220 => 'HZBANK-KJB-B2C', //杭州銀行
        221 => 'CZ-KJB-B2C', //浙商銀行
        222 => 'NBCB-KJB-B2C', //寧波銀行
        223 => 'HKBEA-KJB-B2C', //東亞銀行
        226 => 'NJCB-KJB-B2C', //南京銀行
        228 => 'SRCB-KJB-B2C', //上海農村商業銀行
        234 => 'BJRCB-KJB-B2C', //北京農村商業銀行
        278 => 'UP-KJB-B2C', //銀聯在線
        279 => 'UPNC-KJB-B2C' //銀聯無卡
    ];

    /**
     * 查詢時要傳給平台驗證的參數
     *
     * @var array
     */
    protected $trackingRequestData = [
        'p0_Cmd'   => 'QueryOrdDetail', //業務類型
        'p1_MerId' => '', //商戶編號
        'p2_Order' => '', //商戶訂單號
        'hmac'     => '', //簽名數據
    ];

    /**
     * 查詢時支付平台參數與內部參數的對應
     *
     * @var array
     */
    protected $trackingRequireMap = [
        'p1_MerId' => 'number',
        'p2_Order' => 'orderId'
    ];

    /**
     * 查詢時需要加密的參數
     *
     * @var array
     */
    protected $trackingEncodeParams = [
        'p0_Cmd',
        'p1_MerId',
        'p2_Order',
    ];

    /**
     * 查詢解密驗證時需要加密的參數
     *     0: 可不返回的參數
     *     1: 必要返回的參數
     *
     * @var array
     */
    protected $trackingDecodeParams = [
        'r0_Cmd' => 1,
        'r1_Code' => 1,
        'r2_TrxId' => 1,
        'r3_Amt' => 1,
        'r4_Cur' => 1,
        'r5_Pid' => 1,
        'r6_Order' => 1,
        'r8_MP' => 1,
        'rb_PayStatus' => 1,
        'rc_RefundCount' => 1,
        'rd_RefundAmt' => 1
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

        $this->options['notify_url'] = sprintf(
            '%s?pay_system=%s&hallid=%s',
            $this->options['notify_url'],
            $this->options['merchantId'],
            $this->options['domain']
        );

        // 從內部給定值到參數
        foreach ($this->requireMap as $paymentKey => $internalKey) {
            $this->requestData[$paymentKey] = $this->options[$internalKey];
        }

        //帶入未支援的銀行就噴例外
        if (!array_key_exists($this->requestData['pd_FrpId'], $this->bankMap)) {
            throw new PaymentException('PaymentVendor is not supported by PaymentGateway', 180066);
        }

        //額外的參數設定
        $this->requestData['pd_FrpId'] = $this->bankMap[$this->requestData['pd_FrpId']];
        $this->requestData['p3_Amt'] = sprintf('%.2f', $this->requestData['p3_Amt']);

        //設定支付平台需要的加密串
        $this->requestData['hmac'] = $this->encode();

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

        $encodeStr = '';

        foreach (array_keys($this->decodeParams) as $paymentKey) {
            if (array_key_exists($paymentKey, $this->options)) {
                $encodeStr .= $paymentKey . $this->options[$paymentKey];
            }
        }

        //進行加密
        $encodeStr .= $this->privateKey;
        $encodeStr = strtoupper(md5($encodeStr));

        //沒有hmac就要丟例外，其他的參數在組織加密串的時候驗證過了
        if (!isset($this->options['hmac'])) {
            throw new PaymentException('No return parameter specified', 180137);
        }

        if ($this->options['hmac'] != $encodeStr) {
            throw new PaymentConnectionException('Signature verification failed', 180034, $this->getEntryId());
        }

        if ($this->options['r1_Code'] != '1') {
            throw new PaymentConnectionException('Payment failure', 180035, $this->getEntryId());
        }

        if ($this->options['r6_Order'] != $entry['id']) {
            throw new PaymentException('Order Id error', 180061);
        }

        if ($this->options['r3_Amt'] != $entry['amount']) {
            throw new PaymentException('Order Amount error', 180058);
        }
    }

    /**
     * 訂單查詢
     */
    public function paymentTracking()
    {
        // 驗證私鑰
        $this->verifyPrivateKey();
        $this->trackingVerify();

        // 從內部給定值到參數
        foreach ($this->trackingRequireMap as $paymentKey => $internalKey) {
            $this->trackingRequestData[$paymentKey] = $this->options[$internalKey];
        }
        $this->trackingRequestData['hmac'] = $this->trackingEncode();

        if (trim($this->options['verify_url']) == '') {
            throw new PaymentException('No verify_url specified', 180140);
        }

        $curlParam = [
            'method' => 'POST',
            'uri' => '/bankinterface/queryOrd',
            'ip' => $this->options['verify_ip'],
            'host' => $this->options['verify_url'],
            'param' => http_build_query($this->trackingRequestData),
            'header' => []
        ];

        // 取得訂單查詢結果
        $result = $this->curlRequest($curlParam);
        $parseData = $this->parseData($result);
        $this->trackingResultVerify($parseData);

        $encodeStr = '';

        // 組織加密串
        foreach (array_keys($this->trackingDecodeParams) as $paymentKey) {
            if (array_key_exists($paymentKey, $parseData)) {
                $encodeStr .= $paymentKey . $parseData[$paymentKey];
            }
        }

        // 進行加密
        $encodeStr .= $this->privateKey;

        // 沒有hmac就要丟例外，其他的參數在組織加密串的時候驗證過了
        if (!isset($parseData['hmac'])) {
            throw new PaymentException('No tracking return parameter specified', 180139);
        }

        if ($parseData['hmac'] != strtoupper(md5($encodeStr))) {
            throw new PaymentConnectionException('Signature verification failed', 180034, $this->getEntryId());
        }

        if ($parseData['r1_Code'] == '50') {
            throw new PaymentConnectionException('Order does not exist', 180060, $this->getEntryId());
        }

        if ($parseData['r1_Code'] != '1') {
            throw new PaymentConnectionException('Payment failure', 180035, $this->getEntryId());
        }

        if ($parseData['rb_PayStatus'] == 'INIT') {
            throw new PaymentConnectionException('Unpaid order', 180062, $this->getEntryId());
        }

        if ($parseData['rb_PayStatus'] == 'ING') {
            throw new PaymentConnectionException('Order Processing', 180059, $this->getEntryId());
        }

        if ($parseData['rb_PayStatus'] == 'CANCELED') {
            throw new PaymentConnectionException('Order has been cancelled', 180063, $this->getEntryId());
        }

        if ($parseData['r3_Amt'] != $this->options['amount']) {
            throw new PaymentException('Order Amount error', 180058);
        }
    }

    /**
     * 取得訂單查詢時需要的參數
     *
     * @return array
     */
    public function getPaymentTrackingData()
    {
        // 驗證私鑰
        $this->verifyPrivateKey();
        $this->trackingVerify();

        // 從內部給定值到參數
        foreach ($this->trackingRequireMap as $paymentKey => $internalKey) {
            $this->trackingRequestData[$paymentKey] = $this->options[$internalKey];
        }
        $this->trackingRequestData['hmac'] = $this->trackingEncode();

        if (trim($this->options['verify_url']) == '') {
            throw new PaymentException('No verify_url specified', 180140);
        }

        $curlParam = [
            'verify_ip' => $this->options['verify_ip'],
            'path' => '/bankinterface/queryOrd',
            'method' => 'POST',
            'form' => $this->trackingRequestData,
            'headers' => [
                'Host' => $this->options['verify_url']
            ]
        ];

        return $curlParam;
    }

    /**
     * 驗證訂單查詢是否成功
     */
    public function paymentTrackingVerify()
    {
        // 驗證私鑰
        $this->verifyPrivateKey();

        $parseData = $this->parseData($this->options['content']);
        $this->trackingResultVerify($parseData);

        $encodeStr = '';

        // 組織加密串
        foreach (array_keys($this->trackingDecodeParams) as $paymentKey) {
            if (array_key_exists($paymentKey, $parseData)) {
                $encodeStr .= $paymentKey . $parseData[$paymentKey];
            }
        }

        // 進行加密
        $encodeStr .= $this->privateKey;

        // 沒有hmac就要丟例外，其他的參數在組織加密串的時候驗證過了
        if (!isset($parseData['hmac'])) {
            throw new PaymentException('No tracking return parameter specified', 180139);
        }

        if ($parseData['hmac'] != strtoupper(md5($encodeStr))) {
            throw new PaymentConnectionException('Signature verification failed', 180034, $this->getEntryId());
        }

        if ($parseData['r1_Code'] == '50') {
            throw new PaymentConnectionException('Order does not exist', 180060, $this->getEntryId());
        }

        if ($parseData['r1_Code'] != '1') {
            throw new PaymentConnectionException('Payment failure', 180035, $this->getEntryId());
        }

        if ($parseData['rb_PayStatus'] == 'INIT') {
            throw new PaymentConnectionException('Unpaid order', 180062, $this->getEntryId());
        }

        if ($parseData['rb_PayStatus'] == 'ING') {
            throw new PaymentConnectionException('Order Processing', 180059, $this->getEntryId());
        }

        if ($parseData['rb_PayStatus'] == 'CANCELED') {
            throw new PaymentConnectionException('Order has been cancelled', 180063, $this->getEntryId());
        }

        if ($parseData['r3_Amt'] != $this->options['amount']) {
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
        $encodeStr = '';

        //加密設定
        foreach ($this->encodeParams as $index) {
            $encodeStr .= $index . $this->requestData[$index];
        }

        //額外的加密設定
        $encodeStr .= $this->privateKey;

        return strtoupper(md5($encodeStr));
    }

    /**
     * 訂單查詢時的加密
     *
     * @return string
     */
    protected function trackingEncode()
    {
        $encodeStr = '';

        //加密設定
        foreach ($this->trackingEncodeParams as $index) {
            $encodeStr .= $index . $this->trackingRequestData[$index];
        }

        //額外的加密設定
        $encodeStr .= $this->privateKey;

        return strtoupper(md5($encodeStr));
    }

    /**
     * 分析支付平台回傳的入款查詢參數
     *
     * @param array $content
     * @return array
     */
    private function parseData($content)
    {
        //將格式改成query string的格式再用parse_str來做分解
        $content = str_replace("\n", '&', urldecode($content));
        parse_str($content, $parseData);

        return $parseData;
    }
}
