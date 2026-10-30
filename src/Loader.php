<?php

namespace Hubleto\App\External\WaiBlue\HlthcrAudits;

use Hubleto\App\Community\Customers\Models\Customer;
use Hubleto\App\Community\Workflow\Models\Workflow as WorkflowModel;
use Hubleto\App\Community\Workflow\Models\WorkflowStep;
use Hubleto\App\External\WaiBlue\HlthcrBranches\Models\Branch;
use Hubleto\App\External\WaiBlue\HlthcrCore\Services\DemoData;

class Loader extends \Hubleto\App\External\WaiBlue\HlthcrCore\HlthcrApp
{
  // Tags of the audit workflow steps: open or closed, what both legacy apps share.
  const STEP_OPEN = 'hlthcr-audit-open';
  const STEP_CLOSED = 'hlthcr-audit-closed';

  public function init(): void
  {
    parent::init();

    $this->router()->get([
      '/^hlthcr-audits\/?$/' => Controllers\Audits::class,
      '/^hlthcr-audits\/settings\/?$/' => Controllers\Settings::class,
      '/^hlthcr-audits\/boards\/upcoming-audits\/?$/' => Controllers\Boards\UpcomingAudits::class,
    ]);
    $this->router()->crud('hlthcr-audits/audits', Controllers\Audits::class);

    $this->addSearchSwitch('a', 'audits');

    /** @var \Hubleto\App\Community\Calendar\Manager $calendarManager */
    $calendarManager = $this->getService(\Hubleto\App\Community\Calendar\Manager::class);
    $calendarManager->addCalendar($this, 'hlthcr-audits', Calendar::class);

    /** @var \Hubleto\App\Community\Dashboards\Manager $dashboardManager */
    $dashboardManager = $this->getService(\Hubleto\App\Community\Dashboards\Manager::class);
    $dashboardManager->addBoard($this, $this->translate('Upcoming audits'), 'hlthcr-audits/boards/upcoming-audits');

    /** @var \Hubleto\App\Community\Workflow\Manager $workflowManager */
    $workflowManager = $this->getService(\Hubleto\App\Community\Workflow\Manager::class);
    $workflowManager->addWorkflowGroup($this, Models\Audit::WORKFLOW_GROUP, Workflow::class);
  }

  public function installApp(int $round): void
  {
    if ($round == 1) {
      $this->getModel(Models\Audit::class)->upgradeSchema();
      // "php hubleto app install" runs only round 1, so foreign keys are created here too (Hubleto skips them later).
      $this->installForeignKeys();

      $this->installAuditWorkflow();
      $this->putAuditsWithoutStateIntoWorkflow();
    }
  }

  /**
   * Creates the audit workflow, or adds the steps an older install does not have yet.
   * The first step is the default for new audits.
   */
  // Public for the migration Audit_0005: "php hubleto migrate" does not run installApp().
  public function installAuditWorkflow(): void
  {
    /** @var WorkflowModel */
    $mWorkflow = $this->getModel(WorkflowModel::class);
    /** @var WorkflowStep */
    $mWorkflowStep = $this->getModel(WorkflowStep::class);

    $workflow = $mWorkflow->record->where('group', Models\Audit::WORKFLOW_GROUP)->first();
    if ($workflow) {
      $idWorkflow = (int) $workflow->id;
    } else {
      $idWorkflow = (int) $mWorkflow->record->recordCreate([
        'name' => $this->translate('Audits'),
        'show_in_kanban' => 1,
        'order' => 55,
        'group' => Models\Audit::WORKFLOW_GROUP,
      ])['id'];
    }

    $steps = [
      ['name' => $this->translate('In progress'), 'color' => '#e27300', 'tag' => self::STEP_OPEN],
      ['name' => $this->translate('Closed'), 'color' => '#4caf50', 'tag' => self::STEP_CLOSED],
    ];
    $order = 1;
    foreach ($steps as $step) {
      $existingStep = $mWorkflowStep->record->where('id_workflow', $idWorkflow)->where('tag', $step['tag'])->first();
      if ($existingStep) {
        $existingStep->update(['order' => $order]);
      } else {
        $mWorkflowStep->record->recordCreate([
          'id_workflow' => $idWorkflow,
          'name' => $step['name'],
          'color' => $step['color'],
          'tag' => $step['tag'],
          'order' => $order,
        ]);
      }
      $order++;
    }
  }

  // Audits from before the workflow, and demo audits: a past audit has taken place, so it is closed.
  public function putAuditsWithoutStateIntoWorkflow(): void
  {
    $openStep = $this->getModel(WorkflowStep::class)->record->where('tag', self::STEP_OPEN)->first();
    $closedStep = $this->getModel(WorkflowStep::class)->record->where('tag', self::STEP_CLOSED)->first();
    if (!$openStep || !$closedStep) return;

    /** @var Models\Audit */
    $mAudit = $this->getModel(Models\Audit::class);
    $mAudit->record->whereNull('id_workflow_step')->where('date_executed', '<', date('Y-m-d'))->update([
      'id_workflow' => $closedStep->id_workflow,
      'id_workflow_step' => $closedStep->id,
    ]);
    $mAudit->record->whereNull('id_workflow_step')->update([
      'id_workflow' => $openStep->id_workflow,
      'id_workflow_step' => $openStep->id,
    ]);
  }

  public function search(array $expressions): array
  {
    $auditsQuery = $this->getModel(Models\Audit::class)->record->prepareReadQuery()->with(['BRANCH']);
    foreach ($expressions as $expression) {
      $auditsQuery = $auditsQuery->where(function ($matchingAudit) use ($expression) {
        $matchingAudit->orWhere('hlthcr_audits.note', 'like', '%' . $expression . '%');
        $matchingAudit->orWhereIn('hlthcr_audits.id_branch', function ($matchingBranches) use ($expression) {
          $matchingBranches->select('id')->from('hlthcr_branches')->where('name', 'like', '%' . $expression . '%');
        });
      });
    }

    $results = [];
    foreach ($auditsQuery->orderBy('date_executed', 'desc')->limit(20)->get()->toArray() as $audit) {
      $results[] = [
        'id' => $audit['id'],
        'label' => $this->translate('Audit') . ' ' . date('d.m.Y', strtotime($audit['date_executed'])),
        'url' => 'hlthcr-audits/audits/' . $audit['id'],
        'description' => $audit['BRANCH']['name'] ?? '',
      ];
    }
    return $results;
  }

  public function renderSecondSidebar(): string
  {
    return '
      ' . $this->secondSidebarTitle() . '
      <div class="app-sidebar-buttons">
        ' . $this->secondSidebarButton('hlthcr-audits/audits', 'fas fa-clipboard-check', $this->translate('Audits')) . '
        ' . $this->secondSidebarButton('calendar', 'fas fa-calendar-days', $this->translate('Calendar')) . '
        ' . $this->secondSidebarButton('hlthcr-audits/settings', 'fas fa-cog', $this->translate('Settings')) . '
      </div>
    ';
  }

  public function renderAlerts(): string
  {
    return '';
  }

  // No number in the menu: neither legacy app shows one.
  public function getSidebarBadgeNumber(): int
  {
    return 0;
  }

  /**
   * For the first branch of each demo client: an audit from last year and a planned one in two weeks (FAKE data).
   * Records are inserted directly: the model rules use the logged-in user, and there is none on the command line.
   */
  public function generateHlthcrDemoData(): void
  {
    /** @var Models\Audit */
    $mAudit = $this->getModel(Models\Audit::class);
    $demoCompanyIds = $this->getService(DemoData::class)->getDemoCompanyIds();
    $demoCustomerIds = $this->getModel(Customer::class)->record->whereIn('company_id', $demoCompanyIds)->pluck('id');

    foreach ($demoCustomerIds as $idDemoCustomer) {
      $branch = $this->getModel(Branch::class)->record->where('id_customer', $idDemoCustomer)->orderBy('id')->first();
      if (!$branch) continue;

      $branchHasAudits = $mAudit->record->where('id_branch', $branch->id)->exists();
      if ($branchHasAudits) continue;

      $mAudit->record->create([
        'id_branch' => $branch->id,
        'date_executed' => date('Y-m-d', strtotime('-1 year')),
        'date_valid_to' => date('Y-m-d', strtotime('+1 year')),
        'note' => 'Pravidelný audit pracovísk',
        'id_hygienist' => $branch->id_owner,
      ]);
      $mAudit->record->create([
        'id_branch' => $branch->id,
        'date_executed' => date('Y-m-d', strtotime('+14 days')),
        'date_valid_to' => date('Y-m-d', strtotime('+14 days +24 months')),
        'note' => 'Obhliadka pracovísk po rozšírení výroby',
        'id_hygienist' => $branch->id_owner,
      ]);
    }

    $this->putAuditsWithoutStateIntoWorkflow();
  }
}
