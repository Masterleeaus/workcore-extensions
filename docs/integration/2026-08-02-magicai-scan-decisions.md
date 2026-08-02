# MagicAI 11 scan decisions applied to WorkCore extensions

## Source evidence

Reviewed the six focused MagicAI 11 scans in `Masterleeaus/Documents/workcore/magicai-integration`:

1. Complete system inventory
2. Bootstrap and runtime architecture
3. Extension and marketplace runtime
4. Menu, navigation and dashboard hooks
5. Authentication and user identity
6. Teams, companies and tenancy

Also reviewed the separate Operations Hooks and WorkCore Menu Integration architecture reference.

## Decisions

- MagicAI 11 remains the host platform; WorkCore remains the operational authority.
- The actual host target is Laravel 10, not the Laravel 12 standalone shell used during extraction.
- MagicAI Marketplace sees one umbrella `workcore` provider.
- Shared Foundation plus the five domain groups remain internal WorkCore packages.
- MagicAI User is the authenticated account, not the operational tenant.
- MagicAI Team is a SaaS seat/credit group, not WorkCore company membership.
- MagicAI Company is Brand Voice context, not the operational company.
- WorkCore routes, migrations, views, commands, events and menus remain provider-owned.
- WorkCore installation must not use the legacy host-file-copying installer.
- WorkCore writes must not inherit the dashboard-wide CSRF exemption.
- WorkCore queues must retain their names and must never be rewritten to `default` during host boot.
- Extension disable or uninstall must preserve operational data and audit history.

## Current release correction

The final consolidated WorkCore archive adds 32 files and changes two Finance files compared with the earlier archive. Release `0.1.1` includes the missing Titan Money commercial application layer and payment orchestration capability.
