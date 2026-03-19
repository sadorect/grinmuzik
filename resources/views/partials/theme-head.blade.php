<meta name="color-scheme" content="light dark">
<script>
    (() => {
        const storageKey = "grinmuzik-theme";
        const storedTheme = window.localStorage.getItem(storageKey);
        const prefersDark = window.matchMedia("(prefers-color-scheme: dark)").matches;
        const isDark = storedTheme ? storedTheme === "dark" : prefersDark;

        document.documentElement.classList.toggle("dark", isDark);
        document.documentElement.dataset.theme = isDark ? "dark" : "light";
        document.documentElement.style.colorScheme = isDark ? "dark" : "light";
    })();
</script>
