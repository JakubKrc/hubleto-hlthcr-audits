<?php

namespace Hubleto\App\External\WaiBlue\HlthcrAudits\Models\RecordManagers;

use Hubleto\App\External\WaiBlue\HlthcrCore\Models\RecordManagers\Traits\DefaultOrder;
use Hubleto\App\Community\Auth\Models\RecordManagers\User;
use Hubleto\App\Community\Workflow\Models\RecordManagers\Workflow;
use Hubleto\App\Community\Workflow\Models\RecordManagers\WorkflowStep;
use Hubleto\App\External\WaiBlue\HlthcrBranches\Models\RecordManagers\Branch;
use Hubleto\App\External\WaiBlue\HlthcrBranches\Models\RecordManagers\Traits\BelongsToBranch;
use Hubleto\App\External\WaiBlue\HlthcrCore\Models\RecordManagers\Traits\WorkRecord;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Audit extends \Hubleto\Erp\RecordManager
{
  use DefaultOrder;
  use BelongsToBranch;
  use WorkRecord;

  public $table = 'hlthcr_audits';

  const STATE_FILTER = 'fAuditState';

  public function BRANCH(): BelongsTo
  {
    return $this->belongsTo(Branch::class, 'id_branch', 'id');
  }

  public function HYGIENIST(): BelongsTo
  {
    return $this->belongsTo(User::class, 'id_hygienist', 'id');
  }

  public function WORKFLOW(): BelongsTo
  {
    return $this->belongsTo(Workflow::class, 'id_workflow', 'id');
  }

  public function WORKFLOW_STEP(): BelongsTo
  {
    return $this->belongsTo(WorkflowStep::class, 'id_workflow_step', 'id');
  }

  public function prepareReadQuery(mixed $query = null, int $level = 0, array|null $includeRelations = null): mixed
  {
    $query = parent::prepareReadQuery($query, $level, $includeRelations);
    $query = $this->restrictToWorkRecordReaders($query);
    return $this->restrictToVisibleBranches($query);
  }

  // Dropdowns too: Hubleto's lookup query does not go through prepareReadQuery.
  public function prepareLookupQuery(string $search): mixed
  {
    $query = parent::prepareLookupQuery($search);
    $query = $this->restrictToWorkRecordReaders($query);
    $query = $this->restrictToVisibleBranches($query);
    return $this->filterByBranchFromUrl($query);
  }

  public function addUrlFiltersToQuery(mixed $query): mixed
  {
    $query = parent::addUrlFiltersToQuery($query);
    $query = $this->filterByBranchFromUrl($query);

    $filters = $this->router()->urlParamAsArray('filters');
    $selectedStates = (array) ($filters[self::STATE_FILTER] ?? []);
    return Workflow::applyWorkflowStepFilter($this->model, $query, $selectedStates);
  }

  public function prepareLookupData(array $dataRaw): array
  {
    return parent::prepareLookupData($dataRaw);
  }
}
