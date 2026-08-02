# Native extension pull-request summary

This branch converts the validated WorkCore package split into six MagicAI-native extensions while incorporating the final missing Finance completion source.

The parent `WorkCore` vertical suite owns the canonical runtime, migrations, tenancy and host adapters. Five parent-dependent add-ons activate exact domain module sets without duplicating runtime code or claiming migration ownership.

Permanent CI covers deterministic packaging, seven Laravel 10 installation profiles, MagicAI 11 compatibility, disabled boot, parent-absent add-ons and full migrations.
