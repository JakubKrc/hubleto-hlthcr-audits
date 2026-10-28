<?php

namespace Hubleto\App\External\WaiBlue\HlthcrAudits\Controllers\Boards;

use Hubleto\App\External\WaiBlue\HlthcrAudits\Models\Audit;

/**
 * Home dashboard panel "Najbližšie audity (max 1 mesiac)" (legacy home of hygienists): audits of own branches.
 */
class UpcomingAudits extends \Hubleto\Erp\Controller
{
  public bool $hideDefaultDesktop = true;

  public function prepareView(): void
  {
    parent::prepareView();

    $this->viewParams['audits'] = $this->getModel(Audit::class)->record->prepareReadQuery()
      ->with(['BRANCH'])
      ->where('hlthcr_audits.date_executed', '>=', date('Y-m-d'))
      ->where('hlthcr_audits.date_executed', '<=', date('Y-m-d', strtotime('+1 month')))
      ->orderBy('hlthcr_audits.date_executed', 'asc')
      ->get()
      ->toArray()
    ;
    $this->setView('@Hubleto:App:External:WaiBlue:HlthcrAudits/Boards/UpcomingAudits.twig');
  }
}
