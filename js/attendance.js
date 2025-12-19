document.addEventListener("DOMContentLoaded", () => {
  const courseSelect = document.getElementById("course");
  const semesterSelect = document.getElementById("semester");
  const subjectSelect = document.getElementById("subject");
  const dateInput = document.getElementById("attendance_date");
  const loadStudentsBtn = document.getElementById("loadStudentsBtn");
  const studentsSection = document.getElementById("studentsSection");
  const summarySection = document.getElementById("summarySection");
  const studentsTableContainer = document.getElementById("studentsTableContainer");
  const markAllPresentCheckbox = document.getElementById("markAllPresent");
  const searchInput = document.getElementById("searchStudent");
  const saveBtn = document.getElementById("saveBtn");
  const attendanceForm = document.getElementById("attendanceForm");

  // Subjects and semesters data
  const allSubjects = window.allSubjectsData;
  const allSemesters = window.allSemestersData;

  // --- Core Functions ---
  function checkFormValidity() {
    const allFilled = courseSelect.value && semesterSelect.value && subjectSelect.value && dateInput.value;
    loadStudentsBtn.disabled = !allFilled;
  }

  courseSelect.addEventListener("change", function () {
    const courseId = parseInt(this.value);
    semesterSelect.innerHTML = '<option value="">Select Semester</option>';
    subjectSelect.innerHTML = '<option value="">Select Subject</option>';
    semesterSelect.disabled = true;
    subjectSelect.disabled = true;

    if (courseId) {
      const uniqueSemesters = new Set();
      allSubjects.forEach(subject => {
        if (parseInt(subject.course_id) === courseId) {
          uniqueSemesters.add(parseInt(subject.sem_id));
        }
      });

      allSemesters.forEach(sem => {
        if (uniqueSemesters.has(parseInt(sem.sem_id))) {
          const opt = document.createElement("option");
          opt.value = sem.sem_id;
          opt.textContent = sem.sem_name;
          semesterSelect.appendChild(opt);
        }
      });

      semesterSelect.disabled = false;
    }
    checkFormValidity();
  });

  semesterSelect.addEventListener("change", function () {
    const courseId = parseInt(courseSelect.value);
    const semId = parseInt(this.value);
    subjectSelect.innerHTML = '<option value="">Select Subject</option>';
    subjectSelect.disabled = true;

    if (courseId && semId) {
      const filteredSubjects = allSubjects.filter(
        subject => parseInt(subject.course_id) === courseId && parseInt(subject.sem_id) === semId
      );

      filteredSubjects.forEach(subject => {
        const opt = document.createElement("option");
        opt.value = subject.sub_id;
        opt.textContent = subject.sub_name;
        subjectSelect.appendChild(opt);
      });

      subjectSelect.disabled = false;
    }
    checkFormValidity();
  });

  [subjectSelect, dateInput].forEach(el => el.addEventListener("change", checkFormValidity));

  // --- Load Students ---
  loadStudentsBtn.addEventListener("click", () => {
    const courseId = courseSelect.value;
    const semId = semesterSelect.value;
    const subjectId = subjectSelect.value;
    const date = dateInput.value;

    if (!courseId || !semId || !subjectId || !date) {
      alert("Please fill all required fields!");
      return;
    }

    document.getElementById("hidden_course_id").value = courseId;
    document.getElementById("hidden_sem_id").value = semId;
    document.getElementById("hidden_subject_id").value = subjectId;
    document.getElementById("hidden_attendance_date").value = date;

    studentsSection.classList.add("active");
    studentsTableContainer.innerHTML = `
      <div class="loading"><i class="fas fa-spinner"></i><p>Loading students...</p></div>
    `;
    saveBtn.style.display = "none";

    fetch("fetch_students_for_attendance.php", {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: `course_id=${courseId}&sem_id=${semId}&subject_id=${subjectId}&date=${date}`
    })
      .then(res => res.json())
      .then(data => {
        if (data.error) {
          studentsTableContainer.innerHTML = `<div class="no-students"><i class="fas fa-exclamation-circle"></i><p>${data.error}</p></div>`;
          summarySection.classList.remove("active");
          return;
        }

        if (data.duplicate) {
          studentsTableContainer.innerHTML = `<div class="no-students"><i class="fas fa-info-circle"></i><p>${data.message}</p></div>`;
          summarySection.classList.remove("active");
          return;
        }

        if (data.students && data.students.length > 0) {
          renderStudentsTable(data.students);
          updateSummary();
          summarySection.classList.add("active");
          saveBtn.style.display = "inline-flex";
        } else {
          studentsTableContainer.innerHTML = `<div class="no-students"><i class="fas fa-user-slash"></i><p>No students found.</p></div>`;
          summarySection.classList.remove("active");
        }
      })
      .catch(err => {
        console.error("Error:", err);
        studentsTableContainer.innerHTML = `<div class="no-students"><i class="fas fa-exclamation-triangle"></i><p>Error loading students.</p></div>`;
        summarySection.classList.remove("active");
      });
  });

  function renderStudentsTable(students) {
    let tableHTML = `
      <table>
        <thead>
          <tr>
            <th>S.N.</th><th>ID Number</th><th>Student Name</th>
            <th>Attendance Status</th><th>Remarks</th>
          </tr>
        </thead><tbody>
    `;
    students.forEach((student, i) => {
      tableHTML += `
        <tr class="student-row" data-student-name="${student.full_name.toLowerCase()}" data-student-id="${student.id_number.toLowerCase()}">
          <td>${i + 1}</td>
          <td>${student.id_number}</td>
          <td>${student.full_name}</td>
          <td>
            <input type="hidden" name="student_ids[]" value="${student.user_id}">
            <div class="radio-group">
              <div class="radio-option present">
                <input type="radio" name="attendance_status[${student.user_id}]" value="Present" id="present_${student.user_id}" class="attendance-radio" checked>
                <label for="present_${student.user_id}">Present</label>
              </div>
              <div class="radio-option absent">
                <input type="radio" name="attendance_status[${student.user_id}]" value="Absent" id="absent_${student.user_id}" class="attendance-radio">
                <label for="absent_${student.user_id}">Absent</label>
              </div>
              <div class="radio-option late">
                <input type="radio" name="attendance_status[${student.user_id}]" value="Late" id="late_${student.user_id}" class="attendance-radio">
                <label for="late_${student.user_id}">Late</label>
              </div>
            </div>
          </td>
          <td><input type="text" name="remarks[${student.user_id}]" class="remarks-input" placeholder="Optional remarks..."></td>
        </tr>
      `;
    });
    tableHTML += `</tbody></table>`;
    studentsTableContainer.innerHTML = tableHTML;

    document.querySelectorAll(".attendance-radio").forEach(r => r.addEventListener("change", updateSummary));
  }

  markAllPresentCheckbox.addEventListener("change", function () {
    const presentRadios = document.querySelectorAll('input[type="radio"][value="Present"]');
    presentRadios.forEach(r => (r.checked = this.checked));
    updateSummary();
  });

  searchInput.addEventListener("input", function () {
    const term = this.value.toLowerCase();
    document.querySelectorAll(".student-row").forEach(row => {
      row.style.display =
        row.dataset.studentName.includes(term) || row.dataset.studentId.includes(term) ? "" : "none";
    });
  });

  function updateSummary() {
    const radios = document.querySelectorAll(".attendance-radio");
    const studentIds = new Set();
    let present = 0, absent = 0, late = 0;
    radios.forEach(radio => {
      if (radio.checked) {
        studentIds.add(radio.name);
        if (radio.value === "Present") present++;
        else if (radio.value === "Absent") absent++;
        else if (radio.value === "Late") late++;
      }
    });
    const total = studentIds.size;
    const percent = total ? (((present + late) / total) * 100).toFixed(2) : 0;

    document.getElementById("totalStudents").textContent = total;
    document.getElementById("presentCount").textContent = present;
    document.getElementById("absentCount").textContent = absent;
    document.getElementById("lateCount").textContent = late;
    document.getElementById("attendancePercentage").textContent = percent + "%";
  }

  // --- Custom Confirmation Modal ---
  const confirmationOverlay = document.getElementById("confirmationOverlay");
  const confirmYes = document.getElementById("confirmYes");
  const confirmNo = document.getElementById("confirmNo");
  const confirmCount = document.getElementById("confirmCount");

  const handleSubmit = function (e) {
    e.preventDefault();
    const total = document.getElementById("totalStudents").textContent;
    if (total === "0") {
      alert("No students to save attendance for!");
      return;
    }

    confirmCount.textContent = total;
    confirmationOverlay.classList.add("active");

    confirmYes.onclick = function () {
      confirmationOverlay.classList.remove("active");
      attendanceForm.removeEventListener("submit", handleSubmit); // 🔧 prevent recursion
      attendanceForm.submit();
    };

    confirmNo.onclick = function () {
      confirmationOverlay.classList.remove("active");
    };
  };

  attendanceForm.addEventListener("submit", handleSubmit);
});
