export function initApllyTheme() {
    
    function applyGuestTheme() {
        // Если юзер авторизован, сервер уже всё отрисовал, выходим
        const isAuth = document.documentElement.getAttribute('data-auth') === '1';
        if (isAuth) return;

        // Для гостей читаем localStorage
        const localTheme = localStorage.getItem('theme:mode') || 'light';
        if (localTheme === 'dark') {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    }

    // 1. Применяем тему при первой загрузке страницы (на всякий случай)
    applyGuestTheme();

    // 2. ПРИНУДИТЕЛЬНО применяем тему после каждой SPA-навигации Livewire
    // (Livewire сбрасывает классы на <html> при переходе, возвращаем их на место)
    document.addEventListener("livewire:navigated", applyGuestTheme);
}