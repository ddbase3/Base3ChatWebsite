# Base3ChatWebsite

BASE3 website plugin containing chat, agent-flow, SSE, and diagnostic demo pages.

## Installation

Install the `Base3ChatWebsite/` directory at `plugin/Base3ChatWebsite/` in a BASE3 standalone project, or at the corresponding plugin location recognized by an embedded host. The module identity and namespace are declared in `base3.json`.

Do not leave a previous copy of this website plugin enabled beside this directory. Two copies would register overlapping routes and services. Rebuild any deployment class map/cache according to the host's normal process when the plugin directory is replaced.

The PHP entry class is `Base3ChatWebsite\Base3ChatWebsitePlugin`, and its class-map name is `base3chatwebsiteplugin`. All other routable output names, such as `demoserviceagentstream`, are unchanged. Static assets and agent-flow configuration files are addressed using `plugin/Base3ChatWebsite/`.

## Runtime dependencies

The plugin uses BASE3 and the installed `AssistantFoundation`, `ResourceFoundation`, `MissionBay`, `Chatbot`, `DataHawk`, and `EventTransport` modules. The demo pages also use the host's page modules and link-target service. The navigation template references an asset from `Base3Template`. Some agent flows additionally reference tools and providers supplied by the host or other installed plugins. The exact versions of these runtime implementations were not included with this module archive; the manifest declares hard module dependencies without inventing their minimum supported versions.

The examples require the host's existing service bindings, session/authentication setup, configuration, and, depending on the selected flow, API credentials and external services. The rename does not add a second service path, change external-provider settings, or migrate stored conversations or log records.

The logger scopes used by the included agent-flow files are `Base3ChatWebsite` and `Base3ChatWebsiteSuggestions`. Pre-existing records under previous scopes are not migrated.

## Verification

From the module directory, run:

```sh
php test/ModuleIdentityTest.php
```

The test checks the new BASE3 identity, PHP source namespace/class-name consistency, JSON validity, page-service names, and internal file paths. PHP syntax can be checked with `php -l`. Full HTTP, agent, SSE, provider, and database behavior requires the actual configured BASE3 host and installed dependencies.

## Privacy and accessibility

See [PRIVACY.md](PRIVACY.md) for processing surfaces and deployment-specific unknowns. This is a demonstration plugin. Production deployment requires a separate verification of the host's authorization, provider retention, transfers, and the existing UI's accessibility.
