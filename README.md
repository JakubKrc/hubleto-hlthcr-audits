# Hlthcr Audits

Audits of the branches with validity, documents, calendar and the home panel of upcoming audits.

Hubleto app of the Hlthcr set (occupational health service, Slovakia). Namespace: `Hubleto\App\External\WaiBlue\HlthcrAudits`.
Needs: `wai-blue/hubleto-hlthcr-core`, `wai-blue/hubleto-hlthcr-branches`, `wai-blue/hubleto-hlthcr-clients`.

## Install into a Hubleto project

```bash
composer config repositories.wai-blue/hubleto-hlthcr-audits vcs <repository-url>
composer require wai-blue/hubleto-hlthcr-audits:dev-main
php hubleto app install "Hubleto\App\External\WaiBlue\HlthcrAudits"
npm run build
```
