<?php

namespace Hubleto\App\External\WaiBlue\HlthcrAudits;

/**
 * Audits on the Workflow kanban board, grouped by state.
 */
class Workflow extends \Hubleto\App\Community\Workflow\Workflow
{
  public function loadItems(int $idWorkflow, array $filters): array
  {
    /** @var Models\Audit */
    $mAudit = $this->getModel(Models\Audit::class);
    $query = $mAudit->record->prepareReadQuery()
      ->where('hlthcr_audits.id_workflow', $idWorkflow)
      ->with(['BRANCH'])
    ;

    // The board's user buttons: audits of that hygienist.
    $idOwner = (int) ($filters['fOwner'] ?? 0);
    if ($idOwner > 0) {
      $query = $query->where('hlthcr_audits.id_hygienist', $idOwner);
    }

    $audits = $query->get()->toArray();

    $items = [];
    foreach ($audits as $audit) {
      $audit['_WORKFLOW_ITEM_TITLE'] = date('d.m.Y', strtotime($audit['date_executed']));
      $audit['_WORKFLOW_ITEM_SUBTITLE'] = $audit['BRANCH']['name'] ?? '';
      $audit['_DETAIL_URL'] = 'hlthcr-audits/audits/' . $audit['id'];
      $audit['_DETAIL_VIEW'] = '@Hubleto:App:Community:Workflow/WorkflowItemDetail.twig';
      $items[] = $audit;
    }

    return $items;
  }
}
