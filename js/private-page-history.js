// Force a fresh server request when a protected page is restored from browser history.
window.addEventListener("pageshow", function (event) {
  if (event.persisted) {
    window.location.reload();
  }
});
