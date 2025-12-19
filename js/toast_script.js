function showToast(message, status = 'info') {
  const toast = document.getElementById("toast");
  toast.textContent = message;
  toast.className = 'show ' + status;
  setTimeout(() => {
      toast.classList.remove("show", status);
  }, 6000);
}

window.addEventListener("DOMContentLoaded", () => {
  if (typeof toastMessage !== "undefined" && toastMessage.trim() !== "") {
      const status = typeof toastStatus !== "undefined" ? toastStatus : 'info';
      showToast(toastMessage, status);
  }
});