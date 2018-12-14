<?php

namespace BB\DurianBundle\Payment;

use BB\DurianBundle\Exception\PaymentException;
use BB\DurianBundle\Exception\PaymentConnectionException;

/**
 * 中銀支付
 */
class ZhongYinPay extends PaymentBase
{
    /**
     * 支付時要傳給平台驗證的參數
     *
     * @var array
     */
    protected $requestData = [
        'pay_memberid' => '', // 商戶號
        'pay_orderid' => '', // 訂單號
        'pay_amount' => '', // 金額，單位元
        'pay_applydate' => '', // 訂單時間，格式Y-m-d H:i:s
        'pay_bankcode' => '', // 銀行編號
        'pay_notifyurl' => '', // 通知地址
        'pay_callbackurl' => '', // 返回地址，非必填
        'pay_md5sign' => '', // 簽名
        'pay_tongdao' => '', // 通道名
        'pay_productname' => '', // 商品名稱
    ];

    /**
     * 支付時支付平台參數與內部參數的對應
     *
     * @var array
     */
    protected $requireMap = [
        'pay_memberid' => 'number',
        'pay_orderid' => 'orderId',
        'pay_amount' => 'amount',
        'pay_applydate' => 'orderCreateDate',
        'pay_bankcode' => 'paymentVendorId',
        'pay_notifyurl' => 'notify_url',
        'pay_productname' => 'username',
    ];

    /**
     * 支付時需要加密的參數
     *
     * @var array
     */
    protected $encodeParams = [
        'pay_amount',
        'pay_applydate',
        'pay_bankcode',
        'pay_callbackurl',
        'pay_memberid',
        'pay_notifyurl',
        'pay_orderid',
    ];

    /**
     * 支付解密驗證時需要加密的參數
     *     0: 可不返回的參數
     *     1: 必要返回的參數
     *
     * @var array
     */
    protected $decodeParams = [
        'amount' => 1,
        'datetime' => 1,
        'memberid' => 1,
        'orderid' => 1,
        'returncode' => 1,
    ];

    /**
     * 應答機制訊息
     *
     * @var string
     */
    protected $msg = 'ok';

    /**
     * 支付平台支援的銀行對應編號
     *
     * @var array
     */
    protected $bankMap = [
        '1088' => 'YLWAP', // 銀聯_手機支付
        '1090' => 'WXPAY', // 微信_二維
        '1103' => 'QQPAY', // QQ_二維
        '1104' => 'QQWAP', // QQ_手機支付
    ];

    /**
     * 支付平台通道參數
     *
     * @var array
     */
    protected $tongdaoMap = [
        '1088' => 'Esdylwap', // 銀聯_手機支付
        '1090' => 'Qhwxsm', // 微信_二維
        '1103' => 'YqsQQ', // QQ_二維
        '1104' => 'Esdqqwap', // QQ_手機支付
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
        if (!array_key_exists($this->requestData['pay_bankcode'], $this->bankMap)) {
            throw new PaymentException('PaymentVendor is not supported by PaymentGateway', 180066);
        }

        // 額外的參數設定
        $date = new \DateTime($this->requestData['pay_applydate']);
        $this->requestData['pay_applydate'] = $date->format('Y-m-d H:i:s');
        $this->requestData['pay_orderid'] .= $this->requestData['pay_memberid'];
        $payBankcode = $this->requestData['pay_bankcode'];
        $this->requestData['pay_bankcode'] = $this->bankMap[$payBankcode];
        $this->requestData['pay_tongdao'] = $this->tongdaoMap[$payBankcode];

        // 設定支付平台需要的加密串
        $this->requestData['pay_md5sign'] = $this->encode();

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

        $encodeData = [];

        // 組織加密串
        foreach (array_keys($this->decodeParams) as $paymentKey) {
            if (array_key_exists($paymentKey, $this->options)) {
                $encodeData[$paymentKey] = $this->options[$paymentKey];
            }
        }

        $encodeData['key'] = $this->privateKey;

        // 依key1=value1&key2=value2&...&keyN=valueN之後做md5
        $encodeStr = urldecode(http_build_query($encodeData));

        // 如果沒有返回簽名要丟例外
        if (!isset($this->options['sign'])) {
            throw new PaymentException('No return parameter specified', 180137);
        }

        if ($this->options['sign'] != strtoupper(md5($encodeStr))) {
            throw new PaymentConnectionException('Signature verification failed', 180034, $this->getEntryId());
        }

        if ($this->options['returncode'] !== '00') {
            throw new PaymentConnectionException('Payment failure', 180035, $this->getEntryId());
        }

        // 返回參數orderid是由訂單號+商號組成
        $orderId = preg_replace("/{$this->options['memberid']}$/", '', $this->options['orderid']);

        if ($orderId != $entry['id']) {
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

        // 加密設定
        foreach ($this->encodeParams as $index) {
            $encodeData[$index] = $this->requestData[$index];
        }

        $encodeData['key'] = $this->privateKey;

        // 依key1=value1&key2=value2&...&keyN=valueN之後做md5
        $encodeStr = urldecode(http_build_query($encodeData));

        return strtoupper(md5($encodeStr));
    }
}