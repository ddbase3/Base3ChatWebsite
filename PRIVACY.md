# Privacy and data-flow inventory: Base3ChatWebsite

**Review date:** 2026-11-03. **Source snapshot:** the supplied project archive, including the checked-out source at Git HEAD `e08f64a` and its additional working-tree files, transformed for the `Base3ChatWebsite` module identity. **Module manifest version:** `1.0.0` (new module identity). This document describes observable code, not a legal privacy notice or a claim of GDPR compliance.

## 1. Scope and limitations

The plugin contains synchronous chat endpoints, an agent example, diagnostic output, page configurations, and a photo-mosaic canvas tool. Supporting BASE3, MissionBay, Chatbot, DataHawk and provider implementations are not contained here. Their actual storage, recipient lists, security and retention must be assessed from the deployed sources and settings.

## 2. Roles and responsibility boundaries

The deploying operator determines purposes and legal bases, controls the host configuration, and must identify the controller and any processors. BASE3 and the host own framework services. External AI, messaging, retrieval and embedded-site providers are separate recipients when enabled. This source does not establish which legal entity performs each role in a deployment.

## 3. Purposes and data categories

The demo features process entered chat text and optional feedback, generated AI responses, model/tool output, generated message IDs and timestamps, and potentially session-linked conversation context. Navigation accesses authentication state and the current user's role via the configured usermanager. The DataHawk example reads repository-name and full-name fields of `git_repository` through `IQueryService`. Agent tools may process retrieved content or account identifiers depending on host configuration.

## 4. Collection and entry points

`src/Chat/DemoServiceSync.php` and `src/Chat/DemoServiceAgent.php` process browser requests or delegate to agent flows. `src/Content/DataHawkTest.php` reads query results. `src/MissionBay/PhotoMosaicDemoAgentTool.php` opens an external iframe when invoked. `local/Page/*.json` provides example page definitions.

## 5. Storage and data locations

The plugin ships static JSON, text and template files. It does not itself define a domain database schema or migrations. `src/Base3ChatWebsitePlugin.php` configures `ISession`, a MySQL-based `IDatabase` default, and a database-backed `ISettingsStore`. Runtime storage performed through these services belongs to their actual installed backends. The retained agent flow JSON references session memory. The concrete persistence of dependent session/memory services cannot be established from this archive. Host cache, queue, backup and audit stores are not described by available implementation sources.

## 6. Stored fields and sensitivity

Potential data includes raw prompts, generated text, tool responses, session information, credentials in host configuration, and vector/retrieval results. The exact content written by dependent memory, log, and database implementations is unknown. The `local/Chatbot` prompt and flow files are shipped application configuration, not a verified record of runtime conversations.

## 7. Ownership and authorization

The plugin registers the existing authentication and access-control middleware and uses the configured `IAccesscontrol` service for navigation. The provided output classes do not themselves implement a verified per-operation `IAuthorizationService` check. Access control for exposed endpoints and records must be verified at the actual host and authoritative resource owner before production deployment. Native permission boundaries must not be replaced with a second plugin permission model.

## 8. Recipients and external connections

`DemoServiceSync` calls the configured `openai` endpoint's `/chat/completions` API and sends prompts. The retained agent-flow configuration refers to an OpenAI chat model, session memory and a Telegram tool, subject to actual availability and invocation. `PhotoMosaicDemoAgentTool.php` can embed `https://mosaic-creator.de/mosaicgallery.php?embed`; when displayed, the browser connects to that website. Chat authenticates human login credentials against the configured XRM microservice via the shared Base3Template login form and KeyHarbor-protected HTTPS server-to-server calls, then reads native user identity, groups, roles and permission catalog metadata through an XRM usermanager microservice. Chat maintains its own session identifier and current verified XRM login. A Data microservice can be used for DataHawk. Actual operators, jurisdictions and subprocessors are deployment-dependent.

## 9. Credentials and configuration

The demonstration endpoints read `openai` configuration values for the API URL and key. The retained agent-flow resource definitions refer to `openai` and `telegram` configuration entries. Secrets are not defined in `base3.json`; their storage and protection depend on the active BASE3 configuration and provider plugins. No claim is made about encrypted storage, key rotation or account policy.

## 10. Retention and cleanup

The archive does not define retention limits, scheduled cleanup, or deletion for chat conversations, remote model requests, vector data, sessions or logs. Those behaviors must be verified and configured in the authoritative host, memory, logging and provider services. A production retention policy cannot be inferred from this demo code.

## 11. Deletion, correction, export and restriction

The plugin contains no standalone data-subject request API or own data-erasure workflow. The host and respective storage/provider owners must support applicable deletion, correction, access, export or restriction procedures. Where data has been sent externally, propagation and provider-side deletion require separate evidence.

## 12. Backups and restoration

Backup storage, backup expiration, restoration and any re-deletion on restore are not visible in this archive. The operator must document these for its configured host and backend services.

## 13. Logs and observability

The removed streaming flow configurations referenced the logger scopes `Base3ChatWebsite` and `Base3ChatWebsiteSuggestions`. The retained flow does not define a logger resource. Actual host and provider log destinations and retention are not included. The removed streaming demo sample files did not write runtime logs. Web server, PHP and external-provider logs depend on deployment.

## 14. International transfers

No verified guarantee about regional hosting, international transfers, provider training use, or subprocessors can be made from the supplied source. Review the configured endpoints, provider contracts, hosting region, data transfer mechanisms and downstream tools before enabling them with personal data.

## 15. Security controls and limitations

The plugin uses BASE3 session and access-control services supplied by the host. It requires the deployed host to manage TLS, credential protection, request access, rate limits, audit controls, secret rotation and provider restrictions. The demo `PhotoMosaicDemoAgentTool` emits an iframe in an HTML canvas block with `sanitize=false`; its safety and content security policy must be validated by the owning host/canvas implementation. Demonstration endpoints and direct DataHawk output require specific authorization review before public exposure.

## 16. Data-subject rights and responsible owners

Contact channels, controller identity and execution of data-subject requests must be provided by the deployment operator. The authoritative session, chat-memory, logging, external-provider, data and backup services own their respective data operations; this plugin should not replicate them.

## 17. Default and optional processing

Static page examples include synchronous chatbot integrations. Whether those pages are routable and which provider calls occur depends on host configuration, credentials, user actions and selected flows. Some page definitions reference `jquerycookiebarpagemodule`, but this archive does not establish cookie categories, consent timing or exemption rules. The optional external iframe is triggered by the corresponding agent tool.

## 18. Known unknowns and deployment checklist

Before production use, verify: the installed dependencies and their versions; authentication and authoritative authorization of each endpoint; data-flow recipients; model usage/training terms; provider retention; configured memory and logging stores and erasure behavior; backup scope; user notice and valid legal basis; terminal storage and cookie consent where applicable; accessibility including keyboard and screen reader behavior; and any applicable legal/contractual requirements. No live deployment, external provider or accessibility audit was available for this change.

## 19. Relevant code and tests

Main wiring: `src/Base3ChatWebsitePlugin.php`. Request handlers: `src/Chat/` and `src/Content/`. Optional agent/canvas tool: `src/MissionBay/PhotoMosaicDemoAgentTool.php`. Flow configurations: `local/Chatbot/`. Page definitions: `local/Page/`. Structural test: `test/ModuleIdentityTest.php`. The relevant BASE3 contract and deployment documentation remain in the separate framework and provider repositories.

## 20. Change history

- 2026-11-02: Created source-backed inventory for the renamed module. No runtime privacy behavior, retention policy or provider bindings were redesigned as part of the module-identity change.
- 2026-11-03: Removed obsolete EventTransport/SSE and unsupported legacy streaming demos, their pages and static flow data. The remaining synchronous chat and agent flows are unchanged; historical external provider or host-side data is not deleted by this source change.

## XRM identity integration (2026-11-05)

The Login form processes submitted username/password and a session-bound CSRF
value. Credentials are sent from Chat's server to XRM over TLS using the existing
KeyHarbor-signed microservice transport. Chat does not store the password in its
session or database. The session contains the XRM login identifier; XRM remains
the authority for account data and membership metadata, which Chat reads on
demand. The technical XRM token is read from deployment configuration and must
be kept outside version control, access logs and public responses.

Session lifetime and deletion follow the configured BASE3 session backend.
XRM account revocation and active Chat session invalidation require deployment
policy and operational verification; no live revocation guarantee is asserted.
The host operator must assess network logs, recipients, retention, backups,
accessibility and data-subject rights for the actual installations.

The reusable `XrmIdentityClient` plugin owns the XRM login form, API calls, and
remote usermanager adapter. `Base3ChatWebsite` only selects its services and
retains the normal local BASE3 session; see `XrmIdentityClient/PRIVACY.md`.

## Shared local audience administration (2026-11-05)

Feature and group audience selections on Chat are stored in the existing
site-local settings database in the `base3template` settings group. The shared
Base3Template service evaluates the trusted XRM user identity and roles through
the framework's criterion evaluator. Native permission metadata continues to
come from XRM, and the existing framework `Base3SystemAuthorizationService`
decides native `system/admin` for every admin action. The optional KeyHarbor
grant for user search and role catalog may transfer minimal account identity
and native role metadata from XRM to Chat, with encrypted transport and
server-side credentials. There are no copied passwords, shadow roles, or
additional credential stores. Site operators must verify local settings
retention, database backups, access and reverse-proxy logs, and deletion paths.
