<?php

namespace Application\Migrations;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20171207074715 extends AbstractMigration
{
    /**
     * @param Schema $schema
     */
    public function up(Schema $schema)
    {
        // this up() migration is auto-generated, please modify it to your needs

        $this->abortIf($this->connection->getDatabasePlatform()->getName() != 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql("INSERT INTO `payment_gateway` (`id`, `code`, `name`, `post_url`, `auto_reop`, `reop_url`, `verify_url`, `verify_ip`, `label`, `bind_ip`, `removed`, `withdraw`, `hot`, `order_id`, `upload_key`) VALUES (311, 'HeYiFuu', '合易付', 'http://pay.heyifuu.cn/cgi-bin/netpayment/pay_gate.cgi', '0', '', '', '', 'HeYiFuu', '0', '0', '0', '1', '214', '0')");
        $this->addSql("INSERT INTO `payment_gateway_currency` (`payment_gateway_id`, `currency`) VALUES (311, 156)");
        $this->addSql("INSERT INTO `payment_gateway_has_payment_method` (`payment_gateway_id`, `payment_method_id`) VALUES ('311', '1'), ('311', '8')");
        $this->addSql("INSERT INTO `payment_gateway_has_payment_vendor` (`payment_gateway_id`, `payment_vendor_id`) VALUES ('311', '1'), ('311', '2'), ('311', '3'), ('311', '4'), ('311', '5'), ('311', '6'), ('311', '8'), ('311', '10'), ('311', '11'), ('311', '12'), ('311', '13'), ('311', '14'), ('311', '15'), ('311', '16'), ('311', '17'), ('311', '1090'), ('311', '1092'), ('311', '1103')");
        $this->addSql("INSERT INTO payment_gateway_description (payment_gateway_id, name, value) VALUES ('311', 'number', ''), ('311', 'private_key', ''), ('311', 'platformID', '')");
    }

    /**
     * @param Schema $schema
     */
    public function down(Schema $schema)
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() != 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql("DELETE FROM `payment_gateway_description` WHERE `payment_gateway_id` = '311'");
        $this->addSql("DELETE FROM `payment_gateway_has_payment_vendor` WHERE `payment_gateway_id` = '311' AND `payment_vendor_id` IN ('1', '2', '3', '4', '5', '6', '8', '10', '11', '12', '13', '14', '15', '16', '17', '1090', '1092', '1103')");
        $this->addSql("DELETE FROM `payment_gateway_has_payment_method` WHERE `payment_gateway_id` = '311' AND `payment_method_id` IN ('1', '8')");
        $this->addSql("DELETE FROM `payment_gateway_currency` WHERE `currency` = '156' AND `payment_gateway_id` = '311'");
        $this->addSql("DELETE FROM `payment_gateway` WHERE `id` = '311'");
    }
}
