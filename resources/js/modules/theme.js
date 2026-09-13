// ============================================================
// Тема приложения.
//
// Разделение обязанностей:
//  1) partials/theme-bootstrap.blade.php — инлайн-стаб в <head>,
//     вешает класс `dark` ДО первой отрисовки (анти-FOUC).
//  2) Этот модуль — вся логика: чтение источников, синхронизация
//     localStorage + cookie, тумблер, восстановление после SPA-переходов.
//
// Стаб обязан оставаться инлайн: module-скрипты из бандла выполняются
// с задержкой (defer), браузер успевает отрисовать светлую тему.
// ============================================================

const STORAGE_KEY = 'theme:mode';
const COOKIE_NAME = 'theme';
const VALID = ['light', 'dark'];

function syncStorage(theme) {
    // localStorage — его читает blatui-core.js
    localStorage.setItem(STORAGE_KEY, theme);

    // cookie — по ней сервер рендерит тему и мигрирует её в БД при логине
    document.cookie =
        COOKIE_NAME + '=' + theme +
        ';path=/;max-age=31536000;samesite=lax' +
        (location.protocol === 'https:' ? ';secure' : '');
}

export function applyAppTheme() {
    const root = document.documentElement;
    const isAuth = root.getAttribute('data-auth') === '1';
    let theme = root.getAttribute('data-app-theme');

    if (!VALID.includes(theme)) {
        theme = 'light';
    }

    if (!isAuth) {
        // Гость: источник правды — его браузер
        const local = localStorage.getItem(STORAGE_KEY);
        if (VALID.includes(local)) {
            theme = local;
        }
    }

    root.classList.toggle('dark', theme === 'dark');
    syncStorage(theme);
}

export function toggleAppTheme() {
    const root = document.documentElement;
    root.classList.toggle('dark');
    const theme = root.classList.contains('dark') ? 'dark' : 'light';
    root.setAttribute('data-app-theme', theme);
    syncStorage(theme);
}

// Кнопки в разметке зовут window.* — биндим
window.applyAppTheme = applyAppTheme;
window.toggleAppTheme = toggleAppTheme;

export function initApllyTheme() {
    // Дублирует стаб из <head> — идемпотентно. Но именно здесь
    // проставится cookie для гостя на самом первом визите (стаб её не пишет).
    applyAppTheme();

    // После SPA-переходов Livewire обновляет атрибуты <html> с сервера —
    // перечитываем и возвращаем класс, если кто-то (blatui) его снёс.
    document.addEventListener('livewire:navigated', applyAppTheme);
}