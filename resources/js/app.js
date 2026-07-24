import "./bootstrap.js";
import { registerBlatUI } from './blatui-core.js'
import { registerCharts } from './blatui-charts.js';

import { initApllyTheme } from "./modules/theme.js";
import { initShowToast } from "./modules/show-toast.js";

import playToastSound from "./modules/play-toast-sound.js";

document.addEventListener('alpine:init', () => {
    // Регистрируем магическое свойство $playSound для всего Alpine
    
     // <!-- Звук сыграет при инициализации компонента -->
    // <div x-data x-init="$playSound('success')"></div>

    // <!-- Или по клику -->
    // <button @click="$playSound('error')">Ошибка</button>
    window.Alpine.magic('playSound', () => {
        return (type) => playToastSound(type);
    });   

    registerBlatUI(window.Alpine)
    registerCharts(window.Alpine);
})

initShowToast();
initApllyTheme();

