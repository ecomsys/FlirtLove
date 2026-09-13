{{--
    Минимальный анти-FOUC стаб. Единственная задача — повесить класс `dark`
    до первой отрисовки. Вся логика — в resources/js/modules/theme.js.
    ОБЯЗАН оставаться инлайн в <head> (до @vite) — внешний файл здесь
    даст задержку и вспышку светлой темы.
--}}
<script>
(function () {
    var root = document.documentElement;
    var theme = root.getAttribute('data-app-theme');

    if (root.getAttribute('data-auth') !== '1') {
        // Гость без cookie, но со старым localStorage — уважаем его выбор
        var local = localStorage.getItem('theme:mode');
        if (local === 'dark' || local === 'light') {
            theme = local;
        }
    }

    root.classList.toggle('dark', theme === 'dark');
})();
</script>