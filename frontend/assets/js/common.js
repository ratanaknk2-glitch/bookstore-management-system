/**
 * Shared layout + auth guard. Include on every protected page after api.js and toast.js:
 *   <div id="app-shell"></div>
 *   <script>Layout.init('books');</script>   // 'books' = current page key, used to highlight nav
 */

const NAV_ICONS = {
  dashboard: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="9"/><rect x="14" y="3" width="7" height="5"/><rect x="14" y="12" width="7" height="9"/><rect x="3" y="16" width="7" height="5"/></svg>',
  sales: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="20" r="1.5"/><circle cx="18" cy="20" r="1.5"/><path d="M2 3h3l2.4 12.2a2 2 0 0 0 2 1.8h8.2a2 2 0 0 0 2-1.6L21 8H6"/></svg>',
  books: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4.5A2.5 2.5 0 0 1 6.5 2H20v18H6.5A2.5 2.5 0 0 0 4 22.5z"/><path d="M4 4.5v16A2.5 2.5 0 0 0 6.5 23"/><line x1="8" y1="7" x2="15" y2="7"/></svg>',
  categories: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.6 12.6 12.7 20.5a2 2 0 0 1-2.8 0l-7-7a2 2 0 0 1 0-2.8l7.9-7.9a2 2 0 0 1 1.4-.6H19a2 2 0 0 1 2 2v5.6a2 2 0 0 1-.4 1.4z"/><circle cx="15.5" cy="8.5" r="1.5"/></svg>',
  authors: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 21v-1a6 6 0 0 1 6-6h4a6 6 0 0 1 6 6v1"/></svg>',
  publishers: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="3" width="16" height="18"/><line x1="8" y1="7" x2="8" y2="7.01"/><line x1="12" y1="7" x2="12" y2="7.01"/><line x1="16" y1="7" x2="16" y2="7.01"/><line x1="8" y1="11" x2="8" y2="11.01"/><line x1="12" y1="11" x2="12" y2="11.01"/><line x1="16" y1="11" x2="16" y2="11.01"/><line x1="9" y1="21" x2="9" y2="15"/><line x1="15" y1="21" x2="15" y2="15"/></svg>',
  suppliers: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="6" width="14" height="11"/><path d="M15 9h4l3 3v5h-7z"/><circle cx="6" cy="19" r="1.8"/><circle cx="17.5" cy="19" r="1.8"/></svg>',
  customers: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="8" r="3.5"/><path d="M2.5 21v-1a6 6 0 0 1 6-6h1a6 6 0 0 1 6 6v1"/><circle cx="18" cy="8" r="2.5"/><path d="M16.5 12.2A5 5 0 0 1 21.5 17v1"/></svg>',
  purchases: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="6" y="3" width="12" height="18" rx="1"/><path d="M9 3v2h6V3"/><line x1="9" y1="9" x2="15" y2="9"/><line x1="9" y1="13" x2="15" y2="13"/><line x1="9" y1="17" x2="12" y2="17"/></svg>',
  inventory: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 8 12 3 3 8v8l9 5 9-5z"/><path d="M3 8l9 5 9-5"/><line x1="12" y1="13" x2="12" y2="21"/></svg>',
  discounts: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="5" x2="5" y2="19"/><circle cx="7" cy="7" r="2.2"/><circle cx="17" cy="17" r="2.2"/></svg>',
  employees: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="4" width="14" height="17" rx="1.5"/><circle cx="12" cy="10" r="2.5"/><path d="M8.5 16.5a3.5 3.5 0 0 1 7 0"/></svg>',
  reports: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="21" x2="5" y2="11"/><line x1="12" y1="21" x2="12" y2="6"/><line x1="19" y1="21" x2="19" y2="14"/><line x1="3" y1="21" x2="21" y2="21"/></svg>',
  notifications: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>',
  settings: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>',
  users: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="7.5" r="3.5"/><path d="M5 21v-1a7 7 0 0 1 14 0v1"/><path d="M12 3.5v0"/><circle cx="18.5" cy="16" r="1"/></svg>',
};

// Generic "no photo" placeholder used anywhere a user avatar is shown but the
// user has no profile_image on file yet.
const AVATAR_PLACEHOLDER_SVG = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 21v-1a8 8 0 0 1 16 0v1"/></svg>';

/** Builds the avatar markup (photo if present, otherwise the gray placeholder icon). */
function avatarHtml(user, extraClass = '') {
  if (user && user.profile_image) {
    return `<span class="sidebar-user-avatar ${extraClass}"><img src="../backend/uploads/avatars/${encodeURIComponent(user.profile_image)}" alt=""></span>`;
  }
  return `<span class="sidebar-user-avatar ${extraClass}">${AVATAR_PLACEHOLDER_SVG}</span>`;
}

const NAV_ITEMS = [
  { key: 'dashboard', label: 'Dashboard', href: 'dashboard.html', roles: ['Admin', 'Manager', 'Cashier'] },
  { key: 'sales', label: 'Sales (POS)', href: 'sales.html', roles: ['Admin', 'Manager', 'Cashier'] },
  { key: 'books', label: 'Books', href: 'books.html', roles: ['Admin', 'Manager', 'Cashier'] },
  { key: 'categories', label: 'Categories', href: 'categories.html', roles: ['Admin', 'Manager'] },
  { key: 'authors', label: 'Authors', href: 'authors.html', roles: ['Admin', 'Manager'] },
  { key: 'publishers', label: 'Publishers', href: 'publishers.html', roles: ['Admin', 'Manager'] },
  { key: 'suppliers', label: 'Suppliers', href: 'suppliers.html', roles: ['Admin', 'Manager'] },
  { key: 'customers', label: 'Customers', href: 'customers.html', roles: ['Admin', 'Manager', 'Cashier'] },
  { key: 'purchases', label: 'Purchases', href: 'purchases.html', roles: ['Admin', 'Manager'] },
  { key: 'inventory', label: 'Inventory', href: 'inventory.html', roles: ['Admin', 'Manager'] },
  { key: 'discounts', label: 'Discounts', href: 'discounts.html', roles: ['Admin', 'Manager'] },
  { key: 'employees', label: 'Employees', href: 'employees.html', roles: ['Admin', 'Manager'] },
  { key: 'reports', label: 'Reports', href: 'reports.html', roles: ['Admin', 'Manager'] },
  { key: 'notifications', label: 'Notifications', href: 'notifications.html', roles: ['Admin', 'Manager', 'Cashier'] },
  { key: 'settings', label: 'Settings', href: 'settings.html', roles: ['Admin'] },
  { key: 'users', label: 'User Accounts', href: 'users.html', roles: ['Admin'] },
];

/**
 * SHELL ARCHITECTURE:
 * - app.html is the ONE page that stays loaded. It builds the sidebar/topbar once,
 *   via Layout.init() below, and holds an <iframe> that displays whichever content
 *   page is selected. Switching tabs only changes the iframe's src - the sidebar,
 *   topbar, and browser tab itself never reload, so there's no flash/flicker.
 * - Every content page (dashboard.html, books.html, ...) runs INSIDE that iframe,
 *   and uses Embedded.init() instead of Layout.init() - it only needs the session/
 *   role/CSRF token, since the surrounding chrome is provided by the parent frame.
 */

const Layout = {
  currentUser: null,
  branding: { store_name: 'Book Store MS', store_logo: '' },
  nav: [],

  async init() {
    try {
      const session = await Api.get('/auth/session.php');
      this.currentUser = session;
      Api.setCsrfToken(session.csrf_token);
    } catch {
      location.href = 'login.html';
      return;
    }
    try {
      const settings = await Api.get('/settings/index.php');
      if (settings.store_name) this.branding.store_name = settings.store_name;
      if (settings.store_logo) this.branding.store_logo = settings.store_logo;
    } catch {
      // Non-fatal - fall back to defaults if settings can't be loaded
    }
    this.nav = NAV_ITEMS.filter(item => item.roles.includes(this.currentUser.role));
    this.renderShell();
    window.addEventListener('hashchange', () => this.loadFromHash());
    this.loadFromHash();
    this.loadNotificationBadge();
    setInterval(() => this.loadNotificationBadge(), 15000); // keep the count fresh in the background
  },

  renderShell() {
    // Notifications and Settings move out of the sidebar list and render as
    // icon-only buttons in the topbar instead (see topbarIconsHtml below).
    // They stay in `this.nav` (role-filtered) so hash routing, active-state
    // highlighting, and role checks below all keep working unchanged.
    const TOPBAR_ICON_KEYS = ['notifications', 'settings'];

    const navHtml = this.nav
      .filter(item => !TOPBAR_ICON_KEYS.includes(item.key))
      .map(item => `
      <li><a href="#${item.key}" data-key="${item.key}" class="nav-link">
        <span class="nav-icon">${NAV_ICONS[item.key] || ''}</span>
        <span>${item.label}</span>
      </a></li>
    `).join('');

    const topbarIconsHtml = TOPBAR_ICON_KEYS
      .filter(key => this.nav.some(item => item.key === key)) // respects role filtering already applied to this.nav
      .map(key => {
        const item = this.nav.find(i => i.key === key);
        const badge = key === 'notifications' ? '<span class="nav-badge topbar-icon-badge" id="notifBadge" style="display:none;"></span>' : '';
        return `
        <a href="#${key}" data-key="${key}" class="nav-link topbar-icon-btn" title="${escapeHtml(item.label)}" aria-label="${escapeHtml(item.label)}">
          <span class="nav-icon">${NAV_ICONS[key] || ''}</span>
          ${badge}
        </a>`;
      }).join('');

    const logoHtml = this.branding.store_logo
      ? `<img src="../backend/uploads/logo/${this.branding.store_logo}" alt="Logo" class="brand-logo">`
      : `<span class="brand-logo-placeholder">📚</span>`;

    const chevronSvg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="18 15 12 9 6 15"/></svg>';

    const shell = document.getElementById('app-shell');
    shell.innerHTML = `
      <div class="app-shell">
        <aside class="sidebar" id="sidebar">
          <div class="sidebar-brand">${logoHtml}<span>${escapeHtml(this.branding.store_name)}</span></div>
          <ul class="sidebar-nav">${navHtml}</ul>
          <div class="sidebar-footer" id="sidebarFooter">
            <button type="button" class="sidebar-user-trigger" id="sidebarUserTrigger" aria-haspopup="true" aria-expanded="false">
              ${avatarHtml(this.currentUser)}
              <span class="sidebar-user-info">
                <span class="sidebar-user-name">${escapeHtml(this.currentUser.full_name)}</span>
                <span class="sidebar-user-role">${escapeHtml(this.currentUser.role)}</span>
              </span>
              <span class="sidebar-user-chevron">${chevronSvg}</span>
            </button>
            <div class="sidebar-user-popup" id="sidebarUserPopup">
              <div class="sidebar-user-popup-header">
                ${avatarHtml(this.currentUser, 'sidebar-user-avatar-lg')}
                <div>
                  <div class="sidebar-user-popup-name">${escapeHtml(this.currentUser.full_name)}</div>
                  <div class="sidebar-user-popup-role">${escapeHtml(this.currentUser.role)}</div>
                </div>
              </div>
              <a href="#profile" data-key="profile" class="sidebar-user-popup-link nav-link">Profile</a>
              <button type="button" class="sidebar-user-popup-link sidebar-user-popup-logout" id="logoutBtn">Logout</button>
            </div>
          </div>
        </aside>
        <div class="main-content">
          <header class="topbar">
            <div class="topbar-left">
              <button class="menu-toggle" id="menuToggle">☰</button>
              <h1 class="topbar-title" id="topbarTitle"></h1>
            </div>
            <div class="topbar-icons">${topbarIconsHtml}</div>
          </header>
          <iframe id="contentFrame" class="content-frame" title="Page content"></iframe>
        </div>
      </div>
      <div id="toast-container"></div>
    `;

    document.getElementById('logoutBtn').addEventListener('click', () => this.logout());
    document.getElementById('menuToggle').addEventListener('click', () => {
      document.getElementById('sidebar').classList.toggle('open');
    });
    document.querySelectorAll('.nav-link').forEach(a => {
      a.addEventListener('click', () => {
        document.getElementById('sidebar').classList.remove('open'); // auto-close on mobile
        this.closeUserPopup();
      });
    });
    document.getElementById('contentFrame').addEventListener('load', () => {
      this.loadNotificationBadge();
      this.bindIframeClickClose();
    });

    // Account menu: click the avatar/name row to open, click outside or Escape to close.
    const trigger = document.getElementById('sidebarUserTrigger');
    trigger.addEventListener('click', (e) => {
      e.stopPropagation();
      this.toggleUserPopup();
    });
    document.addEventListener('click', (e) => {
      const footer = document.getElementById('sidebarFooter');
      if (footer && !footer.contains(e.target)) this.closeUserPopup();
    });
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') this.closeUserPopup();
    });
  },

  toggleUserPopup() {
    const popup = document.getElementById('sidebarUserPopup');
    const trigger = document.getElementById('sidebarUserTrigger');
    if (!popup || !trigger) return;
    const willOpen = !popup.classList.contains('open');
    popup.classList.toggle('open', willOpen);
    trigger.setAttribute('aria-expanded', String(willOpen));
  },

  closeUserPopup() {
    const popup = document.getElementById('sidebarUserPopup');
    const trigger = document.getElementById('sidebarUserTrigger');
    if (popup) popup.classList.remove('open');
    if (trigger) trigger.setAttribute('aria-expanded', 'false');
  },

  /** The profile-avatar popup only catches outside clicks in THIS document.
   *  Every content page renders inside #contentFrame's own document, so a
   *  click anywhere in the main content area would otherwise never close it.
   *  Re-attached on every iframe navigation since a new document loads each time. */
  bindIframeClickClose() {
    try {
      const frameDoc = document.getElementById('contentFrame').contentWindow.document;
      frameDoc.addEventListener('click', () => this.closeUserPopup());
    } catch {
      // Same-origin by design (see SHELL ARCHITECTURE note above) - non-fatal if it ever isn't.
    }
  },

  /** Re-fetches the session (picks up a freshly-uploaded avatar/name change) and
   *  refreshes just the sidebar footer + popup, without rebuilding the whole shell. */
  async refreshUser() {
    try {
      const session = await Api.get('/auth/session.php');
      this.currentUser = session;
      Api.setCsrfToken(session.csrf_token);
    } catch {
      return;
    }
    document.querySelectorAll('#sidebarFooter .sidebar-user-avatar').forEach(el => el.remove());
    const triggerAvatarSlot = document.getElementById('sidebarUserTrigger');
    const popupHeader = document.querySelector('.sidebar-user-popup-header');
    if (triggerAvatarSlot) triggerAvatarSlot.insertAdjacentHTML('afterbegin', avatarHtml(this.currentUser));
    if (popupHeader) popupHeader.insertAdjacentHTML('afterbegin', avatarHtml(this.currentUser, 'sidebar-user-avatar-lg'));
    document.querySelectorAll('.sidebar-user-name, .sidebar-user-popup-name').forEach(el => el.textContent = this.currentUser.full_name);
    document.querySelectorAll('.sidebar-user-role, .sidebar-user-popup-role').forEach(el => el.textContent = this.currentUser.role);
  },
 async refreshBranding() {
    try {
      const settings = await Api.get('/settings/index.php');
      if (settings.store_name) this.branding.store_name = settings.store_name;
      this.branding.store_logo = settings.store_logo || '';
    } catch {
      return;
    }
    const logoHtml = this.branding.store_logo
      ? `<img src="../backend/uploads/logo/${this.branding.store_logo}" alt="Logo" class="brand-logo">`
      : `<span class="brand-logo-placeholder">📚</span>`;
    const brandEl = document.querySelector('.sidebar-brand');
    if (brandEl) brandEl.innerHTML = `${logoHtml}<span>${escapeHtml(this.branding.store_name)}</span>`;
  },
  async loadNotificationBadge() {
    try {
      const data = await Api.get('/notifications/index.php');
      const unreadStored = (data.stored || []).filter(n => !n.is_read).length;
      const liveCount = (data.live || []).length;
      const total = unreadStored + liveCount;

      const badge = document.getElementById('notifBadge');
      if (!badge) return;
      if (total > 0) {
        badge.textContent = total > 99 ? '99+' : total;
        badge.style.display = 'inline-block';
      } else {
        badge.style.display = 'none';
      }
    } catch {
      // Non-fatal - badge just won't update this cycle
    }
  },

  loadFromHash() {
    const key = (location.hash || '#dashboard').slice(1);
    const item = this.nav.find(i => i.key === key) || (key === 'profile' ? { key: 'profile', label: 'My Profile', href: 'profile.html' } : null);
    const target = item || this.nav[0] || { key: 'dashboard', label: 'Dashboard', href: 'dashboard.html' };

    // Resolve explicitly against this document's own URL, so the iframe's src is
    // never ambiguous regardless of how the hash was set.
    const resolvedUrl = new URL(target.href, document.baseURI).href;
    document.getElementById('contentFrame').src = resolvedUrl;

    document.querySelectorAll('.sidebar-nav a, .sidebar-user-popup a.nav-link, .topbar-icons a').forEach(a => {
      a.classList.toggle('active', a.dataset.key === target.key);
    });

    const titleEl = document.getElementById('topbarTitle');
    if (titleEl) titleEl.textContent = target.label || '';

    this.closeUserPopup();
  },

  async logout() {
    try {
      await Api.post('/auth/logout.php');
    } catch { /* ignore */ }
    sessionStorage.clear();
    location.href = 'login.html';
  },

  hasRole(...roles) {
    return roles.includes(this.currentUser?.role);
  },
};
// Top-level `const` does not attach to `window` automatically - but pages inside
// the shell's iframe (e.g. profile.html) need to reach this instance via
// window.top.Layout, so expose it explicitly.
window.Layout = Layout;

/**
 * Used by every content page that loads INSIDE the shell's iframe. Only handles
 * auth/session/CSRF - the sidebar and topbar are already rendered by the parent
 * frame (Layout, above), so this never touches the DOM shell.
 */
const Embedded = {
  currentUser: null,

  async init(pageKey) {
    // If this page was opened directly (bookmark, typed URL, refresh outside the
    // shell) instead of inside app.html's iframe, send it into the shell instead
    // of showing bare content with no sidebar/topbar.
    if (window.self === window.top) {
      location.href = 'app.html' + (pageKey ? '#' + pageKey : '');
      return new Promise(() => {}); // never resolves - we're navigating away
    }

    try {
      const session = await Api.get('/auth/session.php');
      this.currentUser = session;
      Api.setCsrfToken(session.csrf_token);
      return session;
    } catch {
      // Session invalid - break the WHOLE app out of the iframe back to login,
      // not just this one frame.
      (window.top || window).location.href = 'login.html';
      return Promise.reject();
    }
  },

  hasRole(...roles) {
    return roles.includes(this.currentUser?.role);
  },
};

// ---------------- Simple modal helper ----------------
const Modal = {
  open(title, bodyHtml, footerHtml = '') {
    let overlay = document.getElementById('modalOverlay');
    if (!overlay) {
      overlay = document.createElement('div');
      overlay.id = 'modalOverlay';
      overlay.className = 'modal-overlay';
      document.body.appendChild(overlay);
    }
    overlay.innerHTML = `
      <div class="modal">
        <div class="modal-header">
          <h3>${title}</h3>
          <button class="modal-close" id="modalCloseBtn">&times;</button>
        </div>
        <div class="modal-body">${bodyHtml}</div>
        ${footerHtml ? `<div class="modal-footer">${footerHtml}</div>` : ''}
      </div>
    `;
    overlay.classList.add('open');
    document.getElementById('modalCloseBtn').addEventListener('click', () => Modal.close());
    overlay.addEventListener('click', (e) => { if (e.target === overlay) Modal.close(); });
  },
  close() {
    const overlay = document.getElementById('modalOverlay');
    if (overlay) overlay.classList.remove('open');
  },
};

function escapeHtml(str) {
  if (str === null || str === undefined) return '';
  return String(str)
    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
}

function confirmAction(message) {
  return window.confirm(message);
}