export function initShowPageLoader() {
    // Функция показа спиннера
    window.showPageLoader = function () {
        const loader = document.getElementById("global-page-loader");
        if (loader) {
            loader.classList.remove("hidden");
            loader.classList.add("flex");
        }
    };

    // Защита от BFCache: если юзер нажал "Назад", принудительно прячем спиннер
    window.addEventListener("pageshow", function (event) {
        const loader = document.getElementById("global-page-loader");
        if (loader) {
            loader.classList.add("hidden");
            loader.classList.remove("flex");
        }
    });
}
