(() => {
  const password = document.getElementById("password");
  const toggle = document.getElementById("togglePassword");
  if (!password || !toggle) return;

  toggle.addEventListener("click", () => {
    const showPassword = password.type === "password";
    password.type = showPassword ? "text" : "password";
    toggle.classList.toggle("is-password-visible", showPassword);
    const label = showPassword ? "Hide password" : "Show password";
    toggle.setAttribute("aria-label", label);
    toggle.title = label;
  });
  toggle.hidden = false;
})();
