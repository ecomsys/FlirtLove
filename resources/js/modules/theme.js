export function initApllyTheme() {
    // --- Управление темой ---
    function applyTheme() {
        const isDark =
            localStorage.getItem("theme") === "dark" ||
            (!("theme" in localStorage) &&
                window.matchMedia("(prefers-color-scheme: dark)").matches);

        document.documentElement.classList.toggle("dark", isDark);
    }

    // 1. Применяем тему при первой загрузке страницы
    applyTheme();

    // 2. ПРИНУДИТЕЛЬНО применяем тему после каждой SPA-навигации Livewire
    document.addEventListener("livewire:navigated", () => {
        applyTheme();
    });

    // 3. Делаем функцию доступной глобально для нашей кнопки
    window.toggleTheme = () => {
        const isDark = !document.documentElement.classList.contains("dark");
        document.documentElement.classList.toggle("dark", isDark);
        localStorage.setItem("theme", isDark ? "dark" : "light");
    };
}
