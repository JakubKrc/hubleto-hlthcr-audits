<?php

namespace Hubleto\App\External\WaiBlue\HlthcrAudits\Models\Migrations;

use Hubleto\Framework\Migration;

// Foreign key of the audit's hygienist (models and lookups review B6).
class Audit_0003 extends Migration
{
  public function upgradeSchema(): void
  {
  }

  public function downgradeSchema(): void
  {
  }

  public function upgradeForeignKeys(): void
  {
    $this->db->execute("ALTER TABLE `hlthcr_audits` ADD CONSTRAINT `fk__hlthcr_audits__id_hygienist` FOREIGN KEY (`id_hygienist`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT");
  }

  public function downgradeForeignKeys(): void
  {
    $this->db->execute("ALTER TABLE `hlthcr_audits` DROP FOREIGN KEY `fk__hlthcr_audits__id_hygienist`");
  }
}
