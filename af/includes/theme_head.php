<?php
declare(strict_types=1);
?>
<link rel="icon" href="favicon.ico" sizes="any">
<link rel="icon" href="favicon.svg" type="image/svg+xml">
<script>
(function () {
  try {
    var KEY = "audit-fund-theme";
    var pref = localStorage.getItem(KEY);
    if (pref !== "light" && pref !== "dark" && pref !== "auto") {
      pref = "auto";
    }
    var dark =
      pref === "dark" ||
      (pref === "auto" &&
        window.matchMedia &&
        window.matchMedia("(prefers-color-scheme: dark)").matches);
    document.documentElement.setAttribute("data-theme", dark ? "dark" : "light");
    document.documentElement.setAttribute("data-theme-pref", pref);
  } catch (e) {}
})();
</script>
