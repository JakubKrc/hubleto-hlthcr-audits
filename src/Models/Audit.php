<?php

namespace Hubleto\App\External\WaiBlue\HlthcrAudits\Models;

use Hubleto\App\External\WaiBlue\HlthcrCore\Models\Traits\RefusesUnknownUser;
use Hubleto\App\External\WaiBlue\HlthcrCore\Services\Roles;
use Hubleto\App\External\WaiBlue\HlthcrCore\Controllers\Api\UserLookup;
use Hubleto\App\Community\Auth\Models\User;
use Hubleto\App\External\WaiBlue\HlthcrBranches\Models\Branch;
use Hubleto\App\External\WaiBlue\HlthcrCore\Services\MonthDates;
use Hubleto\Framework\Db\Column\Boolean;
use Hubleto\Framework\Db\Column\Date;
use Hubleto\Framework\Exceptions\RecordSaveException;
use Hubleto\Framework\Db\Column\Lookup;
use Hubleto\Framework\Db\Column\Text;
use Hubleto\App\Community\Customers\Models\Customer;
use Hubleto\App\External\WaiBlue\HlthcrClients\Services\CustomerColumns;
use Hubleto\App\External\WaiBlue\HlthcrCore\Models\Traits\EmptyDatesAsNull;

/**
 * Audit (visit) of a branch by its hygienist. Valid until the next audit is due:
 * the audit date plus the client's audit interval, unless typed. Closing an audit was in one legacy app only and is left out.
 */
class Audit extends \Hubleto\App\External\WaiBlue\HlthcrCore\Models\HlthcrModel
{
  use RefusesUnknownUser;
  use EmptyDatesAsNull;

  public string $table = 'hlthcr_audits';
  public string $recordManagerClass = RecordManagers\Audit::class;
  // Date and branch, as in both legacy apps; the newest audits first.
  public ?string $lookupSqlValue = 'concat(date_format({%TABLE%}.date_executed, "%d.%m.%Y"),
    ifnull(concat(" - ", (select name from hlthcr_branches where hlthcr_branches.id = {%TABLE%}.id_branch)), ""))';
  public string $lookupOrderBy = '{%TABLE%}.date_executed desc';
  public ?string $lookupUrlDetail = 'hlthcr-audits/audits/{%ID%}';

  public array $relations = [
    'BRANCH' => [ self::BELONGS_TO, Branch::class, 'id_branch', 'id' ],
    'HYGIENIST' => [ self::BELONGS_TO, User::class, 'id_hygienist', 'id' ],
  ];

  public function describeColumns(): array
  {
    return array_merge(parent::describeColumns(), [
      'id_branch' => (new Lookup($this, $this->translate('Branch'), Branch::class, 'RESTRICT'))
        ->setRequired()
        ->setDefaultVisible()
      ,
      'date_executed' => (new Date($this, $this->translate('Audit date')))->setRequired()->setDefaultVisible(),
      'date_valid_to' => (new Date($this, $this->translate('Valid to')))
        ->setDefaultVisible()
        ->setHint($this->translate('Left empty, it is the audit date plus the audit interval of the client.'))
      ,
      // Set when the user typed the validity, so it is not recomputed from the audit date.
      'valid_to_typed' => (new Boolean($this, $this->translate('Validity typed'))),
      'note' => (new Text($this, $this->translate('Note / purpose of the visit')))->setDefaultVisible(),
      'id_hygienist' => (new Lookup($this, $this->translate('Hygienist'), User::class))
        ->setEndpoint(UserLookup::endpointFor(Roles::HYGIENIST, 'id_hygienist'))
        ->setDefaultVisible()
      ,
    ]);
  }

  public function describeTable(): \Hubleto\Framework\Description\Table
  {
    $description = parent::describeTable();
    $description->ui['addButtonText'] = $this->translate('Add audit');
    $description->ui['orderBy'] = ['field' => 'date_executed', 'direction' => 'desc'];
    $description->show(['header', 'fulltextSearch', 'columnSearch', 'moreActionsButton']);
    $description->hide(['footer']);


    return $description;
  }

  public function describeForm(): \Hubleto\Framework\Description\Form
  {
    $description = parent::describeForm();
    $description->defaultValues['date_executed'] = date('Y-m-d');
    $description->defaultValues['id_hygienist'] = $this->authProvider()->getUserId();
    return $description;
  }

  public function getRelationsIncludedInLoadTableData(): array|null
  {
    return ['BRANCH', 'HYGIENIST'];
  }

  public function getMaxReadLevelForLoadTableData(): int
  {
    return 1;
  }

  public function onBeforeCreate(array $record): array
  {
    $record = parent::onBeforeCreate($record);
    $record = $this->emptyDatesAsNull($record);
    if (empty($record['id_hygienist'])) {
      $record['id_hygienist'] = $this->authProvider()->getUserId();
    }
    $this->refuseUnknownUser($record, 'id_hygienist');
    $record['valid_to_typed'] = !empty($record['date_valid_to']);
    return $this->fillValidTo($record);
  }

  // The branch stays as created.
  public function onBeforeUpdate(array $record): array
  {
    $record = parent::onBeforeUpdate($record);
    $record = $this->emptyDatesAsNull($record);
    $this->refuseUnknownUser($record, 'id_hygienist');

    $originalAudit = $this->record->where('id', (int) $record['id'])->first();
    if ($originalAudit) {
      $record['id_branch'] = $originalAudit->id_branch;
      $record['valid_to_typed'] = $this->isValidToTyped($record, $originalAudit);
    }

    return $this->fillValidTo($record);
  }

  // The validity is typed when this save changes it to a value; emptying it lets it be computed again.
  private function isValidToTyped(array $record, mixed $originalAudit): bool
  {
    if (!array_key_exists('date_valid_to', $record)) return (bool) $originalAudit->valid_to_typed;

    $submittedDate = (string) ($record['date_valid_to'] ?? '');
    $originalDate = (string) ($originalAudit->date_valid_to ?? '');
    if ($submittedDate == $originalDate) return (bool) $originalAudit->valid_to_typed;
    return $submittedDate != '';
  }

  // Unless typed, "valid to" is the audit date plus the audit interval of the branch's client, also after the date changes.
  private function fillValidTo(array $record): array
  {
    $hasAuditDate = !empty($record['date_executed']);
    $isTyped = (bool) ($record['valid_to_typed'] ?? false);

    if ($hasAuditDate && !$isTyped) {
      $intervalMonths = $this->getAuditIntervalMonths((int) ($record['id_branch'] ?? 0));
      $record['date_valid_to'] = MonthDates::addMonths((string) $record['date_executed'], $intervalMonths);
    }

    $isValidBeforeAudit = $hasAuditDate && !empty($record['date_valid_to']) && $record['date_valid_to'] < $record['date_executed'];
    if ($isValidBeforeAudit) {
      throw new RecordSaveException($this->translate('The audit cannot be valid to a date before the audit.'));
    }
    return $record;
  }

  public function getAuditIntervalMonths(int $idBranch): int
  {
    /** @var CustomerColumns */
    $customerColumns = $this->getService(CustomerColumns::class);
    $defaultMonths = $customerColumns->getDefaultAuditIntervalMonths();

    $branch = $this->getModel(Branch::class)->record->where('id', $idBranch)->first();
    if (!$branch) return $defaultMonths;

    $customer = $this->getModel(Customer::class)->record->where('id', $branch->id_customer)->first();
    $clientMonths = $customer ? (int) $customer->hlthcr_audit_interval_months : 0;
    if ($clientMonths > 0) return $clientMonths;
    return $defaultMonths;
  }
}
