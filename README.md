# Base3ChatWebsite

BASE3 website plugin containing synchronous chat, agent-flow, and diagnostic demo pages.

## Installation

Install the `Base3ChatWebsite/` directory at `plugin/Base3ChatWebsite/` in a BASE3 standalone project, or at the corresponding plugin location recognized by an embedded host. The module identity and namespace are declared in `base3.json`.

Do not leave a previous copy of this website plugin enabled beside this directory. Two copies would register overlapping routes and services. Rebuild any deployment class map/cache according to the host's normal process when the plugin directory is replaced.

The PHP entry class is `Base3ChatWebsite\Base3ChatWebsitePlugin`, and its class-map name is `base3chatwebsiteplugin`. The legacy streaming demo endpoints, including their page definitions and SSE client example, have been removed. The synchronous chat and agent demo outputs remain available. Static assets and agent-flow configuration files are addressed using `plugin/Base3ChatWebsite/`.

## Runtime dependencies

The plugin uses BASE3 and the installed `AssistantFoundation`, `ResourceFoundation`, `MissionBay`, `Chatbot` and `DataHawk` modules. The demo pages also use the host's page modules and link-target service. The navigation template references an asset from `Base3Template`. Some agent flows additionally reference tools and providers supplied by the host or other installed plugins. The exact versions of these runtime implementations were not included with this module archive; the manifest declares hard module dependencies without inventing their minimum supported versions.

The examples require the host's existing service bindings, session/authentication setup, configuration, and, depending on the selected flow, API credentials and external services. Removing legacy streaming demo endpoints does not replace them with additional services or migrate stored conversations or log records.

The logger scope used by the retained agent-flow file is `Base3ChatWebsite`. Pre-existing logging data is not modified.

## Removing obsolete streaming demos from an existing deployment

`unzip -o` updates the remaining files but cannot delete obsolete ones. After extracting the patch at the BASE3 project root, run the supplied, idempotent removal script:

```sh
sh plugin/Base3ChatWebsite/install/remove-obsolete-streaming-demos.sh
```

The script deletes only obsolete plugin-owned streaming endpoints, related pages/configuration, sample files and tests. It does not change other plugins, chat memory, database records or provider data.

## Verification

From the BASE3 framework root, run:

```sh
make test-issues
```

The module tests check the module identity, retained outputs, valid page/flow JSON and absence of the removed streaming routes. PHP syntax can be checked with `php -l`. Full HTTP, agent, provider, and database behavior requires the actual configured BASE3 host and installed dependencies.

## Privacy and accessibility

See [PRIVACY.md](PRIVACY.md) for processing surfaces and deployment-specific unknowns. This is a demonstration plugin. Production deployment requires a separate verification of the host's authorization, provider retention, transfers, and the existing UI's accessibility.

## Chat login and XRM user context

Chat selects the reusable `Base3Template` identity adapters on its own `login.php` against XRM through the existing
`IAuthentication::login(?array $credentials)` contract and the installed
`KeyHarborClient` transport. It retains the authenticated XRM login in Chat's normal
`SessionAuth` session. There is no redirect, SSO login, local password database
or dependency on the retired external `Account` microservice.

The usermanager is selected from `Base3Template`, via the existing `UsermanagerProxy`, resolving
native XRM identity, groups, roles and global permission *metadata* on XRM.
Access decisions remain the responsibility of `IAuthorizationService` at the
owning resource. Login and basic user context require only `xrmauthenticationmicroservice.login` and
`xrmusermanagermicroservice.getUserById`. Audience administration also uses
separately granted `searchUsers`/`getAllRoles` reads as described below.

Configure the existing BASE3 `IConfiguration` (never put real secrets in Git):

```ini
[xrm]
url = "https://xrm.base3.de"
token = "b3k_..."
```

After applying the site patch, run `php plugin/Base3ChatWebsite/scripts/rebuild_classmap.php` to register the new page module.

The KeyHarbor token belongs to the trusted Chat server, not the browser. No
additional local KeyHarbor server plugin, session mirror, or registration is
introduced. XRM must have the matching usermanager microservice installed and
the exact KeyHarbor operation grant enabled before accepting Chat logins.

## Shared identity implementation

The XRM login form and its reusable remote authentication and usermanager
adapters are owned by the existing `Base3Template` plugin. The Chat project
selects these services and builds two `KeyHarborClient` connectors. No
Chat-specific implementation or new plugin duplicates their logic. The
`login.php` page uses the existing `loginblock` module, and the local
`SessionAuth` maintains the verified XRM login. Only the existing `[xrm]`
`url` and `token` configuration values are read.

## Host-local authorization using shared template audiences (2026-11-05)

Chat selects `Base3Template\Authorization\FeatureAuthorizationService` in
its project DI and delegates non-feature permissions to the existing
`Base3SystemAuthorizationService`, using effective roles and permission metadata
read from XRM through `XrmUsermanager`. The source of identity and native
grants remains XRM; Chat does not introduce its own RBAC database, alternative
credential checker, or feature policy language. The default
`Base3Template\Authorization\AudienceCatalog` stores Chat feature/group
overrides under the local database's `base3template` settings group; XRM's
settings remain independent.

The shared `development` group matches exact trusted XRM login `dd`; it makes
the feature settings tab visible even when no features have been configured.
Editing is still limited to identities whose native XRM roles supply
`system/admin`. Neither a technical KeyHarbor credential nor `development`
alone grants that permission. To enable the current Groups/Features editor's
native role suggestions and user search, grant the existing Chat KeyHarbor
credential two **additional** XRM usermanager operations:
`xrmusermanagermicroservice.getAllRoles` and
`xrmusermanagermicroservice.searchUsers`. Read-only feature evaluation and
login continue to use the previously granted `getUserById`/`login` operations.
