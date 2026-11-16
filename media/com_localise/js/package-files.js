'use strict';

const packageFilePicker = document.querySelector('#localise-package-files');
const packageFileSelect = document.querySelector('#jform_translations');

if (packageFilePicker && packageFileSelect) {
  const search = packageFilePicker.querySelector('input[type="search"]');
  const selectedOnly = packageFilePicker.querySelector('[data-file-selected]');
  const count = packageFilePicker.querySelector('[data-file-count]');
  const list = packageFilePicker.querySelector('[data-file-list]');
  const rows = new Map();
  const language = document.querySelector('#jform_language');
  const automaticSection = packageFilePicker.querySelector('[data-automatic-files]');
  const automaticList = packageFilePicker.querySelector('[data-automatic-list]');
  const automaticMap = JSON.parse(packageFilePicker.querySelector('[data-automatic-map]').textContent);

  function updateAutomaticFiles() {
    automaticList.replaceChildren();
    const tag = language.value;
    const files = automaticMap[tag];
    const selected = Array.from(packageFileSelect.selectedOptions, (option) => option.value);
    automaticSection.hidden = !files || !selected.length;
    if (automaticSection.hidden) return;

    const paths = [`pkg_${tag}.xml`];
    const folders = {site: 'language', administrator: 'administrator/language', api: 'api/language'};
    for (const [client, folder] of Object.entries(folders)) {
      if (client === 'api' || selected.some((value) => value.startsWith(`${client}_`))) {
        paths.push(...files[client].map((file) => `${folder}/${tag}/${file}`));
      }
    }
    for (const path of paths) {
      const item = document.createElement('li');
      item.textContent = path;
      automaticList.append(item);
    }
  }

  function updateList() {
    const query = search.value.trim().toLowerCase();
    for (const [option, row] of rows) {
      row.hidden = (selectedOnly.checked && !option.selected)
        || !`${option.text} ${option.value}`.toLowerCase().includes(query);
      const checkbox = row.querySelector('input');
      checkbox.checked = option.selected;
      checkbox.disabled = !option.selected && option.classList.contains('missing');
    }
    for (const group of list.children) {
      group.hidden = !Array.from(group.querySelectorAll('label')).some((row) => !row.hidden);
    }
    updateAutomaticFiles();
    count.textContent = `${packageFileSelect.selectedOptions.length} / ${packageFileSelect.options.length}`;
  }

  function buildList() {
    list.replaceChildren();
    rows.clear();
    for (const source of packageFileSelect.querySelectorAll('optgroup')) {
      const group = document.createElement('fieldset');
      group.className = 'mb-3';
      const title = document.createElement('legend');
      title.className = 'fs-6 fw-bold';
      title.textContent = source.label;
      group.append(title);

      for (const option of source.querySelectorAll('option')) {
        const row = document.createElement('label');
        row.className = `form-check ${option.className}`;
        const checkbox = document.createElement('input');
        checkbox.type = 'checkbox';
        checkbox.className = 'form-check-input';
        checkbox.addEventListener('change', () => {
          option.selected = checkbox.checked;
          updateList();
        });
        row.append(checkbox, option.text);
        if (option.classList.contains('missing')) {
          const status = document.createElement('small');
          status.className = 'd-block text-body-secondary';
          status.textContent = packageFilePicker.dataset.missingLabel;
          row.append(status);
        }
        group.append(row);
        rows.set(option, row);
      }
      list.append(group);
    }
    updateList();
  }

  language.addEventListener('change', updateAutomaticFiles);
  search.addEventListener('input', updateList);
  selectedOnly.addEventListener('change', updateList);
  for (const button of packageFilePicker.querySelectorAll('[data-file-action]')) {
    button.addEventListener('click', () => {
      for (const [option, row] of rows) {
        const adding = button.dataset.fileAction === 'add';
        if (!row.hidden && (!adding || !option.classList.contains('missing'))) {
          option.selected = adding;
        }
      }
      updateList();
    });
  }
  new MutationObserver(buildList).observe(packageFileSelect, {childList: true, subtree: true});
  buildList();
  packageFileSelect.hidden = true;
  packageFilePicker.hidden = false;
}

const archivePreview = document.querySelector('#localise-archive-preview');

if (archivePreview) {
  const filename = archivePreview.querySelector('#localise-archive-filename');
  const custom = archivePreview.querySelector('#localise-custom-archive');
  const warning = archivePreview.querySelector('#localise-archive-warning');
  const language = document.querySelector('#jform_language');
  const version = document.querySelector('#jform_version');
  const revision = document.querySelector('#jform_packversion');

  function updateArchiveName() {
    filename.readOnly = !custom.checked;
    warning.hidden = !custom.checked;

    if (custom.checked) return;

    const ready = Boolean(language.value)
      && /^[0-9]+\.[0-9]+\.[0-9]+$/.test(version.value)
      && /^[0-9]+$/.test(revision.value);

    filename.value = ready
      ? archivePreview.dataset.template
        .replace('{language}', language.value)
        .replace('{version}', version.value)
        .replace('{revision}', revision.value)
      : '';
  }

  archivePreview.hidden = false;
  custom.addEventListener('change', updateArchiveName);
  language.addEventListener('change', updateArchiveName);
  version.addEventListener('input', updateArchiveName);
  revision.addEventListener('input', updateArchiveName);
  updateArchiveName();
}

const extensionArchivePreview = document.querySelector('#localise-extension-archive-preview');

if (extensionArchivePreview) {
  const useTemplate = document.querySelector('#localise-extension-standard-archive');
  const filename = document.querySelector('#localise-extension-archive-filename');
  const language = document.querySelector('#jform_language');
  const version = document.querySelector('#jform_version');
  const revision = document.querySelector('#jform_packversion');

  function updateExtensionArchiveName() {
    const ready = useTemplate.checked && Boolean(language.value)
      && /^[0-9]+\.[0-9]+\.[0-9]+$/.test(version.value)
      && /^[0-9]+$/.test(revision.value);

    filename.disabled = useTemplate.checked;
    extensionArchivePreview.hidden = !ready;
    extensionArchivePreview.textContent = ready
      ? extensionArchivePreview.dataset.template
        .replace('{language}', language.value)
        .replace('{version}', version.value)
        .replace('{revision}', revision.value)
      : '';
  }

  useTemplate.addEventListener('change', updateExtensionArchiveName);
  language.addEventListener('change', updateExtensionArchiveName);
  version.addEventListener('input', updateExtensionArchiveName);
  revision.addEventListener('input', updateExtensionArchiveName);
  updateExtensionArchiveName();
}
