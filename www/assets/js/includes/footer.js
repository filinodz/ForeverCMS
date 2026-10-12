const backToTop = document.getElementById("backToTop");
const siteFooter = document.querySelector("footer.wow-footer");
let footerIsVisible = false;

const updateBackToTop = () => {
  const shouldShow = window.scrollY > 300 && !footerIsVisible;
  backToTop.style.opacity = shouldShow ? "1" : "0";
  backToTop.style.pointerEvents = shouldShow ? "auto" : "none";
  backToTop.style.transform = shouldShow ? "translateY(0)" : "translateY(20px)";
};

window.addEventListener("scroll", updateBackToTop, { passive: true });

if (siteFooter && "IntersectionObserver" in window) {
  const footerObserver = new IntersectionObserver((entries) => {
    footerIsVisible = entries.some((entry) => entry.isIntersecting);
    updateBackToTop();
  }, { threshold: 0.08 });
  footerObserver.observe(siteFooter);
}

updateBackToTop();

backToTop.addEventListener("click", () => {
    backToTop.style.transform = "scale(0.9)";
    setTimeout(() => {
      backToTop.style.transform = "scale(1)";
    }, 100);
    window.scrollTo({ top: 0, behavior: "smooth" });
});
