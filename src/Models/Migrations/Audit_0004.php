<?php

namespace Hubleto\App\External\WaiBlue\HlthcrAudits\Models\Migrations;

use Hubleto\Framework\Migration;

// The audit state is a workflow step (workflow review Q1). Existing audits get their state in the Loader.
class Audit_0004 extends Migration
{
  public function upgradeSchema(): void
  {
    $this->db->execute("ALTER TABLE `hlthcr_audits` ADD COLUMN `id_workflow` int(8) NULL DEFAULT NULL");
    $this->db->execute("ALTER TABLE `hlthcr_audits` ADD COLUMN `id_workflow_step` int(8) NULL DEFAULT NULL");
    $this->db->execute("ALTER TABLE `hlthcr_audits` ADD INDEX `id_workflow` (`id_workflow`)");
    $this->db->execute("ALTER TABLE `hlthcr_audits` ADD INDEX `id_workflow_step` (`id_workflow_step`)");
  }

  public function downgradeSchema(): void
  {
    $this->db->execute("ALTER TABLE `hlthcr_audits` DROP COLUMN `id_workflow_step`");
    $this->db->execute("ALTER TABLE `hlthcr_audits` DROP COLUMN `id_workflow`");
  }

  public function upgradeForeignKeys(): void
  {
    $this->db->execute("ALTER TABLE `hlthcr_audits` ADD CONSTRAINT `fk__hlthcr_audits__id_workflow` FOREIGN KEY (`id_workflow`) REFERENCES `workflows` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT");
    $this->db->execute("ALTER TABLE `hlthcr_audits` ADD CONSTRAINT `fk__hlthcr_audits__id_workflow_step` FOREIGN KEY (`id_workflow_step`) REFERENCES `workflow_steps` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT");
  }

  public function downgradeForeignKeys(): void
  {
    $this->db->execute("ALTER TABLE `hlthcr_audits` DROP FOREIGN KEY `fk__hlthcr_audits__id_workflow`");
    $this->db->execute("ALTER TABLE `hlthcr_audits` DROP FOREIGN KEY `fk__hlthcr_audits__id_workflow_step`");
  }
}
