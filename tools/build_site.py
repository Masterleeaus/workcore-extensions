from __future__ import annotations

import argparse
import json
import shutil
import sys
import zipfile
from pathlib import Path
from typing import Any

if __package__ in {None, ''}:
    sys.path.insert(0, str(Path(__file__).resolve().parents[1]))

from tools.build_extensions import GROUPS


def _format_bytes(value: int) -> str:
    units = ['B', 'KB', 'MB', 'GB']
    amount = float(value)
    for unit in units:
        if amount < 1024 or unit == units[-1]:
            return f'{amount:.1f} {unit}' if unit != 'B' else f'{int(amount)} B'
        amount /= 1024
    return f'{value} B'


def _page(title: str, active: str, body: str) -> str:
    nav = ''.join(
        f'<a href="{href}" class="nav-link{" active" if key == active else ""}">{label}</a>'
        for key, href, label in [
            ('overview', 'index.html', 'Overview'),
            ('packages', 'packages.html', 'Packages'),
            ('architecture', 'architecture.html', 'Architecture'),
        ]
    )
    return f'''<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="Verified five-domain WorkCore extension release catalogue.">
  <title>{title} · WorkCore Extensions</title>
  <link rel="stylesheet" href="css/tokens.css">
  <link rel="stylesheet" href="css/base.css">
  <link rel="stylesheet" href="css/layout.css">
  <link rel="stylesheet" href="css/components.css">
</head>
<body>
  <header class="site-header">
    <a class="brand" href="index.html" aria-label="WorkCore Extensions home">
      <span class="brand-mark" aria-hidden="true">W</span>
      <span><strong>WorkCore</strong><small>Extension Foundry</small></span>
    </a>
    <nav aria-label="Primary navigation">{nav}</nav>
  </header>
  <main>{body}</main>
  <footer>
    <p>Generated from the verified WorkCore–MagicAI consolidated source on 2 August 2026.</p>
  </footer>
  <script type="module" src="js/catalogue.js"></script>
</body>
</html>
'''


def _write(path: Path, content: str) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(content, encoding='utf-8')


def _zip_site(site_root: Path, zip_path: Path) -> None:
    zip_path.parent.mkdir(parents=True, exist_ok=True)
    if zip_path.exists():
        zip_path.unlink()
    with zipfile.ZipFile(zip_path, 'w', compression=zipfile.ZIP_DEFLATED, compresslevel=9) as archive:
        for path in sorted(site_root.rglob('*')):
            if not path.is_file():
                continue
            info = zipfile.ZipInfo(path.relative_to(site_root).as_posix(), date_time=(2026, 8, 2, 0, 0, 0))
            info.compress_type = zipfile.ZIP_DEFLATED
            info.external_attr = 0o100644 << 16
            archive.writestr(info, path.read_bytes())


def build_site(repo_root: Path, site_root: Path, zip_path: Path | None = None) -> Path:
    repo_root = repo_root.resolve()
    site_root = site_root.resolve()
    if site_root.exists():
        shutil.rmtree(site_root)
    site_root.mkdir(parents=True)

    dist = repo_root / 'dist'
    downloads = site_root / 'downloads'
    downloads.mkdir(parents=True)

    download_names = [
        'workcore-shared-foundation.zip',
        *[f'workcore-{group}.zip' for group in GROUPS],
        'workcore-extension-workspace.zip',
        'WorkCore-MagicAI-Consolidated-2026-08-02.zip',
        'ownership-manifest.json',
    ]
    for name in download_names:
        source = dist / name
        if source.is_file():
            shutil.copy2(source, downloads / name)

    package_records: list[dict[str, Any]] = []
    for group, definition in GROUPS.items():
        archive = downloads / f'workcore-{group}.zip'
        package_records.append({
            'slug': group,
            'name': definition['title'],
            'type': 'Domain extension',
            'modules': definition['modules'],
            'runtimeKeys': definition['runtime_keys'],
            'archive': f'downloads/{archive.name}',
            'archiveSize': _format_bytes(archive.stat().st_size),
        })
    shared_archive = downloads / 'workcore-shared-foundation.zip'
    package_records.insert(0, {
        'slug': 'shared-foundation',
        'name': 'WorkCore Shared Foundation',
        'type': 'Required runtime',
        'modules': [],
        'runtimeKeys': [],
        'archive': f'downloads/{shared_archive.name}',
        'archiveSize': _format_bytes(shared_archive.stat().st_size),
    })

    _write(site_root / 'data/catalogue.json', json.dumps({'packages': package_records}, indent=2) + '\n')

    overview_body = '''
<section class="hero shell">
  <div class="hero-copy">
    <p class="section-label">Five-domain extraction</p>
    <h1>WorkCore, divided without being diminished.</h1>
    <p class="lede">Every one of the 35 operational modules now has a single package owner. Shared authority, migrations and governance remain intact beneath five installable domain extensions.</p>
    <div class="actions">
      <a class="button primary" href="downloads/workcore-extension-workspace.zip">Download complete workspace</a>
      <a class="button secondary" href="packages.html">Review packages</a>
    </div>
  </div>
  <div class="hero-metrics" aria-label="Release metrics">
    <div><strong>35</strong><span>modules assigned</span></div>
    <div><strong>2,129</strong><span>files ownership-tracked</span></div>
    <div><strong>2,061</strong><span>PHP files syntax-checked</span></div>
    <div><strong>5</strong><span>domain groups</span></div>
  </div>
</section>
<section class="shell flow-section">
  <div class="section-heading">
    <h2>Release structure</h2>
    <p>One shared foundation carries the stable runtime. The five groups own the business capability surface.</p>
  </div>
  <div class="release-flow">
    <article><span>01</span><h3>Shared Foundation</h3><p>Tenancy, actions, read models, outbox, Rewind, configuration and historical schema.</p></article>
    <div class="flow-line" aria-hidden="true"></div>
    <article><span>02</span><h3>Five Extensions</h3><p>Business Network, Commercial, Work Operations, Property Operations and Workforce Assurance.</p></article>
    <div class="flow-line" aria-hidden="true"></div>
    <article><span>03</span><h3>Host Overlay</h3><p>MagicAI and PWA integration files remain preserved separately rather than becoming duplicate authorities.</p></article>
  </div>
</section>
<section class="shell callout">
  <div><h2>Zero-loss evidence included</h2><p>The downloadable ownership manifest records each original WorkCore file, destination package, source checksum, destination checksum and intentional transformations.</p></div>
  <a class="text-link" href="downloads/ownership-manifest.json">Open ownership manifest →</a>
</section>
'''
    _write(site_root / 'index.html', _page('Overview', 'overview', overview_body))

    packages_body = '''
<section class="page-intro shell">
  <p class="section-label">Release packages</p>
  <h1>Five domain extensions, one shared authority.</h1>
  <p>Download each group independently or use the complete workspace archive.</p>
</section>
<section class="shell package-list" id="package-list" aria-live="polite">
  <div class="loading">Loading verified package metadata…</div>
</section>
<section class="shell download-bar">
  <div><h2>Need the complete source baseline?</h2><p>The untouched consolidated archive remains available beside the extracted workspace.</p></div>
  <a class="button secondary" href="downloads/WorkCore-MagicAI-Consolidated-2026-08-02.zip">Download original archive</a>
</section>
'''
    _write(site_root / 'packages.html', _page('Packages', 'packages', packages_body))

    architecture_rows = ''.join(
        f'''<article class="architecture-row">
  <div><span>{index:02d}</span><h2>{definition['title'].replace('WorkCore ', '')}</h2></div>
  <p>{', '.join(definition['modules'])}</p>
  <strong>{len(definition['modules'])} modules</strong>
</article>'''
        for index, definition in enumerate(GROUPS.values(), start=1)
    )
    architecture_body = f'''
<section class="page-intro shell">
  <p class="section-label">Ownership architecture</p>
  <h1>Boundaries follow operational authority.</h1>
  <p>Modules are grouped by the records and decisions they own, while cross-domain infrastructure remains in the shared foundation.</p>
</section>
<section class="shell architecture-list">{architecture_rows}</section>
<section class="shell principles">
  <h2>Extraction principles</h2>
  <div class="principle-grid">
    <article><h3>Single ownership</h3><p>No module directory appears in more than one extension.</p></article>
    <article><h3>Canonical namespaces</h3><p>Phase one changes packaging, not thousands of PHP class names.</p></article>
    <article><h3>Safe optional loading</h3><p>Unavailable providers are skipped and the old load-everything fallback is removed.</p></article>
    <article><h3>Schema preservation</h3><p>Historical migrations stay centralized until clean-install baselines are proven.</p></article>
  </div>
</section>
'''
    _write(site_root / 'architecture.html', _page('Architecture', 'architecture', architecture_body))

    _write(site_root / 'css/tokens.css', '''
:root {
  --bg: #080b12;
  --surface: #111722;
  --surface-2: #171f2d;
  --text: #f4f7fb;
  --muted: #9aa8bb;
  --line: #293448;
  --accent: #7ef0c3;
  --accent-strong: #34dba1;
  --max: 1180px;
  --radius: 20px;
  --shadow: 0 24px 70px rgba(0, 0, 0, .28);
}
''')
    _write(site_root / 'css/base.css', '''
* { box-sizing: border-box; }
html { color-scheme: dark; scroll-behavior: smooth; }
body { margin: 0; background: var(--bg); color: var(--text); font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; line-height: 1.6; }
a { color: inherit; }
h1, h2, h3, p { margin-top: 0; }
h1 { max-width: 900px; font-size: clamp(2.7rem, 7vw, 6.4rem); line-height: .98; letter-spacing: -.06em; }
h2 { font-size: clamp(1.8rem, 3vw, 3rem); line-height: 1.08; letter-spacing: -.035em; }
h3 { font-size: 1.08rem; }
p { color: var(--muted); }
button, a { -webkit-tap-highlight-color: transparent; }
:focus-visible { outline: 3px solid var(--accent); outline-offset: 4px; }
''')
    _write(site_root / 'css/layout.css', '''
.shell { width: min(calc(100% - 40px), var(--max)); margin-inline: auto; }
.site-header { width: min(calc(100% - 40px), var(--max)); min-height: 88px; margin-inline: auto; display: flex; align-items: center; justify-content: space-between; gap: 30px; }
.site-header nav { display: flex; gap: 8px; }
.hero { min-height: calc(100vh - 88px); display: grid; grid-template-columns: 1.25fr .75fr; gap: 70px; align-items: center; padding-block: 70px; }
.flow-section, .callout, .package-list, .architecture-list, .principles, .download-bar { margin-top: 120px; }
.release-flow { display: grid; grid-template-columns: 1fr 56px 1fr 56px 1fr; align-items: center; margin-top: 45px; }
.callout, .download-bar { display: flex; align-items: center; justify-content: space-between; gap: 40px; margin-bottom: 120px; padding: 48px; }
.page-intro { padding-top: 110px; }
.page-intro h1 { max-width: 960px; }
.architecture-list { display: grid; }
.principle-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 18px; }
footer { width: min(calc(100% - 40px), var(--max)); margin: 80px auto 0; padding: 30px 0 50px; border-top: 1px solid var(--line); }
@media (max-width: 820px) {
  .site-header { align-items: flex-start; padding-block: 20px; flex-direction: column; }
  .site-header nav { width: 100%; overflow-x: auto; }
  .hero { min-height: auto; grid-template-columns: 1fr; gap: 40px; padding-top: 90px; }
  .release-flow { grid-template-columns: 1fr; gap: 16px; }
  .flow-line { width: 1px; height: 32px; margin-left: 25px; }
  .callout, .download-bar { align-items: flex-start; flex-direction: column; padding: 30px; }
  .principle-grid { grid-template-columns: 1fr; }
}
''')
    _write(site_root / 'css/components.css', '''
.brand { display: flex; align-items: center; gap: 12px; text-decoration: none; }
.brand-mark { width: 44px; height: 44px; display: grid; place-items: center; border-radius: 14px; background: var(--accent); color: #04110c; font-weight: 900; }
.brand strong, .brand small { display: block; }
.brand small { color: var(--muted); font-size: .72rem; letter-spacing: .08em; text-transform: uppercase; }
.nav-link { padding: 10px 14px; color: var(--muted); text-decoration: none; border-radius: 10px; white-space: nowrap; }
.nav-link:hover, .nav-link.active { color: var(--text); background: var(--surface); }
.section-label { color: var(--accent); font-size: .78rem; font-weight: 800; letter-spacing: .15em; text-transform: uppercase; }
.lede { max-width: 760px; font-size: clamp(1.05rem, 1.8vw, 1.32rem); }
.actions { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 34px; }
.button { display: inline-flex; min-height: 50px; align-items: center; justify-content: center; padding: 0 20px; border: 1px solid var(--line); border-radius: 12px; text-decoration: none; font-weight: 750; }
.button.primary { background: var(--accent); border-color: var(--accent); color: #04110c; }
.button.primary:hover { background: var(--accent-strong); }
.button.secondary:hover { background: var(--surface-2); }
.hero-metrics { border-left: 1px solid var(--line); }
.hero-metrics div { display: grid; grid-template-columns: 110px 1fr; gap: 18px; align-items: baseline; padding: 22px 0 22px 34px; border-bottom: 1px solid var(--line); }
.hero-metrics strong { font-size: 2rem; }
.hero-metrics span { color: var(--muted); }
.section-heading { display: flex; justify-content: space-between; gap: 40px; align-items: end; }
.section-heading p { max-width: 480px; }
.release-flow article { min-height: 220px; padding: 28px; background: var(--surface); border: 1px solid var(--line); border-radius: var(--radius); box-shadow: var(--shadow); }
.release-flow article span { color: var(--accent); font-size: .8rem; font-weight: 800; }
.flow-line { height: 1px; background: var(--line); }
.callout, .download-bar { background: var(--surface); border: 1px solid var(--line); border-radius: var(--radius); }
.text-link { color: var(--accent); font-weight: 800; text-decoration: none; white-space: nowrap; }
.package-list { border-top: 1px solid var(--line); }
.package-row { display: grid; grid-template-columns: 1.2fr 1.8fr auto; gap: 35px; padding: 34px 0; border-bottom: 1px solid var(--line); align-items: center; }
.package-row h2 { margin-bottom: 8px; font-size: 1.5rem; }
.package-row .type { color: var(--accent); font-size: .78rem; font-weight: 800; text-transform: uppercase; letter-spacing: .1em; }
.module-list { color: var(--muted); }
.package-actions { text-align: right; }
.package-actions small { display: block; margin-top: 8px; color: var(--muted); }
.architecture-row { display: grid; grid-template-columns: 1fr 2fr auto; gap: 35px; align-items: center; padding: 30px 0; border-bottom: 1px solid var(--line); }
.architecture-row > div { display: flex; align-items: baseline; gap: 18px; }
.architecture-row span { color: var(--accent); font-size: .78rem; font-weight: 800; }
.architecture-row h2 { margin: 0; font-size: 1.5rem; }
.architecture-row p { margin: 0; }
.architecture-row strong { white-space: nowrap; }
.principle-grid article { min-height: 180px; padding: 28px; background: var(--surface); border: 1px solid var(--line); border-radius: var(--radius); }
.loading, .error { padding: 40px 0; color: var(--muted); }
@media (max-width: 820px) {
  .section-heading, .package-row, .architecture-row { grid-template-columns: 1fr; display: grid; gap: 14px; }
  .package-actions { text-align: left; }
  .hero-metrics { border-left: 0; }
  .hero-metrics div { padding-left: 0; }
}
''')
    _write(site_root / 'js/catalogue.js', '''
const list = document.querySelector('#package-list');
if (list) {
  try {
    const response = await fetch('data/catalogue.json');
    if (!response.ok) throw new Error(`Catalogue request failed: ${response.status}`);
    const data = await response.json();
    list.innerHTML = data.packages.map((item) => `
      <article class="package-row">
        <div><span class="type">${item.type}</span><h2>${item.name}</h2></div>
        <div class="module-list">${item.modules.length ? item.modules.join(' · ') : 'Tenancy · Actions · Read models · Migrations · Rewind · Host bridges'}</div>
        <div class="package-actions"><a class="button secondary" href="${item.archive}">Download ZIP</a><small>${item.archiveSize}</small></div>
      </article>
    `).join('');
  } catch (error) {
    list.innerHTML = `<p class="error">Package metadata could not be loaded. ${error.message}</p>`;
  }
}
''')

    output_zip = zip_path.resolve() if zip_path is not None else repo_root / 'dist/workcore-extensions-miniup-site.zip'
    _zip_site(site_root, output_zip)
    return output_zip


def main() -> int:
    parser = argparse.ArgumentParser(description='Build the WorkCore MiniUp catalogue.')
    parser.add_argument('--repo', type=Path, required=True)
    parser.add_argument('--site', type=Path, required=True)
    args = parser.parse_args()
    print(build_site(args.repo, args.site))
    return 0


if __name__ == '__main__':
    raise SystemExit(main())
