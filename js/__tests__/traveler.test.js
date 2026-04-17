/**
 * @jest-environment jsdom
 */

describe('traveler.js Traveler Dashboard', () => {
  let domReadyCallback;

  beforeEach(() => {
    document.body.innerHTML = `
      <div id="loginOverlay" class="active">
        <input type="password" id="travelerPassword" value="secret" />
        <button id="loginBtn">Login</button>
        <div id="loginError"></div>
      </div>
      <div id="travelerDashboard" style="display:none;">
        <input type="checkbox" id="modeToggle" />
        <span id="modeStatusMsg"></span>
        <input type="file" id="photoInput" />
        <button id="uploadBtn" disabled>Upload</button>
        <div id="uploadFeedback"></div>
      </div>
    `;

    global.fetch = jest.fn();

    // Intercept addEventListener to avoid accumulating listeners
    jest.spyOn(document, 'addEventListener').mockImplementation((event, cb) => {
      if (event === 'DOMContentLoaded') domReadyCallback = cb;
    });

    jest.resetModules();
  });

  afterEach(() => {
    jest.restoreAllMocks();
  });

  it('initializes dashboard and toggle state on valid auth', async () => {
    global.fetch.mockResolvedValueOnce({
      json: async () => ({
        authenticated: true,
        role: 'traveler',
        onShipMode: true,
      }),
    });

    require('../traveler.js');
    domReadyCallback();
    await new Promise((r) => setTimeout(r, 0));

    expect(global.fetch).toHaveBeenCalledWith('api.php?action=status');
    expect(document.getElementById('loginOverlay').classList.contains('active')).toBe(false);
    expect(document.getElementById('travelerDashboard').style.display).toBe('block');
    expect(document.getElementById('modeToggle').checked).toBe(true);
    expect(document.getElementById('modeStatusMsg').textContent).toBe('Currently tracking via Datadocked AIS.');
  });

  it('handles tracking mode toggle change', async () => {
    global.fetch
        .mockResolvedValueOnce({
          json: async () => ({
            authenticated: true,
            role: 'traveler',
            onShipMode: true,
          }),
        })
        .mockResolvedValueOnce({
          json: async () => ({success: true}),
        });

    require('../traveler.js');
    domReadyCallback();
    await new Promise((r) => setTimeout(r, 0));

    const toggle = document.getElementById('modeToggle');
    // Simulate user unchecking
    toggle.checked = false;
    toggle.dispatchEvent(new Event('change'));

    await new Promise((r) => setTimeout(r, 0));

    expect(global.fetch).toHaveBeenCalledTimes(2);
    // the second call should be api.php with POST FormData (mode=0)
    const callArgs = global.fetch.mock.calls[1];
    expect(callArgs[0]).toBe('api.php');
    expect(callArgs[1].method).toBe('POST');
    // modeStatusMsg should update
    expect(document.getElementById('modeStatusMsg').textContent).toBe('Datadocked polling DISABLED. Photo locations only.');
  });
});
