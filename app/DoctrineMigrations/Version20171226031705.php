<?php

namespace Application\Migrations;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20171226031705 extends AbstractMigration
{
    /**
     * @param Schema $schema
     */
    public function up(Schema $schema)
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() != 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql("INSERT INTO `payment_gateway` (`id`, `code`, `name`, `post_url`, `auto_reop`, `reop_url`, `verify_url`, `verify_ip`, `label`, `bind_ip`, `removed`, `withdraw`, `hot`, `order_id`, `upload_key`) VALUES (325, 'HuiDa', '匯達', 'http://epay.scqyda.com/cgi-bin/netpayment/pay_gate.cgi', '0', '', '', '', 'HuiDa', '1', '0', '0', '1', '228', '0')");
        $this->addSql("INSERT INTO `payment_gateway_currency` (`payment_gateway_id`, `currency`) VALUES (325, 156)");
        $this->addSql("INSERT INTO `payment_gateway_has_payment_method` (`payment_gateway_id`, `payment_method_id`) VALUES ('325', '1'), ('325', '3'), ('325', '8')");
        $this->addSql("INSERT INTO `payment_gateway_has_payment_vendor` (`payment_gateway_id`, `payment_vendor_id`) VALUES ('325', '1'), ('325', '2'), ('325', '3'), ('325', '4'), ('325', '5'), ('325', '6'), ('325', '8'), ('325', '9'), ('325', '10'), ('325', '11'), ('325', '12'), ('325', '13'), ('325', '14'), ('325', '15'), ('325', '16'), ('325', '17'), ('325', '222'), ('325', '1090'), ('325', '1097'), ('325', '1103')");
        $this->addSql("INSERT INTO `payment_gateway_description` (`payment_gateway_id`, `name`, `value`) VALUES ('325', 'number', ''), ('325', 'private_key', ''), ('325', 'platformID', '')");
        $this->addSql("INSERT INTO `payment_gateway_bind_ip` (`payment_gateway_id`, `ip`) VALUES ('325', '2345078182')");
    }

    /**
     * @param Schema $schema
     */
    public function down(Schema $schema)
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() != 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql("DELETE FROM `payment_gateway_bind_ip` WHERE `payment_gateway_id` = '325' AND `ip` = '2345078182'");
        $this->addSql("DELETE FROM `payment_gateway_description` WHERE `payment_gateway_id` = '325'");
        $this->addSql("DELETE FROM `payment_gateway_has_payment_vendor` WHERE `payment_gateway_id` = '325' AND `payment_vendor_id` IN ('1', '2', '3', '4', '5', '6', '8', '9', '10', '11', '12', '13', '14', '15', '16', '17', '222', '1090', '1097', '1103')");
        $this->addSql("DELETE FROM `payment_gateway_has_payment_method` WHERE `payment_gateway_id` = '325' AND `payment_method_id` IN ('1', '3', '8')");
        $this->addSql("DELETE FROM `payment_gateway_currency` WHERE `currency` = '156' AND `payment_gateway_id` = '325'");
        $this->addSql("DELETE FROM `payment_gateway` WHERE `id` = '325'");
    }
}