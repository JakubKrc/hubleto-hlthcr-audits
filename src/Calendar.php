<?php

namespace Hubleto\App\External\WaiBlue\HlthcrAudits;

use Hubleto\App\Community\Workflow\Models\WorkflowStep;
use Hubleto\App\External\WaiBlue\HlthcrCore\Services\CalendarAuthor;

/**
 * Audits in the Hubleto calendar (legacy colour red): the audit date and the end of its validity.
 * Derived from the audits, so there is no event table to keep in sync.
 */
class Calendar extends \Hubleto\App\Community\Calendar\Calendar
{
  const COLOR = '#f5563d';

  public string $translationContext = 'hubleto-app-external-waiblue-hlthcraudits-loader';
  public string $translationContextInner = 'Calendar';

  public function getCalendarConfig(): array
  {
    return [
      'position' => 6,
      'color' => self::COLOR,
      'title' => $this->translate('Audits'),
      'icon' => 'fas fa-clipboard-check',
    ];
  }

  public function loadEvent(int $id): array
  {
    $audit = $this->getModel(Models\Audit::class)->record->prepareReadQuery()->where('hlthcr_audits.id', $id)->first();
    if (!$audit) return [];
    return $audit->toArray();
  }

  public function loadEvents(string $dateStart, string $dateEnd, array $filter = []): array
  {
    // Both events of an audit are done when the audit is closed.
    $closedStepIds = $this->getModel(WorkflowStep::class)->record
      ->where('tag', Loader::STEP_CLOSED)
      ->pluck('id')
      ->toArray()
    ;

    $events = [];
    foreach ($this->loadAuditsInPeriod('date_executed', $dateStart, $dateEnd, $filter, $closedStepIds) as $audit) {
      $title = $this->translate('Audit - {{ branch }}', ['branch' => $audit['BRANCH']['name'] ?? '']);
      $events[] = $this->convertToCalendarEvent($audit, $audit['date_executed'], $title, $closedStepIds);
    }
    foreach ($this->loadAuditsInPeriod('date_valid_to', $dateStart, $dateEnd, $filter, $closedStepIds) as $audit) {
      $title = $this->translate('End of audit validity - {{ branch }}', ['branch' => $audit['BRANCH']['name'] ?? '']);
      $events[] = $this->convertToCalendarEvent($audit, $audit['date_valid_to'], $title, $closedStepIds);
    }
    return $events;
  }

  /**
   * Legacy calendar: no audits of inactive branches; administrators and managers see all audits,
   * others only audits they created.
   */
  private function loadAuditsInPeriod(string $dateColumn, string $dateStart, string $dateEnd, array $filter, array $closedStepIds): array
  {
    $query = $this->getModel(Models\Audit::class)->record->prepareReadQuery()
      ->with(['BRANCH'])
      ->where('hlthcr_audits.' . $dateColumn, '>=', $dateStart)
      ->where('hlthcr_audits.' . $dateColumn, '<=', $dateEnd)
      ->whereIn('hlthcr_audits.id_branch', function ($activeBranches) {
        $activeBranches->select('id')->from('hlthcr_branches')->where('is_active', 1);
      })
    ;

    $idAuthor = $this->getService(CalendarAuthor::class)->getAuthorIdToShow($filter);
    if ($idAuthor > 0) {
      $query = $query->where('hlthcr_audits.id_hygienist', $idAuthor);
    }

    // Hubleto's calendar filter "not done" is 1, "done" is 2.
    $completedFilter = (int) ($filter['fCompleted'] ?? 0);
    if ($completedFilter == 1) {
      $query = $query->where(function ($notClosedQuery) use ($closedStepIds) {
        $notClosedQuery->whereNotIn('hlthcr_audits.id_workflow_step', $closedStepIds)
          ->orWhereNull('hlthcr_audits.id_workflow_step');
      });
    }
    if ($completedFilter == 2) $query = $query->whereIn('hlthcr_audits.id_workflow_step', $closedStepIds);

    return $query->get()->toArray();
  }

  private function convertToCalendarEvent(array $audit, string $date, string $title, array $closedStepIds): array
  {
    return [
      'id' => (int) $audit['id'],
      'start' => $date,
      'allDay' => true,
      'title' => $title,
      'color' => self::COLOR,
      'source' => 'hlthcr-audits',
      'id_owner' => $audit['id_hygienist'],
      'completed' => in_array((int) $audit['id_workflow_step'], $closedStepIds) ? 1 : 0,
      'url' => 'hlthcr-audits/audits/' . $audit['id'],
    ];
  }
}
