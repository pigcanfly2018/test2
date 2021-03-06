<?php

namespace Application\Migrations;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20180424130059 extends AbstractMigration
{
    /**
     * @param Schema $schema
     */
    public function up(Schema $schema)
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() != 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql("INSERT INTO `payment_gateway` (`id`, `code`, `name`, `post_url`, `auto_reop`, `reop_url`, `verify_url`, `verify_ip`, `label`, `bind_ip`, `removed`, `withdraw`, `hot`, `order_id`, `upload_key`) VALUES ('451', 'YW', 'YW大商城', 'https://ywpay3.8qye.com', '0', '', 'payment.https.ywpay3.8qye.com', '', 'YW', '1', '0', '0', '1', '354', '0')");
        $this->addSql("INSERT INTO `payment_gateway_currency` (`payment_gateway_id`, `currency`) VALUES ('451', '156')");
        $this->addSql("INSERT INTO `payment_gateway_has_payment_method` (`payment_gateway_id`, `payment_method_id`) VALUES ('451', '3'), ('451', '8')");
        $this->addSql("INSERT INTO `payment_gateway_has_payment_vendor` (`payment_gateway_id`, `payment_vendor_id`) VALUES ('451', '1090'), ('451', '1092'), ('451', '1097')");
        $this->addSql("INSERT INTO `payment_gateway_description` (`payment_gateway_id`, `name`, `value`) VALUES ('451', 'number', ''), ('451', 'private_key', '')");
        $this->addSql("INSERT INTO `payment_gateway_bind_ip` (`payment_gateway_id`, `ip`) VALUES ('451', '401313234'), ('451', '2487359626'), ('451', '599966692')");
    }

    /**
     * @param Schema $schema
     */
    public function down(Schema $schema)
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() != 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql("DELETE FROM `payment_gateway_bind_ip` WHERE `payment_gateway_id` = '451' AND `ip` IN ('401313234', '2487359626', '599966692')");
        $this->addSql("DELETE FROM `payment_gateway_description` WHERE `payment_gateway_id` = '451'");
        $this->addSql("DELETE FROM `payment_gateway_has_payment_vendor` WHERE `payment_gateway_id` = '451' AND `payment_vendor_id` IN ('1090', '1092', '1097')");
        $this->addSql("DELETE FROM `payment_gateway_has_payment_method` WHERE `payment_gateway_id` = '451' AND `payment_method_id` IN ('3', '8')");
        $this->addSql("DELETE FROM `payment_gateway_currency` WHERE `currency` = '156' AND `payment_gateway_id` = '451'");
        $this->addSql("DELETE FROM `payment_gateway` WHERE `id` = '451'");
    }
}
