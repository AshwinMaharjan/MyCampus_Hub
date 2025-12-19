document.addEventListener("DOMContentLoaded", function () {
    const form = document.querySelector("form");
  
    const id        = form.querySelector("input[name='id_number']");
    const name      = form.querySelector("input[name='full_name']");
    const email     = form.querySelector("input[name='email']");
    const pass      = form.querySelector("input[name='password']");
    const gender    = form.querySelector("select[name='gender']");
    const dob       = form.querySelector("input[name='date_of_birth']");
    const phone     = form.querySelector("input[name='contact_number']");
    const address   = form.querySelector("input[name='address']");
    const statusSel = form.querySelector("select[name='status']");
    const photo     = form.querySelector("input[name='profile_photo']");
  
    const courseBoxes   = document.querySelectorAll("input[name='course_name[]']");
    const semesterBoxes = document.querySelectorAll("input[name='sem_name[]']");
    const courseError   = document.getElementById("course-error");
    const semError      = document.getElementById("semester-error");
  
    function showError(input, msg) {
      let err = input.nextElementSibling;
      if (!err || !err.classList.contains("staff-error")) {
        err = document.createElement("div");
        err.className = "staff-error";
        input.parentNode.insertBefore(err, input.nextSibling);
      }
      err.textContent = msg;
    }
  
    function clearError(input) {
      let err = input.nextElementSibling;
      if (err && err.classList.contains("staff-error")) err.remove();
    }
  
    name.addEventListener("input", () => {
      /^[A-Za-z\s]+$/.test(name.value)
        ? clearError(name)
        : showError(name, "Only letters allowed");
    });
  
    phone.addEventListener("input", () => {
      /^\d{10}$/.test(phone.value)
        ? clearError(phone)
        : showError(phone, "Enter 10‑digit number");
    });
  
    email.addEventListener("input", () => {
      /^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(email.value)
        ? clearError(email)
        : showError(email, "Invalid email");
    });
  
    dob.addEventListener("change", () => {
      const today = new Date();
      const selected = new Date(dob.value);
      selected > today
        ? showError(dob, "Date can't be in future")
        : clearError(dob);
    });
  
    function checkGroup(boxes, errorElem, msg) {
      const checked = Array.from(boxes).some(cb => cb.checked);
      errorElem.textContent = checked ? "" : msg;
      return checked;
    }
  
    dob.max = new Date().toISOString().split("T")[0];
  
  });
  