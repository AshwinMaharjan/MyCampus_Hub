document.addEventListener("DOMContentLoaded", () => {
    const profileIcon = document.getElementById("profileIcon");
    const dropdownMenu = document.getElementById("dropdownMenu");
  
    if (profileIcon && dropdownMenu) {
      profileIcon.addEventListener("click", () => {
        dropdownMenu.style.display =
          dropdownMenu.style.display === "block" ? "none" : "block";
      });
  
      window.addEventListener("click", (e) => {
        if (!profileIcon.contains(e.target) && !dropdownMenu.contains(e.target)) {
          dropdownMenu.style.display = "none";
        }
      });
    }
  });
  