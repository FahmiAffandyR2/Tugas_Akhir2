/**
 * Alert Sound Utility
 * Generates alert sounds using Web Audio API
 */

let audioContext = null;

/**
 * Get or create AudioContext
 */
function getAudioContext() {
  if (!audioContext) {
    audioContext = new (window.AudioContext || window.webkitAudioContext)();
  }
  return audioContext;
}

/**
 * Play a short alert beep sound
 * Uses Web Audio API - no external files needed
 */
export function playAlertSound() {
  try {
    const ctx = getAudioContext();

    // First beep
    const osc1 = ctx.createOscillator();
    const gain1 = ctx.createGain();
    osc1.connect(gain1);
    gain1.connect(ctx.destination);
    osc1.frequency.value = 880;
    osc1.type = 'sine';
    gain1.gain.value = 0.3;
    osc1.start(ctx.currentTime);
    gain1.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.1);
    osc1.stop(ctx.currentTime + 0.1);

    // Second beep (higher pitch)
    const osc2 = ctx.createOscillator();
    const gain2 = ctx.createGain();
    osc2.connect(gain2);
    gain2.connect(ctx.destination);
    osc2.frequency.value = 1100;
    osc2.type = 'sine';
    gain2.gain.value = 0.3;
    osc2.start(ctx.currentTime + 0.15);
    gain2.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.25);
    osc2.stop(ctx.currentTime + 0.25);

    // Third beep (back to first pitch)
    const osc3 = ctx.createOscillator();
    const gain3 = ctx.createGain();
    osc3.connect(gain3);
    gain3.connect(ctx.destination);
    osc3.frequency.value = 880;
    osc3.type = 'sine';
    gain3.gain.value = 0.3;
    osc3.start(ctx.currentTime + 0.3);
    gain3.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.4);
    osc3.stop(ctx.currentTime + 0.4);

    console.log('[AlertSound] Alert sound played');
  } catch (error) {
    console.warn('[AlertSound] Failed to play alert sound:', error);
  }
}

/**
 * Play a warning sound (for out of route, speed exceeded)
 */
export function playWarningSound() {
  try {
    const ctx = getAudioContext();

    // Low warning tone
    const osc = ctx.createOscillator();
    const gain = ctx.createGain();
    osc.connect(gain);
    gain.connect(ctx.destination);
    osc.frequency.value = 440;
    osc.type = 'square';
    gain.gain.value = 0.2;

    osc.start(ctx.currentTime);
    gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.3);
    osc.stop(ctx.currentTime + 0.3);

    // Pause
    const osc2 = ctx.createOscillator();
    const gain2 = ctx.createGain();
    osc2.connect(gain2);
    gain2.connect(ctx.destination);
    osc2.frequency.value = 520;
    osc2.type = 'square';
    gain2.gain.value = 0.2;

    osc2.start(ctx.currentTime + 0.4);
    gain2.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.7);
    osc2.stop(ctx.currentTime + 0.7);

    console.log('[AlertSound] Warning sound played');
  } catch (error) {
    console.warn('[AlertSound] Failed to play warning sound:', error);
  }
}

/**
 * Play a critical sound (for GPS offline)
 */
export function playCriticalSound() {
  try {
    const ctx = getAudioContext();

    for (let i = 0; i < 3; i++) {
      const osc = ctx.createOscillator();
      const gain = ctx.createGain();
      osc.connect(gain);
      gain.connect(ctx.destination);
      osc.frequency.value = 1000;
      osc.type = 'sine';
      gain.gain.value = 0.3;

      osc.start(ctx.currentTime + (i * 0.2));
      gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + (i * 0.2) + 0.1);
      osc.stop(ctx.currentTime + (i * 0.2) + 0.1);
    }

    console.log('[AlertSound] Critical sound played');
  } catch (error) {
    console.warn('[AlertSound] Failed to play critical sound:', error);
  }
}

/**
 * Play a success sound (for arrived at depot)
 */
export function playSuccessSound() {
  try {
    const ctx = getAudioContext();

    // Ascending two-tone chime
    const osc1 = ctx.createOscillator();
    const gain1 = ctx.createGain();
    osc1.connect(gain1);
    gain1.connect(ctx.destination);
    osc1.frequency.value = 523;
    osc1.type = 'sine';
    gain1.gain.value = 0.3;
    osc1.start(ctx.currentTime);
    gain1.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.15);
    osc1.stop(ctx.currentTime + 0.15);

    const osc2 = ctx.createOscillator();
    const gain2 = ctx.createGain();
    osc2.connect(gain2);
    gain2.connect(ctx.destination);
    osc2.frequency.value = 659;
    osc2.type = 'sine';
    gain2.gain.value = 0.3;
    osc2.start(ctx.currentTime + 0.2);
    gain2.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.4);
    osc2.stop(ctx.currentTime + 0.4);

    console.log('[AlertSound] Success sound played');
  } catch (error) {
    console.warn('[AlertSound] Failed to play success sound:', error);
  }
}
