/**
 * Central API client. All requests go through here so CSRF tokens,
 * credentials, and error handling are consistent everywhere.
 */

// Backend base URL. Works out of the box when frontend+backend are served
// from the same project root (e.g. XAMPP htdocs/bookstore-management-system/).
// Change this ONLY if you host backend on a different domain.
const API_BASE = '/bookstore-management-system/backend/api';

const Api = {
  csrfToken: sessionStorage.getItem('csrf_token') || '',

  setCsrfToken(token) {
    this.csrfToken = token;
    sessionStorage.setItem('csrf_token', token);
  },

  async request(path, { method = 'GET', body = null, isForm = false } = {}) {
    const headers = {};
    if (!isForm) headers['Content-Type'] = 'application/json';
    if (method !== 'GET') headers['X-CSRF-Token'] = this.csrfToken;

    const options = {
      method,
      headers,
      credentials: 'include', // send the PHP session cookie
    };
    if (body) options.body = isForm ? body : JSON.stringify(body);

    let response;
    try {
      response = await fetch(`${API_BASE}${path}`, options);
    } catch (err) {
      Toast.error('Network error. Please check your connection.');
      throw err;
    }

    let json;
    try {
      json = await response.json();
    } catch {
      Toast.error('Unexpected server response.');
      throw new Error('Invalid JSON response');
    }

    if (response.status === 401) {
      // Session expired / not logged in
      sessionStorage.clear();
      if (!location.pathname.endsWith('login.html')) {
        location.href = 'login.html';
      }
      throw new Error(json.message || 'Unauthorized');
    }

    if (!json.success) {
      Toast.error(json.message || 'Something went wrong.');
      throw new Error(json.message || 'Request failed');
    }

    return json.data;
  },

  get(path) { return this.request(path); },
  post(path, body) { return this.request(path, { method: 'POST', body }); },
  put(path, body) { return this.request(path, { method: 'PUT', body }); },
  del(path) { return this.request(path, { method: 'DELETE' }); },
  upload(path, formData) { return this.request(path, { method: 'POST', body: formData, isForm: true }); },
};
