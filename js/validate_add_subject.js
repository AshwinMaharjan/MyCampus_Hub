function isValidSubjectName(name) {
  return /^[A-Za-z\s()]+$/.test(name);
}

function isValidFullMarks(marks) {
  const num = Number(marks);
  return /^[0-9]+$/.test(marks) && num >= 1 && num <= 100;
}

document.addEventListener("DOMContentLoaded", function () {
  const form = document.querySelector("form");
  const subjectInput = form.querySelector("input[name='subject_name']");
  const marksInput = form.querySelector("input[name='full_marks']");
  const staffSelect = form.querySelector("select[name='staff_id']");
  const semSelect = form.querySelector("select[name='sem_id']");
  const courseSelect = form.querySelector("select[name='course_id']");

  subjectInput.addEventListener("input", () => {
    const value = subjectInput.value.trim();
    const error = document.getElementById("subject_error");

    if (!isValidSubjectName(value)) {
      error.textContent = "Only letters and spaces allowed.";
      subjectInput.classList.add("error-border");
    } else {
      error.textContent = "";
      subjectInput.classList.remove("error-border");
    }
  });

  marksInput.addEventListener("input", () => {
    const value = marksInput.value.trim();
    const error = document.getElementById("marks_error");

    if (!isValidFullMarks(value)) {
      error.textContent = "Enter a number between 1 and 100.";
      marksInput.classList.add("error-border");
    } else {
      error.textContent = "";
      marksInput.classList.remove("error-border");
    }
  });

  function validateSelect(selectElement, errorId) {
    const error = document.getElementById(errorId);
    if (!selectElement.value) {
      error.textContent = "Please make a selection.";
      selectElement.classList.add("error-border");
      return false;
    } else {
      error.textContent = "";
      selectElement.classList.remove("error-border");
      return true;
    }
  }

  form.addEventListener("submit", function (e) {
    const isSubjectValid = isValidSubjectName(subjectInput.value.trim());
    const isMarksValid = isValidFullMarks(marksInput.value.trim());
    const isStaffValid = validateSelect(staffSelect, "staff_error");
    const isSemValid = validateSelect(semSelect, "sem_error");
    const isCourseValid = validateSelect(courseSelect, "course_error");

    if (!isSubjectValid || !isMarksValid || !isStaffValid || !isSemValid || !isCourseValid) {
      e.preventDefault(); 
    }
  });
});
