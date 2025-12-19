  document.addEventListener("DOMContentLoaded", function () {
  const semesterDropdown = document.getElementById("semesterFilter");

  if (!semesterDropdown) {
    console.error("Semester dropdown not found");
    return;
  }

  semesterDropdown.addEventListener("change", function () {
    const filter = semesterDropdown.value.toLowerCase();
    console.log("Filtering for semester:", filter); // Debugging log

    const rows = document.querySelectorAll("tbody tr");

    rows.forEach((row) => {
      const semesterCell = row.querySelector(".semester-cell");
      const semester = semesterCell?.textContent?.toLowerCase() || "";

      if (filter === "all" || semester.includes(filter)) {
        row.style.display = "";
      } else {
        row.style.display = "none";
      }
    });
  });
});
