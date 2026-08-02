# MiniUp publication and transfer record

MiniUp was used as the integrity-checked file transport for publishing the extension workspace and supporting extraction assets into GitHub.

## Extension workspace

- Dataset asset ID: `00782a71-8258-4321-b1a0-c78746ec9e97`
- Dataset rows: `55`
- Hosted Parquet bytes: `3,372,655`
- Source archive bytes: `2,466,665`
- Source archive SHA-256: `ced25f6a626b45db3f54b276e78674c3b10158b99ece83ea0a579b30e19a463b`
- Dataset URL: <https://titan-builder-ob009-artifact.miniup.app/api/data-file/titan-builder-ob009-artifact/workcore-workspace-transfer.parquet>

The ordered dataset rows carry Base64 chunks of the deterministic `workcore-extension-workspace.zip`. The one-shot GitHub importer reconstructed the archive, verified its size and SHA-256, expanded the packages and then deleted itself.

## Reproducibility and integration support

- Dataset asset ID: `478b9636-27ad-4ca5-a649-417870486441`
- Dataset rows: `48`
- Hosted Parquet bytes: `3,636,874`
- Source archive bytes: `2,120,014`
- Source archive SHA-256: `921e62dbb1f7a4b940b2ba462e551bcdc4be59f7b1ae64fef6cb96f7432a985d`
- Dataset URL: <https://titan-builder-ob009-artifact.miniup.app/api/data-file/titan-builder-ob009-artifact/workcore-repository-support-transfer.parquet>

This archive preserved the deterministic extraction builder, extraction tests, approved design and implementation plan, MagicAI host-overlay integration assets and MiniUp catalogue source.

## Runtime independence

MiniUp is not a production dependency of WorkCore. The complete expanded source now lives in GitHub. These datasets remain as publication provenance and an independently checksummed transfer copy.

The static catalogue source is under [`site/`](site). Download ZIPs are generated build outputs and are not committed to Git history.
