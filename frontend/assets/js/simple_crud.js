/**
 * Generic CRUD table engine.
 * Used by simple modules (categories, authors, publishers, suppliers, customers)
 * so each page only needs a small config object instead of repeating table/form/modal code.
 *
 * Config shape:
 * {
 *   endpoint: '/categories/index.php',
 *   idField: 'category_id',
 *   title: 'Category',
 *   columns: [{ key: 'category_name', label: 'Name' }, ...],
 *   formFields: [{ name: 'category_name', label: 'Category Name', required: true }, ...],
 *   canWrite: true/false   // pass Layout.hasRole(...) result
 * }
 */
function initSimpleCrud(config) {
  let items = [];

  async function load() {
    const container = document.getElementById('crudTable');
    container.innerHTML = '<div class="loading">Loading...</div>';
    const search = document.getElementById('searchInput')?.value.trim() || '';
    const query = search ? '?search=' + encodeURIComponent(search) : '';
    try {
      items = await Api.get(config.endpoint + query);
      render();
    } catch (e) {}
  }

  function render() {
    const container = document.getElementById('crudTable');
    if (!items.length) {
      container.innerHTML = '<div class="empty-state">No records found.</div>';
      return;
    }
    container.innerHTML = `
      <table>
        <thead><tr>
          ${config.columns.map(c => `<th>${c.label}</th>`).join('')}
          ${config.canWrite ? '<th>Actions</th>' : ''}
          ${config.extraColumn ? `<th>${config.extraColumn.label}</th>` : ''}
        </tr></thead>
        <tbody>
          ${items.map(item => `
            <tr>
              ${config.columns.map(c => `<td>${escapeHtml(formatCell(item[c.key], c))}</td>`).join('')}
              ${config.extraColumn ? `<td>${config.extraColumn.render(item)}</td>` : ''}
              ${config.canWrite ? `
              <td>
                <button class="btn btn-outline btn-sm" onclick='openForm(${JSON.stringify(item[config.idField])})'>Edit</button>
                <button class="btn btn-danger btn-sm" onclick="removeItem(${item[config.idField]})">Delete</button>
              </td>` : ''}
            </tr>`).join('')}
        </tbody>
      </table>`;
  }

  function formatCell(value, col) {
    if (col.format) return col.format(value);
    return value ?? '—';
  }

  window.openForm = function (id) {
    const item = id ? items.find(i => i[config.idField] == id) : {};
    const fieldsHtml = config.formFields.map(f => {
      if (f.type === 'select') {
        const opts = f.options.map(o => `<option value="${o.value}" ${item[f.name] == o.value ? 'selected' : ''}>${o.label}</option>`).join('');
        return `<div class="form-group"><label>${f.label}${f.required ? ' *' : ''}</label><select name="${f.name}">${opts}</select></div>`;
      }
      if (f.type === 'textarea') {
        return `<div class="form-group"><label>${f.label}</label><textarea name="${f.name}" rows="3">${escapeHtml(item[f.name] || '')}</textarea></div>`;
      }
      return `<div class="form-group"><label>${f.label}${f.required ? ' *' : ''}</label>
        <input type="${f.type || 'text'}" name="${f.name}" value="${escapeHtml(item[f.name] ?? '')}" ${f.required ? 'required' : ''}></div>`;
    }).join('');

    Modal.open(id ? `Edit ${config.title}` : `Add ${config.title}`, `<form id="crudForm">${fieldsHtml}</form>`, `
      <button class="btn btn-outline" onclick="Modal.close()">Cancel</button>
      <button class="btn" onclick="saveItem(${id ? `'${id}'` : 'null'})">Save</button>
    `);
  };

  window.saveItem = async function (id) {
    const form = document.getElementById('crudForm');
    const data = Object.fromEntries(new FormData(form).entries());
    if (id) data[config.idField] = id;
    try {
      if (id) await Api.put(config.endpoint, data);
      else await Api.post(config.endpoint, data);
      Toast.success(`${config.title} saved.`);
      Modal.close();
      load();
    } catch (e) {}
  };

  window.removeItem = async function (id) {
    if (!confirmAction(`Delete this ${config.title.toLowerCase()}?`)) return;
    try {
      await Api.del(`${config.endpoint}?id=${id}`);
      Toast.success(`${config.title} deleted.`);
      load();
    } catch (e) {}
  };

  document.getElementById('addBtn')?.addEventListener('click', () => openForm(null));
  document.getElementById('searchInput')?.addEventListener('input', debounceCrud(load, 400));

  load();
}

function debounceCrud(fn, ms) { let t; return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), ms); }; }
