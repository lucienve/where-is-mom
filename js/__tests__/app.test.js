/**
 * @jest-environment jsdom
 */

describe('app.js Viewer Dashboard', () => {
  let domReadyCallback;

  beforeEach(() => {
    // Set up our document body
    document.body.innerHTML = `
      <div id="loginOverlay" class="active">
        <input type="password" id="viewerPassword" value="secret" />
        <button id="loginBtn">Login</button>
        <div id="loginError"></div>
      </div>
      <div id="lightbox">
        <button id="closeLightbox">Close</button>
      </div>
    `;

    // Mock global fetch
    global.fetch = jest.fn();
    global.initMapWithKey = jest.fn();

    // Intercept addEventListener to avoid accumulating listeners across tests
    jest.spyOn(document, 'addEventListener').mockImplementation((event, cb) => {
      if (event === 'DOMContentLoaded') domReadyCallback = cb;
    });

    // Isolate module execution
    jest.resetModules();
  });

  afterEach(() => {
    jest.restoreAllMocks();
  });

  it('removes loginOverlay and initializes map if authenticated on load', async () => {
    global.fetch.mockResolvedValueOnce({
      json: async () => ({authenticated: true, role: 'viewer', mapsApiKey: 'test_key'}),
    });

    require('../app.js');

    // Trigger DOMContentLoaded manually
    domReadyCallback();

    // Allow promises to resolve
    await new Promise((r) => setTimeout(r, 0));

    expect(global.fetch).toHaveBeenCalledWith('api.php?action=status');
    const overlay = document.getElementById('loginOverlay');
    expect(overlay.classList.contains('active')).toBe(false);
    expect(global.initMapWithKey).toHaveBeenCalledWith('test_key');
  });

  it('displays error message on failed login', async () => {
    global.fetch
        .mockResolvedValueOnce({
          json: async () => ({authenticated: false}),
        }) // initial status check
        .mockResolvedValueOnce({
          json: async () => ({success: false, message: 'Invalid test password'}),
        }); // login attempt

    require('../app.js');

    domReadyCallback();
    await new Promise((r) => setTimeout(r, 0));

    // Click login
    const btn = document.getElementById('loginBtn');
    btn.click();

    await new Promise((r) => setTimeout(r, 0));

    expect(global.fetch).toHaveBeenCalledTimes(2);
    expect(document.getElementById('loginError').textContent).toBe('Invalid test password');
  });
});
