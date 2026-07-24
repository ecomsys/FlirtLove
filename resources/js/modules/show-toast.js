import playToastSound from "./play-toast-sound";

export function initShowToast() {
    // --- Ловим уведомления от Livewire и показываем Sonner ---
    window.addEventListener("show-toast", (event) => {
        const { type, message } = event.detail;

        // Проверяем, доступна ли глобальная функция toast() из библиотеки Sonner
        if (typeof window.toast === "function") {
            playToastSound(type);

            if (type === "success") window.toast.success(message);
            else if (type === "error") window.toast.error(message);
            else if (type === "warning") window.toast.warning(message);
            else window.toast(message);
        } else {
            // Запасной вариант, если Sonner не подключен как глобальная функция
            console.warn(`Toast [${type}]: ${message}`);
            alert(message);
        }
    });
}
