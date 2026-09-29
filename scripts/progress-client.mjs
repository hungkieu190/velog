const baseTitle = 'VeLog · Project progress';
const seenKey = 'velog-progress-seen-handoff';
const unreadKey = 'velog-progress-unread-handoff';

export function completionEvent(focus) {
  if (!focus?.id || !focus.latestPrompt) return null;
  const status = focus.latestPrompt.match(/^Status: ([A-Z_]+)$/m)?.[1];
  const recipient = focus.latestPrompt.match(/^Recipient: (Architect|Builder|User)$/m)?.[1];
  if (!status || status !== focus.status || !recipient) return null;
  const actor = recipient === 'Architect' ? 'Builder' : 'Architect';
  return {
    key: JSON.stringify([focus.id, status, focus.latestPrompt]),
    text: `${actor} finished ${focus.id}. ${recipient} is next.`,
    actor,
  };
}

if (typeof document !== 'undefined') {
  const alert = document.getElementById('handoff-alert');
  const alertText = document.getElementById('handoff-alert-text');
  const soundButton = document.getElementById('enable-sound');
  const dismissButton = document.getElementById('dismiss-alert');
  const icon = document.createElement('link');
  icon.rel = 'icon';
  document.head.append(icon);

  let audioContext = null;
  let soundEnabled = false;
  let polling = false;
  let lastUpdated = null;
  let lastEventKey = sessionStorage.getItem(seenKey);
  let unread = sessionStorage.getItem(unreadKey);

  function showAlert(message) {
    unread = message;
    sessionStorage.setItem(unreadKey, message);
    alertText.textContent = message;
    alert.hidden = false;
    document.title = `🔔 ${message} · ${baseTitle}`;
    const svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><rect width="64" height="64" rx="14" fill="#b64b23"/><circle cx="32" cy="32" r="15" fill="white"/></svg>';
    icon.href = `data:image/svg+xml,${encodeURIComponent(svg)}`;
  }

  function dismissAlert() {
    unread = null;
    sessionStorage.removeItem(unreadKey);
    alert.hidden = true;
    document.title = baseTitle;
    icon.removeAttribute('href');
  }

  function playAlert() {
    if (!soundEnabled || !audioContext) return;
    const start = audioContext.currentTime;
    for (const [index, frequency] of [880, 1175].entries()) {
      const oscillator = audioContext.createOscillator();
      const gain = audioContext.createGain();
      const noteStart = start + index * 0.22;
      oscillator.frequency.value = frequency;
      oscillator.type = 'sine';
      gain.gain.setValueAtTime(0.0001, noteStart);
      gain.gain.exponentialRampToValueAtTime(0.13, noteStart + 0.02);
      gain.gain.exponentialRampToValueAtTime(0.0001, noteStart + 0.18);
      oscillator.connect(gain).connect(audioContext.destination);
      oscillator.start(noteStart);
      oscillator.stop(noteStart + 0.19);
    }
  }

  async function refreshDisplay() {
    const response = await fetch('/', { cache: 'no-store' });
    if (!response.ok) throw new Error(`Dashboard refresh failed: ${response.status}`);
    const updated = new DOMParser().parseFromString(await response.text(), 'text/html');
    document.querySelector('main').replaceWith(updated.querySelector('main'));
    document.querySelector('footer').replaceWith(updated.querySelector('footer'));
  }

  async function poll() {
    if (polling) return;
    polling = true;
    try {
      const response = await fetch('/api/progress', { cache: 'no-store' });
      if (!response.ok) throw new Error(`Progress request failed: ${response.status}`);
      const data = await response.json();
      const event = completionEvent(data.currentFocus);
      if (event) {
        if (lastEventKey && event.key !== lastEventKey) {
          showAlert(event.text);
          playAlert();
        }
        lastEventKey = event.key;
        sessionStorage.setItem(seenKey, event.key);
      }
      if (data.lastUpdated !== lastUpdated) {
        await refreshDisplay();
        lastUpdated = data.lastUpdated;
      }
    } catch (error) {
      // A transient read failure must not mark a handoff as seen.
      soundButton.title = `Progress check failed: ${error.message}`;
    } finally {
      polling = false;
    }
  }

  soundButton.addEventListener('click', async () => {
    if (soundEnabled) {
      soundEnabled = false;
      soundButton.textContent = 'Enable sound';
      return;
    }
    try {
      audioContext ??= new window.AudioContext();
      await audioContext.resume();
      soundEnabled = audioContext.state === 'running';
      soundButton.textContent = soundEnabled ? 'Sound on · Mute' : 'Sound blocked';
      soundButton.title = soundEnabled ? 'This tab will play a chime after a new handoff.' : 'Allow audio playback for this tab.';
    } catch {
      soundButton.textContent = 'Sound blocked';
      soundButton.title = 'Allow audio playback for this tab.';
    }
  });
  dismissButton.addEventListener('click', dismissAlert);
  if (unread) showAlert(unread);
  void poll();
  window.setInterval(poll, 20_000);
  document.addEventListener('visibilitychange', () => {
    if (!document.hidden) void poll();
  });
}
