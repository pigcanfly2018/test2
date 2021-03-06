<?php

namespace Application\Migrations;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20180521132738 extends AbstractMigration
{
    /**
     * @param Schema $schema
     */
    public function up(Schema $schema)
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() != 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql("INSERT INTO `payment_gateway` (`id`, `code`, `name`, `post_url`, `auto_reop`, `reop_url`, `verify_url`, `verify_ip`, `label`, `bind_ip`, `removed`, `withdraw`, `hot`, `order_id`, `upload_key`) VALUES (476, 'ELongPay', '亿龙支付', 'http://npay.elongpay.com/trade/create', '0', '', 'payment.http.npay.elongpay.com', '', 'ELongPay', 0, 0, 0, 1, '379', 0)");
        $this->addSql("INSERT INTO `payment_gateway_currency` (`payment_gateway_id`, `currency`) VALUES ('476', '156')");
        $this->addSql("INSERT INTO `payment_gateway_has_payment_method` (`payment_gateway_id`, `payment_method_id`) VALUES ('476', '3'), ('476', '8')");
        $this->addSql("INSERT INTO `payment_gateway_has_payment_vendor` (`payment_gateway_id`, `payment_vendor_id`) VALUES ('476', '1092'), ('476', '1098')");
        $this->addSql("INSERT INTO `payment_gateway_description` (`payment_gateway_id`, `name`, `value`) VALUES ('476', 'number', ''), ('476', 'private_key', '')");
    }

    /**
     * @param Schema $schema
     */
    public function down(Schema $schema)
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() != 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql("DELETE FROM `payment_gateway_description` WHERE `payment_gateway_id` = '476'");
        $this->addSql("DELETE FROM `payment_gateway_has_payment_vendor` WHERE `payment_gateway_id` = '476' AND `payment_vendor_id` IN ('1092', '1098')");
        $this->addSql("DELETE FROM `payment_gateway_has_payment_method` WHERE `payment_gateway_id` = '476' AND `payment_method_id` IN ('3', '8')");
        $this->addSql("DELETE FROM `payment_gateway_currency` WHERE `currency` = '156' AND `payment_gateway_id` = '476'");
        $this->addSql("DELETE FROM `payment_gateway` WHERE `id` = '476'");
    }
}
