
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
