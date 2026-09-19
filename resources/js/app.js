import "./bootstrap.js";

// 1. ИМПОРТИРУЕМ ALPINE.JS
import Alpine from "alpinejs";

// Твои импорты
import { registerBlatUI } from "./blatui-core.js";
import { registerCharts } from "./blatui-charts.js";
import { initShowToast } from "./modules/show-toast.js";
import { initApllyTheme } from "./modules/theme.js";
import { initScrollLockManager } from "./modules/scroll-lock-manager.js";
import playToastSound from "./modules/play-toast-sound.js";

// 2. ДЕЛАЕМ ALPINE ДОСТУПНЫМ ГЛОБАЛЬНО
// Livewire в админке увидит это и будет использовать этот экземпляр

// 3. РЕГИСТРИРУЕМ ПЛАГИНЫ
document.addEventListener("alpine:init", () => {
    window.Alpine.magic("playSound", () => {
        return (type) => playToastSound(type);
    });    

    registerBlatUI(window.Alpine);
    registerCharts(window.Alpine);
});

// 4. УМНЫЙ ЗАПУСК ALPINE
// Если переменная LIVEWIRE_ENABLED не равна true (т.е. мы на фронтенде),
// запускаем Alpine сами!
if (!window.LIVEWIRE_ENABLED) {
    window.Alpine = Alpine;
    Alpine.start();
}

// Инициализация других скриптов
initShowToast();
initApllyTheme();
initScrollLockManager();
