<?php

namespace Hubleto\App\External\WaiBlue\HlthcrAudits\Controllers;

class Settings extends \Hubleto\Erp\Controller
{
  public function getBreadcrumbs(): array
  {
    return array_merge(parent::getBreadcrumbs(), [
      ['url' => 'hlthcr-audits', 'content' => $this->translate('Audits')],
      ['url' => 'settings', 'content' => $this->translate('Settings')],
    ]);
  }

  public function prepareView(): void
  {
    parent::prepareView();
    $this->setView('@Hubleto:App:External:WaiBlue:HlthcrAudits/Settings.twig');
  }
}
