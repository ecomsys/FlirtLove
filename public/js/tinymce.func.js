const initTinyMce = () =>  {
    return {
        sidebarOpen: true,
        themeObserver: null,
        currentTheme: "light",
        isEditorLoaded: false,
        textareaElement: null,
        typingTimer: null,

        init() {
            // ФИКС: Небольшая задержка (150мс), чтобы DOM от Livewire успел полностью "осесть" после wire:navigate
            setTimeout(() => {
                this.$nextTick(() => {
                    this.textareaElement =
                        document.getElementById("tinyMceBody");
                    if (this.textareaElement) {
                        this.waitForTinyMCE();
                    }
                    this.setupThemeWatcher();
                });
            }, 150);
        },

        waitForTinyMCE() {
            if (typeof tinymce !== "undefined" && this.textareaElement) {
                this.initTinyMCE();
            } else {
                setTimeout(() => this.waitForTinyMCE(), 100);
            }
        },

        getTheme() {
            return document.documentElement.classList.contains("dark")
                ? "dark"
                : "light";
        },

        getCssVar(name) {
            return getComputedStyle(document.documentElement)
                .getPropertyValue(name)
                .trim();
        },

        initTinyMCE() {
            if (typeof tinymce === "undefined" || !this.textareaElement) return;

            // ФИКС: Жестко убиваем старые инстансы, если они зависли, чтобы не было гонок
            if (tinymce.get("tinyMceBody")) {
                tinymce.get("tinyMceBody").remove();
            }

            this.currentTheme = this.getTheme();
            const isDark = this.currentTheme === "dark";

            const bgColor = this.getCssVar("--background");
            const textColor = this.getCssVar("--foreground");
            const borderColor = this.getCssVar("--border");
            const mutedColor = this.getCssVar("--muted-foreground");
            const mutedBgColor = this.getCssVar("--muted");

            // Достаем настройки из внешнего файла
            const config = window.getTinyMceConfig(
                isDark,
                textColor,
                bgColor,
                borderColor,
                mutedColor,
                mutedBgColor,
            );

            // Добавляем селектор и коллбэки
            config.selector = "#tinyMceBody";
            config.setup = (editor) => {
                editor.on("init", () => {
                    this.isEditorLoaded = true;
                });

                editor.on("input change keyup undo redo SetContent", () => {
                    clearTimeout(this.typingTimer);
                    this.typingTimer = setTimeout(() => {
                        // ФИКС: Напрямую пушим в Livewire, минуя баги с textarea
                        this.$wire.set("body", editor.getContent());
                    }, 500);
                });
            };

            tinymce.init(config);
        },

        destroyTinyMCE() {
            if (typeof tinymce !== "undefined" && tinymce.get("tinyMceBody")) {
                this.isEditorLoaded = false;
                clearTimeout(this.typingTimer);
                tinymce.get("tinyMceBody").remove();
            }
        },

        setupThemeWatcher() {
            this.themeObserver = new MutationObserver((mutations) => {
                mutations.forEach((mutation) => {
                    if (mutation.attributeName === "class") {
                        const newTheme = this.getTheme();
                        if (newTheme !== this.currentTheme) {
                            this.destroyTinyMCE();
                            setTimeout(() => this.initTinyMCE(), 50);
                        }
                    }
                });
            });
            this.themeObserver.observe(document.documentElement, {
                attributes: true,
            });
        },

        destroy() {
            if (this.themeObserver) {
                this.themeObserver.disconnect();
                this.themeObserver = null;
            }
            this.destroyTinyMCE();
        },
    };
}
