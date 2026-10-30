<?php

namespace Hubleto\App\External\WaiBlue\HlthcrAudits\Models\Migrations;

use Hubleto\App\External\WaiBlue\HlthcrAudits\Loader;
use Hubleto\Framework\Migration;

// Existing installs: the audit workflow and a state for every audit ("php hubleto migrate" does not run installApp()).
class Audit_0005 extends Migration
{
  public function upgradeSchema(): void
  {
    /** @var Loader */
    $auditsApp = $this->model->getService(Loader::class);
    $auditsApp->installAuditWorkflow();
    $auditsApp->putAuditsWithoutStateIntoWorkflow();
  }

  public function downgradeSchema(): void
  {
  }

  public function upgradeForeignKeys(): void
  {
  }

  public function downgradeForeignKeys(): void
  {
  }
}
