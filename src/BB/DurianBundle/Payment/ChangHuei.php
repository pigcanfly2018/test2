<?php

namespace BB\DurianBundle\Payment;

use BB\DurianBundle\Exception\PaymentException;
use BB\DurianBundle\Exception\PaymentConnectionException;

/**
 * 暢匯
 */
class ChangHuei extends PaymentBase
{
    /**
     * 支付時要傳給平台驗證的參數
     *
     * @var array
     */
    protected $requestData = [
        'p0_Cmd' => 'Buy', // 業務類型，固定值
        'p1_MerId' => '', // 商號
        'p2_Order' => '', // 訂單號
        'p3_Cur' => 'CNY', // 交易幣別，固定值
        'p4_Amt' => '', // 支付金額，精準到分
        'p5_Pid' => '', // 商品名稱，可空
        'p6_Pcat' => '', // 商品種類，可空
        'p7_Pdesc' => '', // 商品描述，可空
        'p8_Url' => '', // 接收支付成功的地扯
        'p9_MP' => '', // 商戶擴展訊息，放入orderid
        'pa_FrpId' => 'OnlinePay', // 支付通道編碼，預設網銀
        'pg_BankCode' => '', // 銀行編碼，網銀必填
        'hmac' => '', // 加密簽名
    ];

    /**
     * 支付時支付平台參數與內部參數的對應
     *
     * @var array
     */
    protected $requireMap = [
        'p1_MerId' => 'number',
        'p2_Order' => 'orderId',
        'p4_Amt' => 'amount',
        'p8_Url' => 'notify_url',
        'p9_MP' => 'orderId',
        'pg_BankCode' => 'paymentVendorId',
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
        'p3_Cur',
        'p4_Amt',
        'p5_Pid',
        'p6_Pcat',
        'p7_Pdesc',
        'p8_Url',
        'p9_MP',
        'pa_FrpId',
        'pg_BankCode',
    ];

    /**
     * 支付解密驗證時需要加密的參數
     *     0: 可不返回的參數
     *     1: 必要返回的參數
     *
     * @var array
     */
    protected $decodeParams = [
        'p1_MerId' => 1,
        'r0_Cmd' => 1,
        'r1_Code' => 1,
        'r2_TrxId' => 1,
        'r3_Amt' => 1,
        'r4_Cur' => 1,
        'r5_Pid' => 1,
        'r6_Order' => 1,
        'r8_MP' => 1,
        'r9_BType' => 1,
        'ro_BankOrderId' => 1,
        'rp_PayDate' => 1,
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
        2 => 'BOCO', // 交通銀行
        3 => 'ABC', // 農業銀行
        4 => 'CCB', // 建設銀行
        5 => 'CMBCHINA', // 招商銀行
        6 => 'CMBC', // 民生銀行
        8 => 'SPDB', // 上海浦東發展銀行
        9 => 'BOB', // 北京銀行
        10 => 'CIB', // 興業銀行
        11 => 'ECITIC', // 中信銀行
        12 => 'CEB', // 光大銀行
        13 => 'HXB', // 華夏銀行
        14 => 'CGB', // 廣東發展銀行
        15 => 'PAB', // 平安銀行
        16 => 'PSBC', // 中國郵政
        17 => 'BOC', // 中國銀行
        19 => 'SHB', // 上海銀行
        1103 => 'QQ', // QQ_二維
        1104 => 'QQWAP', // QQ_手機支付
        1107 => 'JDPAY', // 京東錢包_二維
        1111 => 'UnionPay', // 銀聯_二維
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
        if (!array_key_exists($this->requestData['pg_BankCode'], $this->bankMap)) {
            throw new PaymentException('PaymentVendor is not supported by PaymentGateway', 180066);
        }

        // 額外的參數設定
        $this->requestData['pg_BankCode'] = $this->bankMap[$this->requestData['pg_BankCode']];
        $this->requestData['p4_Amt'] = sprintf('%.2f', $this->requestData['p4_Amt']);

        // 二維支付
        if (in_array($this->options['paymentVendorId'], [1103, 1107, 1111])) {
            $this->requestData['pa_FrpId'] = $this->requestData['pg_BankCode'];
            $this->requestData['pg_BankCode'] = '';

            // 設定支付平台需要的加密串
            $this->requestData['hmac'] = $this->encode();

            if (trim($this->options['verify_url']) == '') {
                throw new PaymentException('No verify_url specified', 180140);
            }

            $curlParam = [
                'method' => 'POST',
                'uri' => '/controller.action',
                'ip' => $this->options['verify_ip'],
                'host' => $this->options['verify_url'],
                'param' => urldecode(http_build_query($this->requestData)),
                'header' => [],
            ];

            $result = $this->curlRequest($curlParam);
            $parseData = json_decode($result, true);

            if (!isset($parseData['r1_Code']) || !isset($parseData['r7_Desc'])) {
                throw new PaymentConnectionException('Get pay parameters failed', 180128, $this->getEntryId());
            }

            if ($parseData['r1_Code'] != '1') {
                throw new PaymentConnectionException($parseData['r7_Desc'], 180130, $this->getEntryId());
            }

            if (!isset($parseData['r3_PayInfo'])) {
                throw new PaymentConnectionException('Get pay parameters failed', 180128, $this->getEntryId());
            }

            $this->setQrcode($parseData['r3_PayInfo']);

            return [];
        }

        // 手機支付
        if ($this->options['paymentVendorId'] == 1104) {
            $this->requestData['pa_FrpId'] = $this->requestData['pg_BankCode'];
            $this->requestData['pg_BankCode'] = '';
        }

        // 設定支付平台需要的加密串
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

        // 組織加密串
        foreach (array_keys($this->decodeParams) as $paymentKey) {
            if (array_key_exists($paymentKey, $this->options)) {
                $encodeStr .= $this->options[$paymentKey];
            }
        }

        // 沒有hmac就要丟例外，其他的參數在組織加密串的時候驗證過了
        if (!isset($this->options['hmac'])) {
            throw new PaymentException('No return parameter specified', 180137);
        }

        if ($this->options['hmac'] != hash_hmac('md5', $encodeStr, $this->privateKey)) {
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
     * 支付時的加密
     *
     * @return string
     */
    protected function encode()
    {
        $encodeStr = '';

        // 加密設定
        foreach ($this->encodeParams as $index) {
            $encodeStr .= $this->requestData[$index];
        }

        return hash_hmac('md5', $encodeStr, $this->privateKey);
    }
}
