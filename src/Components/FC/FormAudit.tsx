import React from 'react';
import Translator from '@hubleto/react-ui/core/Translator';
import Form, { FormMetaContext } from '@hubleto/react-ui/components/fc/Form';
import { type FormProps } from '@hubleto/react-ui/components/fc/FormInterfaces';
import Input from '@hubleto/react-ui/components/fc/FormComponents/Input';
import { dateToEUFormat } from '@hubleto/react-ui/components/fc/Inputs/DateTime';
import { useRecordField } from '@hubleto/react-ui/components/fc/FormRecordStore';

const componentName = 'FormAudit';
const parentApp = 'Hubleto/App/External/WaiBlue/HlthcrAudits';
const T = new Translator(parentApp + '/Loader', 'Components/' + componentName);

// The branch stays as created (the server keeps it), so it is read-only after saving
// and when the audit is added in the branch's tab (both legacy apps).
const TabDefault = () => {
  const form = React.useContext(FormMetaContext);
  const isSaved = form.id > 0;
  const isBranchGiven = Number(form.description?.defaultValues?.id_branch ?? 0) > 0;
  return <div className='grid grid-cols-1 md:grid-cols-2 gap-4'>
    <div>
      <Input field='id_branch' readonly={isSaved || isBranchGiven} />
      <Input field='date_executed' />
      <Input field='date_valid_to' />
    </div>
    <div>
      <Input field='id_hygienist' />
      <Input field='note' />
    </div>
  </div>;
}

// The title shows the date as dd.mm.yyyy (Hubleto's title would show the stored yyyy-mm-dd).
const AuditTitle = () => {
  const date = useRecordField<string>('date_executed', '');
  return <div>
    <h2>{date ? dateToEUFormat(date) : ''}</h2>
    <small>{T.translate('Audit')}</small>
  </div>;
}

const FormAudit = (props: FormProps) => <Form
  componentName={componentName}
  parentApp={parentApp}
  model={parentApp + '/Models/Audit'}
  urlSlug='hlthcr-audits/audits'
  renderTitle={() => <AuditTitle />}
  tabs={{default: {title: <b>{T.translate('Audit')}</b>, content: () => <TabDefault />}}}
  {...props}
/>;

export default FormAudit;
