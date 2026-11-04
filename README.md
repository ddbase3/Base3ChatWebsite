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
