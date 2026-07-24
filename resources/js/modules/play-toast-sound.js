// --- Функция генерации звука (Web Audio API) ---
export default function playToastSound(type) {
    try {
        const audioContext = new (window.AudioContext || window.webkitAudioContext)();
        const oscillator = audioContext.createOscillator();
        const gainNode = audioContext.createGain();

        oscillator.connect(gainNode);
        gainNode.connect(audioContext.destination);

        // Настройки звука в зависимости от типа уведомления
        if (type === 'success') {
            oscillator.type = 'sine';
            oscillator.frequency.setValueAtTime(880, audioContext.currentTime); // Приятный высокий тон (нота Ля)
        } else if (type === 'error') {
            oscillator.type = 'sawtooth';
            oscillator.frequency.setValueAtTime(150, audioContext.currentTime); // Низкий грубый тон
        } else { // warning / info
            oscillator.type = 'triangle';
            oscillator.frequency.setValueAtTime(440, audioContext.currentTime); // Средний тон (нота Ля 4-й октавы)
        }

        // Плавное затухание звука (чтобы не было резкого щелчка)
        gainNode.gain.setValueAtTime(0.1, audioContext.currentTime); // Громкость 10%
        gainNode.gain.exponentialRampToValueAtTime(0.0001, audioContext.currentTime + 0.3); // Длительность 0.3 сек

        oscillator.start(audioContext.currentTime);
        oscillator.stop(audioContext.currentTime + 0.3);
    } catch (e) {
        console.warn('Audio playback failed:', e);
    }
}
