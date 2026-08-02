# MagicAI-native WorkCore extension CI evidence

## Permanent validation gates

- `Validate MagicAI-native WorkCore extensions`
  - Push run: `30754060748`
  - Result: success
  - Verifies repository tests, six deterministic extension builds, manifest/folder validation, generated PHP syntax and ZIP integrity.

- `Validate MagicAI 11 Laravel 10 native extensions`
  - Push run: `30754143205`
  - Result: success
  - Seven profiles passed: parent, commercial, business-operations, property-workforce, full, disabled and parent-absent-commercial.
  - Parent and Full profiles passed the complete migration sequence.

- `Validate MagicAI 11 compatibility`
  - Push run: `30754256378`
  - Result: success
  - MagicAI scan contract passed.
  - Foundation and Full Composer package profiles resolved and discovered safely in the disabled low-level package state.

## Generated release hashes

| Extension | Bytes | SHA-256 |
|---|---:|---|
| WorkCore | 2,273,064 | `08604cfb1c7a362f7c1836ff840ff00da2074d986168a34b079fe15c5fb975a4` |
| WorkCoreBusinessNetwork | 3,079 | `9edaebc4f40c8f7660f60232521c5555c9c03567383f9d04c0b39b623243440b` |
| WorkCoreCommercial | 2,917 | `917ec30941aea59cca8e2a7b189b3bd3c7c684c08f305e26837cdaac171e593a` |
| WorkCoreWorkOperations | 2,996 | `280bede86ab3ae6c499ca7929f3b385d4e9bfd267be8cc5288d8bf3b9639d1f9` |
| WorkCorePropertyOperations | 3,020 | `e73aced46af07b90cd8c32e369af5983593978526c3025b899a7e0c828b8001f` |
| WorkCoreWorkforceAssurance | 3,063 | `f4cfaaa8d36d88889abae0ae637604bfd947041da56c186ed44f4bafb1eca64d` |

Generated ZIP binaries are excluded from Git and rebuilt deterministically by CI.

## Superseded legacy assertion

The historical extraction verifier expected modules outside aggregate providers to load automatically. That assertion is intentionally superseded: automatic fallback loading would activate every domain from the parent extension and defeat independent add-on installation. The new Laravel 10 matrix verifies exact module activation for each supported profile instead.
