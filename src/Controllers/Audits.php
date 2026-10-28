<?php

namespace Hubleto\App\External\WaiBlue\HlthcrAudits\Controllers;

class Audits extends \Hubleto\Erp\Controller
{
  public function prepareView(): void
  {
    parent::prepareView();
    $this->setView('@Hubleto:App:External:WaiBlue:HlthcrAudits/Audits.twig');
  }
}
