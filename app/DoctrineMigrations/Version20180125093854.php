<?php

namespace Application\Migrations;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20180125093854 extends AbstractMigration
{
    /**
     * @param Schema $schema
     */
    public function up(Schema $schema)
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() != 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql("INSERT INTO payment_gateway (id, code, name, post_url, auto_reop, reop_url, verify_url, verify_ip, label, bind_ip, removed, withdraw, hot, order_id, upload_key) VALUES ('364', 'SinPay', '鑫支付', 'http://pay.api.tzinformation.com/api/wypay/createOrder', '0', '', 'payment.http.pay.api.tzinformation.com', '', 'SinPay', '1', '0', '0', '1', '267', '0')");
        $this->addSql("INSERT INTO payment_gateway_currency (payment_gateway_id, currency) VALUES ('364', '156')");
        $this->addSql("INSERT INTO payment_gateway_has_payment_method (payment_gateway_id, payment_method_id) VALUES ('364', '8')");
        $this->addSql("INSERT INTO payment_gateway_has_payment_vendor (payment_gateway_id, payment_vendor_id) VALUES ('364', '1090'), ('364', '1092'), ('364', '1103')");
        $this->addSql("INSERT INTO payment_gateway_description (payment_gateway_id, name, value) VALUES ('364', 'number', ''), ('364', 'private_key' ,'')");
        $this->addSql("INSERT INTO payment_gateway_bind_ip (payment_gateway_id, ip) VALUES ('364', '793453031')");
    }

    /**
     * @param Schema $schema
     */
    public function down(Schema $schema)
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() != 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql("DELETE FROM payment_gateway_bind_ip WHERE payment_gateway_id = '364' AND ip = '793453031'");
        $this->addSql("DELETE FROM payment_gateway_description WHERE payment_gateway_id = '364'");
        $this->addSql("DELETE FROM payment_gateway_has_payment_vendor WHERE payment_gateway_id = '364' AND payment_vendor_id IN ('1090', '1092', '1103')");
        $this->addSql("DELETE FROM payment_gateway_has_payment_method WHERE payment_gateway_id = '364' AND payment_method_id = '8'");
        $this->addSql("DELETE FROM payment_gateway_currency WHERE payment_gateway_id = '364' AND currency = '156'");
        $this->addSql("DELETE FROM payment_gateway WHERE id = '364'");
    }
}

