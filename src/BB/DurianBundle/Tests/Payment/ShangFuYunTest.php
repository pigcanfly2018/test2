<?php

namespace BB\DurianBundle\Tests\Payment;

use BB\DurianBundle\Tests\DurianTestCase;
use BB\DurianBundle\Payment\ShangFuYun;
use Buzz\Message\Response;

class ShangFuYunTest extends DurianTestCase
{
    /**
     * @var \Symfony\Component\DependencyInjection\ContainerInterface
     */
    private $container;

    /**
     * @var \Buzz\Client\Curl
     */
    private $client;

    public function setUp()
    {
        parent::setUp();

        $mockLogger = $this->getMockBuilder('BB\DurianBundle\Logger\Payment')
            ->disableOriginalConstructor()
            ->setMethods(['record'])
            ->getMock();
        $mockLogger->expects($this->any())
            ->method('record')
            ->willReturn(null);

        $this->container = $this->getMockBuilder('Symfony\Component\DependencyInjection\Container')
            ->setMethods(['get'])
            ->getMock();

        $this->container->expects($this->any())
            ->method('get')
            ->willReturn($mockLogger);

        $this->client = $this->getMockBuilder('Buzz\Client\Curl')->getMock();
    }

    /**
     * 測試支付時缺少私鑰
     */
    public function testPayWithoutPrivateKey()
    {
        $this->setExpectedException(
            'BB\DurianBundle\Exception\PaymentException',
            'No privateKey specified',
            180142
        );

        $shangFuYun = new ShangFuYun();
        $shangFuYun->getVerifyData();
    }

    /**
     * 測試支付時未指定支付參數
     */
    public function testPayWithNoPayParameterSpecified()
    {
        $this->setExpectedException(
            'BB\DurianBundle\Exception\PaymentException',
            'No pay parameter specified',
            180145
        );

        $sourceData = ['number' => ''];

        $shangFuYun = new ShangFuYun();
        $shangFuYun->setPrivateKey('test');
        $shangFuYun->setOptions($sourceData);
        $shangFuYun->getVerifyData();
    }

    /**
     * 測試支付時代入支付平台不支援的銀行
     */
    public function testPayUnsupportedPaymentVendor()
    {
        $this->setExpectedException(
            'BB\DurianBundle\Exception\PaymentException',
            'PaymentVendor is not supported by PaymentGateway',
            180066
        );

        $sourceData = [
            'number' => '2018032222010002',
            'paymentVendorId' => '9999',
            'amount' => '0.01',
            'orderId' => '201804100000011906',
            'orderCreateDate' => '2018-04-10 10:00:44',
            'username' => 'php1test',
            'ip' => '111.235.135.54',
            'notify_url' => 'http://fufutest.000webhostapp.com/pay/',
            'verify_url' => 'payment.http.gate.xxxxpay.com',
            'verify_ip' => ['172.26.54.42', '172.26.54.41'],
        ];

        $shangFuYun = new ShangFuYun();
        $shangFuYun->setPrivateKey('test');
        $shangFuYun->setOptions($sourceData);
        $shangFuYun->getVerifyData();
    }

    /**
     * 測試支付時缺少verify_url
     */
    public function testPayWithoutVerifyUrl()
    {
        $this->setExpectedException(
            'BB\DurianBundle\Exception\PaymentException',
            'No verify_url specified',
            180140
        );

        $sourceData = [
            'number' => '2018032222010002',
            'paymentVendorId' => '1090',
            'amount' => '0.01',
            'orderId' => '201804100000011906',
            'orderCreateDate' => '2018-04-10 10:00:44',
            'username' => 'php1test',
            'ip' => '111.235.135.54',
            'notify_url' => 'http://fufutest.000webhostapp.com/pay/',
            'verify_url' => '',
            'verify_ip' => ['172.26.54.42', '172.26.54.41'],
        ];

        $shangFuYun = new ShangFuYun();
        $shangFuYun->setPrivateKey('test');
        $shangFuYun->setOptions($sourceData);
        $shangFuYun->getVerifyData();
    }

    /**
     * 測試支付時返回缺少code
     */
    public function testPayReturnWithoutCode()
    {
        $this->setExpectedException(
            'BB\DurianBundle\Exception\PaymentConnectionException',
            'Get pay parameters failed',
            180128
        );

        $result = '<?xml version="1.0" encoding="utf-8"?><message><detail><desc>通讯成功</desc>' .
            '<qrCode>d2VpeGluOi8vd3hwYXkvYml6cGF5dXJsP3ByPUp3Q1lmOWY=</qrCode></detail>' .
            '<sign>C73B8AB30DE61E67F476084EF663D47A</sign></message>';

        $response = new Response();
        $response->setContent($result);
        $response->addHeader('HTTP/1.1 200 OK');

        $sourceData = [
            'number' => '2018032222010002',
            'paymentVendorId' => '1090',
            'amount' => '0.01',
            'orderId' => '201804100000011906',
            'orderCreateDate' => '2018-04-10 10:00:44',
            'username' => 'php1test',
            'ip' => '111.235.135.54',
            'notify_url' => 'http://fufutest.000webhostapp.com/pay/',
            'verify_url' => 'payment.http.gate.xxxxpay.com',
            'verify_ip' => ['172.26.54.42', '172.26.54.41'],
        ];

        $shangFuYun = new ShangFuYun();
        $shangFuYun->setPrivateKey('test');
        $shangFuYun->setContainer($this->container);
        $shangFuYun->setClient($this->client);
        $shangFuYun->setResponse($response);
        $shangFuYun->setOptions($sourceData);
        $shangFuYun->getVerifyData();
    }

    /**
     * 測試支付時返回提交失敗
     */
    public function testPayReturnFailure()
    {
        $this->setExpectedException(
            'BB\DurianBundle\Exception\PaymentConnectionException',
            '银行交易不成功[金额不合法1.0]',
            180130
        );

        $result = '<?xml version="1.0" encoding="UTF-8"?><message><detail><code>59</code>' .
            '<desc>银行交易不成功[金额不合法1.0]</desc></detail><sign>B751BFD3EDB030863A6FC900A23D0378</sign></message>';

        $response = new Response();
        $response->setContent($result);
        $response->addHeader('HTTP/1.1 200 OK');

        $sourceData = [
            'number' => '2018032222010002',
            'paymentVendorId' => '1090',
            'amount' => '0.01',
            'orderId' => '201804100000011906',
            'orderCreateDate' => '2018-04-10 10:00:44',
            'username' => 'php1test',
            'ip' => '111.235.135.54',
            'notify_url' => 'http://fufutest.000webhostapp.com/pay/',
            'verify_url' => 'payment.http.gate.xxxxpay.com',
            'verify_ip' => ['172.26.54.42', '172.26.54.41'],
        ];

        $shangFuYun = new ShangFuYun();
        $shangFuYun->setPrivateKey('test');
        $shangFuYun->setContainer($this->container);
        $shangFuYun->setClient($this->client);
        $shangFuYun->setResponse($response);
        $shangFuYun->setOptions($sourceData);
        $shangFuYun->getVerifyData();
    }

    /**
     * 測試支付時返回缺少qrCode
     */
    public function testPayReturnWithoutQRCode()
    {
        $this->setExpectedException(
            'BB\DurianBundle\Exception\PaymentConnectionException',
            'Get pay parameters failed',
            180128
        );

        $result = '<?xml version="1.0" encoding="utf-8"?><message><detail><code>00</code><desc>交易完成</desc>' .
            '</detail><sign>C73B8AB30DE61E67F476084EF663D47A</sign></message>';

        $response = new Response();
        $response->setContent($result);
        $response->addHeader('HTTP/1.1 200 OK');

        $sourceData = [
            'number' => '2018032222010002',
            'paymentVendorId' => '1090',
            'amount' => '0.01',
            'orderId' => '201804100000011906',
            'orderCreateDate' => '2018-04-10 10:00:44',
            'username' => 'php1test',
            'ip' => '111.235.135.54',
            'notify_url' => 'http://fufutest.000webhostapp.com/pay/',
            'verify_url' => 'payment.http.gate.xxxxpay.com',
            'verify_ip' => ['172.26.54.42', '172.26.54.41'],
        ];

        $shangFuYun = new ShangFuYun();
        $shangFuYun->setPrivateKey('test');
        $shangFuYun->setContainer($this->container);
        $shangFuYun->setClient($this->client);
        $shangFuYun->setResponse($response);
        $shangFuYun->setOptions($sourceData);
        $shangFuYun->getVerifyData();
    }

    /**
     * 測試二維支付
     */
    public function testQrcodePay()
    {
        $result = '<?xml version="1.0" encoding="utf-8"?><message><detail><code>00</code><desc>交易完成</desc>' .
            '<qrCode>d2VpeGluOi8vd3hwYXkvYml6cGF5dXJsP3ByPUp3Q1lmOWY=</qrCode></detail>' .
            '<sign>C73B8AB30DE61E67F476084EF663D47A</sign></message>';

        $response = new Response();
        $response->setContent($result);
        $response->addHeader('HTTP/1.1 200 OK');

        $sourceData = [
            'number' => '2018032222010002',
            'paymentVendorId' => '1090',
            'amount' => '0.01',
            'orderId' => '201804100000011906',
            'orderCreateDate' => '2018-04-10 10:00:44',
            'username' => 'php1test',
            'ip' => '111.235.135.54',
            'notify_url' => 'http://fufutest.000webhostapp.com/pay/',
            'verify_url' => 'payment.http.gate.xxxxpay.com',
            'verify_ip' => ['172.26.54.42', '172.26.54.41'],
        ];

        $shangFuYun = new ShangFuYun();
        $shangFuYun->setPrivateKey('test');
        $shangFuYun->setContainer($this->container);
        $shangFuYun->setClient($this->client);
        $shangFuYun->setResponse($response);
        $shangFuYun->setOptions($sourceData);
        $verifyData = $shangFuYun->getVerifyData();

        $this->assertEmpty($verifyData);
        $this->assertSame('weixin://wxpay/bizpayurl?pr=JwCYf9f', $shangFuYun->getQrcode());
    }

    /**
     * 測試手機支付
     */
    public function testPhonePay()
    {
        $sourceData = [
            'number' => '2018032222010002',
            'paymentVendorId' => '1097',
            'amount' => '0.01',
            'orderId' => '201804100000011906',
            'orderCreateDate' => '2018-04-10 10:00:44',
            'username' => 'php1test',
            'ip' => '111.235.135.54',
            'notify_url' => 'http://fufutest.000webhostapp.com/pay/',
        ];

        $shangFuYun = new ShangFuYun();
        $shangFuYun->setPrivateKey('test');
        $shangFuYun->setOptions($sourceData);
        $verifyData = $shangFuYun->getVerifyData();

        $this->assertEquals('TRADE.H5PAY', $verifyData['service']);
        $this->assertEquals('2018032222010002', $verifyData['merId']);
        $this->assertEquals('201804100000011906', $verifyData['tradeNo']);
        $this->assertEquals('20180410', $verifyData['tradeDate']);
        $this->assertEquals('0.01', $verifyData['amount']);
        $this->assertEquals('http://fufutest.000webhostapp.com/pay/', $verifyData['notifyUrl']);
        $this->assertEquals('php1test', $verifyData['summary']);
        $this->assertEquals('111.235.135.54', $verifyData['clientIp']);
        $this->assertEquals('705e2c622f8e4089707ced783f67cbb1', $verifyData['sign']);
        $this->assertEquals('2', $verifyData['typeId']);
        $this->assertArrayNotHasKey('bankId', $verifyData);
    }

    /**
     * 測試網銀支付
     */
    public function testBankPay()
    {
        $sourceData = [
            'number' => '2018032222010002',
            'paymentVendorId' => '1',
            'amount' => '0.01',
            'orderId' => '201804100000011906',
            'orderCreateDate' => '2018-04-10 10:00:44',
            'username' => 'php1test',
            'ip' => '111.235.135.54',
            'notify_url' => 'http://fufutest.000webhostapp.com/pay/',
        ];

        $shangFuYun = new ShangFuYun();
        $shangFuYun->setPrivateKey('test');
        $shangFuYun->setOptions($sourceData);
        $verifyData = $shangFuYun->getVerifyData();

        $this->assertEquals('TRADE.B2C', $verifyData['service']);
        $this->assertEquals('2018032222010002', $verifyData['merId']);
        $this->assertEquals('201804100000011906', $verifyData['tradeNo']);
        $this->assertEquals('20180410', $verifyData['tradeDate']);
        $this->assertEquals('0.01', $verifyData['amount']);
        $this->assertEquals('http://fufutest.000webhostapp.com/pay/', $verifyData['notifyUrl']);
        $this->assertEquals('php1test', $verifyData['summary']);
        $this->assertEquals('111.235.135.54', $verifyData['clientIp']);
        $this->assertEquals('6717dc764dd773a955418ca4de78f9eb', $verifyData['sign']);
        $this->assertEquals('ICBC', $verifyData['bankId']);
    }

    /**
     * 測試返回時缺少私鑰
     */
    public function testReturnWithoutPrivateKey()
    {
        $this->setExpectedException(
            'BB\DurianBundle\Exception\PaymentException',
            'No privateKey specified',
            180142
        );

        $shangFuYun = new ShangFuYun();
        $shangFuYun->verifyOrderPayment([]);
    }

    /**
     * 測試返回時未指定返回參數
     */
    public function testReturnWithNoReturnParameterSpecified()
    {
        $this->setExpectedException(
            'BB\DurianBundle\Exception\PaymentException',
            'No return parameter specified',
            180137
        );

        $shangFuYun = new ShangFuYun();
        $shangFuYun->setPrivateKey('test');
        $shangFuYun->verifyOrderPayment([]);
    }

    /**
     * 測試返回時支付平台缺少回傳sign
     */
    public function testReturnWithoutSign()
    {
        $this->setExpectedException(
            'BB\DurianBundle\Exception\PaymentException',
            'No return parameter specified',
            180137
        );

        $sourceData = [
            'service' => 'TRADE.NOTIFY',
            'merId' => '2018032222010002',
            'tradeNo' => '201804100000011906',
            'tradeDate' => '20171016',
            'opeNo' => '9798238',
            'opeDate' => '20171016',
            'amount' => '0.01',
            'status' => '1',
            'extra' => '',
            'payTime' => '20171016095751',
            'notifyType' => '1',
        ];

        $shangFuYun = new ShangFuYun();
        $shangFuYun->setPrivateKey('test');
        $shangFuYun->setOptions($sourceData);
        $shangFuYun->verifyOrderPayment([]);
    }

    /**
     * 測試返回時sign簽名驗證錯誤
     */
    public function testReturnSignatureVerificationFailed()
    {
        $this->setExpectedException(
            'BB\DurianBundle\Exception\PaymentConnectionException',
            'Signature verification failed',
            180034
        );

        $sourceData = [
            'service' => 'TRADE.NOTIFY',
            'merId' => '2018032222010002',
            'tradeNo' => '201804100000011906',
            'tradeDate' => '20171016',
            'opeNo' => '9798238',
            'opeDate' => '20171016',
            'amount' => '0.01',
            'status' => '1',
            'extra' => '',
            'payTime' => '20171016095751',
            'sign' => 'D914118A73B8C4EC16A7FA9B3DA682EC',
            'notifyType' => '1',
        ];

        $shangFuYun = new ShangFuYun();
        $shangFuYun->setPrivateKey('test');
        $shangFuYun->setOptions($sourceData);
        $shangFuYun->verifyOrderPayment([]);
    }

    /**
     * 測試返回結果為支付失敗
     */
    public function testReturnPaymentFailure()
    {
        $this->setExpectedException(
            'BB\DurianBundle\Exception\PaymentConnectionException',
            'Payment failure',
            180035
        );

        $sourceData = [
            'service' => 'TRADE.SCANPAY',
            'merId' => '2017090633010146',
            'tradeNo' => '201710050000001416',
            'tradeDate' => '20171005',
            'opeNo' => '147504',
            'opeDate' => '20171005',
            'amount' => '0.01',
            'status' => '0',
            'extra' => '',
            'payTime' => '20171005160044',
            'sign' => '5e8fa24155b94fb461111cafc5f786d8',
            'notifyType' => '1',
        ];

        $shangFuYun = new ShangFuYun();
        $shangFuYun->setPrivateKey('test');
        $shangFuYun->setOptions($sourceData);
        $shangFuYun->verifyOrderPayment([]);
    }

    /**
     * 測試返回結果為訂單編號錯誤
     */
    public function testReturnOrderIdFailed()
    {
        $this->setExpectedException(
            'BB\DurianBundle\Exception\PaymentException',
            'Order Id error',
            180061
        );

        $sourceData = [
            'service' => 'TRADE.SCANPAY',
            'merId' => '2017090633010146',
            'tradeNo' => '201710050000001416',
            'tradeDate' => '20171005',
            'opeNo' => '147504',
            'opeDate' => '20171005',
            'amount' => '0.01',
            'status' => '1',
            'extra' => '',
            'payTime' => '20171005160044',
            'sign' => '007cf72fb1f921504d5542e1391957a4',
            'notifyType' => '1',
        ];

        $entry = [
            'id' => '201710050000001417',
            'amount' => '0.01',
        ];

        $shangFuYun = new ShangFuYun();
        $shangFuYun->setPrivateKey('test');
        $shangFuYun->setOptions($sourceData);
        $shangFuYun->verifyOrderPayment($entry);
    }

    /**
     * 測試返回結果為訂單金額錯誤
     */
    public function testReturnAmountFailed()
    {
        $this->setExpectedException(
            'BB\DurianBundle\Exception\PaymentException',
            'Order Amount error',
            180058
        );

        $sourceData = [
            'service' => 'TRADE.SCANPAY',
            'merId' => '2017090633010146',
            'tradeNo' => '201710050000001416',
            'tradeDate' => '20171005',
            'opeNo' => '147504',
            'opeDate' => '20171005',
            'amount' => '0.01',
            'status' => '1',
            'extra' => '',
            'payTime' => '20171005160044',
            'sign' => '007cf72fb1f921504d5542e1391957a4',
            'notifyType' => '1',
        ];

        $entry = [
            'id' => '201710050000001416',
            'amount' => '1.00',
        ];

        $shangFuYun = new ShangFuYun();
        $shangFuYun->setPrivateKey('test');
        $shangFuYun->setOptions($sourceData);
        $shangFuYun->verifyOrderPayment($entry);
    }

    /**
     * 測試支付驗證成功
     */
    public function testPaySuccess()
    {
        $sourceData = [
            'service' => 'TRADE.SCANPAY',
            'merId' => '2017090633010146',
            'tradeNo' => '201710050000001416',
            'tradeDate' => '20171005',
            'opeNo' => '147504',
            'opeDate' => '20171005',
            'amount' => '0.01',
            'status' => '1',
            'extra' => '',
            'payTime' => '20171005160044',
            'sign' => '007cf72fb1f921504d5542e1391957a4',
            'notifyType' => '1',
        ];

        $entry = [
            'id' => '201710050000001416',
            'amount' => '0.01',
        ];

        $shangFuYun = new ShangFuYun();
        $shangFuYun->setPrivateKey('test');
        $shangFuYun->setOptions($sourceData);
        $shangFuYun->verifyOrderPayment($entry);

        $this->assertEquals('SUCCESS', $shangFuYun->getMsg());
    }
}