(function () {
  var root = document.querySelector('[data-iro-partners-root]');
  if (!root) {
    return;
  }
  if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    root.classList.add('iro-home-partners--visible');
    return;
  }
  function reveal() {
    root.classList.add('iro-home-partners--visible');
  }
  if (!('IntersectionObserver' in window)) {
    reveal();
    return;
  }
  var io = new IntersectionObserver(
    function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          reveal();
          io.disconnect();
        }
      });
    },
    { root: null, rootMargin: '0px 0px -8% 0px', threshold: 0.08 }
  );
  io.observe(root);
})();
