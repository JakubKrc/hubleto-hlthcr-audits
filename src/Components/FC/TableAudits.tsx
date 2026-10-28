import Table from '@hubleto/react-ui/components/fc/Table'
import { type TableMeta, type TableProps } from '@hubleto/react-ui/components/fc/TableInterfaces'
import FormAudit from './FormAudit'

interface TableAuditsProps extends TableProps {
  idBranch?: number,
}

const componentName = 'TableAudits'
const parentApp = 'Hubleto/App/External/WaiBlue/HlthcrAudits'

// The audits list and the "Audity" tab of the branch form.
const TableAudits = (props: TableAuditsProps) => {
  const idBranch = props.idBranch ?? 0;
  return <Table
    componentName={componentName}
    parentApp={parentApp}
    model={parentApp + '/Models/Audit'}
    baseUrlSlug='hlthcr-audits/audits'
    endpointParams={{idBranch: idBranch}}
    formDefaultValues={idBranch > 0 ? {id_branch: idBranch} : {}}
    formModalProps={{type: 'right wide'}}
    renderForm={(table: TableMeta) => <FormAudit {...table.getDefaultFormProps()} />}
    {...props}
  />;
}

export default TableAudits;
