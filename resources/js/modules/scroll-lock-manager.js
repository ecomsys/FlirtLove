export function initScrollLockManager() {
    window.modalScrollManager = {
        count: 0,

        lock() {
            if (this.count === 0) {
                const scrollbarWidth =
                    window.innerWidth - document.documentElement.clientWidth;
                document.documentElement.style.overflow = "hidden";
                if (scrollbarWidth > 0) {
                    document.documentElement.style.paddingRight =
                        scrollbarWidth + "px";
                }
            }
            this.count++;
        },

        unlock(delay = 160) {
            // Откладываем уменьшение счетчика.
            // Если за 160мс откроется новая модалка, счетчик просто не дойдет до 0.
            setTimeout(() => {
                this.count = Math.max(0, this.count - 1);
                if (this.count === 0) {
                    document.documentElement.style.overflow = "";
                    document.documentElement.style.paddingRight = "";
                }
            }, delay);
        },
    };
}
