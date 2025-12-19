document.addEventListener('DOMContentLoaded', function() {
    const subjectSelect = document.getElementById('subject');
    const idNumberSelect = document.getElementById('id_number');
    const studentIdInput = document.getElementById('student_id');
    const studentNameInput = document.getElementById('student_name');
    const fullMarksInput = document.getElementById('full_marks');
    const obtainedMarksInput = document.getElementById('obtained_marks');
    const percentageInput = document.getElementById('percentage');
    const gradeInput = document.getElementById('grade');
    const semesterNameInput = document.getElementById('semester_name');
    const courseNameInput = document.getElementById('course_name');
    const marksForm = document.getElementById('marksForm');
    const examTypeSelect = document.getElementById('exam_type');

    console.log('JavaScript loaded successfully');

    subjectSelect.addEventListener('change', function() {
        const subjectId = this.value;
        const selectedOption = this.options[this.selectedIndex];
        const fullMarks = selectedOption.getAttribute('data-full-marks');

        fullMarksInput.value = fullMarks || '';
        if (fullMarks) obtainedMarksInput.setAttribute('max', fullMarks);
        else obtainedMarksInput.removeAttribute('max');

        resetStudentFields();

        if (subjectId) loadStudents(subjectId);
    });

    idNumberSelect.addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        studentIdInput.value = selectedOption.getAttribute('data-user-id') || '';
        studentNameInput.value = selectedOption.getAttribute('data-student-name') || '';
        semesterNameInput.value = selectedOption.getAttribute('data-sem-id') || '';
        courseNameInput.value = selectedOption.getAttribute('data-course-name') || '';
        document.getElementById('course_id').value = selectedOption.getAttribute('data-course-id') || '';
        document.getElementById('sem_id').value = selectedOption.getAttribute('data-sem-id') || '';
    });

    examTypeSelect.addEventListener('change', function() {
        const subjectId = subjectSelect.value;
        if (subjectId) {
            loadStudents(subjectId);
        }
        resetStudentFields();
    });

    obtainedMarksInput.addEventListener('input', () => {
        validateMarks();
        calculateGrade();
    });

    marksForm.addEventListener('submit', function(e) {
        if (!examTypeSelect.value) {
            alert("Please select an exam type.");
            e.preventDefault();
            return false;
        }
        if (!validateMarks()) {
            e.preventDefault();
            return false;
        }
    });

    function loadStudents(subjectId) {
        const examTypeId = examTypeSelect.value;
        let body = 'subject_id=' + encodeURIComponent(subjectId);
        if (examTypeId) {
            body += '&exam_type_id=' + encodeURIComponent(examTypeId);
        }
        
        fetch('fetch_students.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body
        })
        .then(response => response.json())
        .then(data => {
            idNumberSelect.innerHTML = '<option value="">Select Student ID Number</option>';
            if (data.length === 0) {
                const option = document.createElement('option');
                option.textContent = 'No students found';
                option.disabled = true;
                idNumberSelect.appendChild(option);
                return;
            }
            data.forEach(student => {
                const option = document.createElement('option');
                option.value = student.id_number;
                option.textContent = student.id_number;
                option.setAttribute('data-user-id', student.user_id);
                option.setAttribute('data-student-name', student.full_name);
                option.setAttribute('data-sem-id', student.sem_id);
                option.setAttribute('data-course-name', student.course_name);
                option.setAttribute('data-course-id', student.course_id);
                idNumberSelect.appendChild(option);
            });
        })
        .catch(err => {
            console.error('Fetch error:', err);
            alert('Error loading students: ' + err.message);
        });
    }

    function validateMarks() {
        const fullMarks = parseFloat(fullMarksInput.value) || 0;
        const obtained = parseFloat(obtainedMarksInput.value) || 0;
        clearValidationError(obtainedMarksInput);

        if (obtained > fullMarks && fullMarks > 0) {
            showValidationError(obtainedMarksInput, `Obtained marks cannot exceed full marks (${fullMarks})`);
            return false;
        }
        if (obtained < 0) {
            showValidationError(obtainedMarksInput, 'Obtained marks cannot be negative');
            return false;
        }
        return true;
    }

    function calculateGrade() {
        const full = parseFloat(fullMarksInput.value) || 0;
        const obtained = parseFloat(obtainedMarksInput.value) || 0;
        if (full > 0 && obtained >= 0 && obtained <= full) {
            const percent = (obtained / full) * 100;
            percentageInput.value = percent.toFixed(2);
            let grade = '';
            if (percent >= 90) grade = 'A+';
            else if (percent >= 80) grade = 'A';
            else if (percent >= 70) grade = 'B+';
            else if (percent >= 60) grade = 'B';
            else if (percent >= 50) grade = 'C+';
            else if (percent >= 40) grade = 'C';
            else grade = 'F';
            gradeInput.value = grade;
        } else {
            percentageInput.value = '';
            gradeInput.value = '';
        }
    }

    function showValidationError(el, msg) {
        clearValidationError(el);
        el.classList.add('error');
        const error = document.createElement('div');
        error.className = 'error-message';
        error.textContent = msg;
        error.id = el.id + '_error';
        el.parentNode.insertBefore(error, el.nextSibling);
    }

    function clearValidationError(el) {
        el.classList.remove('error');
        const errEl = document.getElementById(el.id + '_error');
        if (errEl) errEl.remove();
    }

    function resetStudentFields() {
        idNumberSelect.innerHTML = '<option value="">Select Student ID Number</option>';
        studentIdInput.value = '';
        studentNameInput.value = '';
        semesterNameInput.value = '';
        courseNameInput.value = '';
        obtainedMarksInput.value = '';
        percentageInput.value = '';
        gradeInput.value = '';
    }

    if (typeof toastMessage !== 'undefined') showToast(toastMessage);
});