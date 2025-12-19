// Chart.js Global Configuration
Chart.defaults.font.family = 'system-ui, -apple-system, sans-serif';
Chart.defaults.color = '#4b5563';

// Debug: Log dashboard data
console.log('Faculty Dashboard Data:', facultyDashboardData);

// Color Schemes
const colorSchemes = {
  primary: ['#667eea', '#764ba2', '#f093fb', '#4facfe', '#43e97b'],
  success: ['#10b981', '#059669', '#047857', '#065f46', '#064e3b'],
  warm: ['#f59e0b', '#d97706', '#b45309', '#92400e', '#78350f'],
  cool: ['#3b82f6', '#2563eb', '#1d4ed8', '#1e40af', '#1e3a8a'],
  teal: ['#14b8a6', '#0d9488', '#0f766e', '#115e59', '#134e4a'],
  purple: ['#8b5cf6', '#7c3aed', '#6d28d9', '#5b21b6', '#4c1d95']
};

// 1. Attendance Marking Status (Horizontal Bar Chart)
const attendanceMarkingCtx = document.getElementById('attendanceMarkingChart');
if (attendanceMarkingCtx && facultyDashboardData.attendanceMarking && facultyDashboardData.attendanceMarking.length > 0) {
  new Chart(attendanceMarkingCtx, {
    type: 'bar',
    data: {
      labels: facultyDashboardData.attendanceMarking.map(item => item.sub_name),
      datasets: [{
        label: 'Days Marked',
        data: facultyDashboardData.attendanceMarking.map(item => parseInt(item.days_marked)),
        backgroundColor: colorSchemes.teal,
        borderColor: colorSchemes.teal,
        borderWidth: 2,
        borderRadius: 6,
        borderSkipped: false
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: true,
      indexAxis: 'y',
      plugins: {
        legend: {
          display: false
        },
        tooltip: {
          backgroundColor: 'rgba(0, 0, 0, 0.8)',
          padding: 12,
          cornerRadius: 8,
          callbacks: {
            label: function(context) {
              return 'Days Marked: ' + context.parsed.x + ' out of 30';
            }
          }
        },
        datalabels: {
          display: false
        }
      },
      scales: {
        x: {
          beginAtZero: true,
          max: 30,
          ticks: {
            stepSize: 5
          },
          grid: {
            color: 'rgba(0, 0, 0, 0.05)'
          }
        },
        y: {
          grid: {
            display: false
          }
        }
      }
    }
  });
} else {
  if (attendanceMarkingCtx) {
    attendanceMarkingCtx.parentElement.innerHTML += '<p style="text-align:center;color:#6b7280;padding:20px;">No attendance data available</p>';
  }
}

// 2. Student Attendance Trend (Line Chart - Last 30 Days)
const studentAttendanceTrendCtx = document.getElementById('studentAttendanceTrendChart');
if (studentAttendanceTrendCtx && facultyDashboardData.studentAttendanceTrend) {
  // Fill missing dates for last 30 days
  const dates = [];
  const percentages = [];
  const today = new Date();
  
  for (let i = 29; i >= 0; i--) {
    const date = new Date(today);
    date.setDate(date.getDate() - i);
    const dateStr = date.toISOString().split('T')[0];
    dates.push(date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' }));
    
    const found = facultyDashboardData.studentAttendanceTrend.find(item => item.date === dateStr);
    percentages.push(found ? parseFloat(found.percentage) : 0);
  }

  new Chart(studentAttendanceTrendCtx, {
    type: 'line',
    data: {
      labels: dates,
      datasets: [{
        label: 'Attendance %',
        data: percentages,
        borderColor: '#10b981',
        backgroundColor: 'rgba(16, 185, 129, 0.1)',
        borderWidth: 3,
        fill: true,
        tension: 0.4,
        pointRadius: 3,
        pointBackgroundColor: '#10b981',
        pointBorderColor: '#fff',
        pointBorderWidth: 2,
        pointHoverRadius: 6
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: true,
      plugins: {
        legend: {
          display: false
        },
        tooltip: {
          backgroundColor: 'rgba(0, 0, 0, 0.8)',
          padding: 12,
          cornerRadius: 8,
          callbacks: {
            label: function(context) {
              return 'Attendance: ' + context.parsed.y.toFixed(1) + '%';
            }
          }
        },
        datalabels: {
          display: false
        }
      },
      scales: {
        y: {
          beginAtZero: true,
          max: 100,
          ticks: {
            callback: function(value) {
              return value + '%';
            }
          },
          grid: {
            color: 'rgba(0, 0, 0, 0.05)'
          }
        },
        x: {
          grid: {
            display: false
          },
          ticks: {
            maxRotation: 45,
            minRotation: 45
          }
        }
      }
    }
  });
}

// 3. Marks Entry Status (Doughnut Chart)
const marksEntryStatusCtx = document.getElementById('marksEntryStatusChart');
if (marksEntryStatusCtx && facultyDashboardData.marksEntryStatus) {
  const entered = parseInt(facultyDashboardData.marksEntryStatus.entered) || 0;
  const pending = parseInt(facultyDashboardData.marksEntryStatus.pending) || 0;
  
  new Chart(marksEntryStatusCtx, {
    type: 'doughnut',
    data: {
      labels: ['Entered', 'Pending'],
      datasets: [{
        data: [entered, pending],
        backgroundColor: ['#10b981', '#f59e0b'],
        borderColor: '#fff',
        borderWidth: 3,
        hoverOffset: 10
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: true,
      plugins: {
        legend: {
          position: 'bottom',
          labels: {
            padding: 15,
            font: {
              size: 12
            }
          }
        },
        tooltip: {
          backgroundColor: 'rgba(0, 0, 0, 0.8)',
          padding: 12,
          cornerRadius: 8,
          callbacks: {
            label: function(context) {
              const total = context.dataset.data.reduce((a, b) => a + b, 0);
              const percentage = total > 0 ? ((context.parsed / total) * 100).toFixed(1) : 0;
              return context.label + ': ' + context.parsed + ' (' + percentage + '%)';
            }
          }
        },
        datalabels: {
          color: '#fff',
          font: {
            weight: 'bold',
            size: 14
          },
          formatter: function(value) {
            return value;
          }
        }
      }
    },
    plugins: [ChartDataLabels]
  });
}

// 4. Study Material Upload Trend (Area Chart)
const materialUploadTrendCtx = document.getElementById('materialUploadTrendChart');
if (materialUploadTrendCtx && facultyDashboardData.materialUploadTrend) {
  // Fill missing dates for last 30 days
  const dates = [];
  const counts = [];
  const today = new Date();
  
  for (let i = 29; i >= 0; i--) {
    const date = new Date(today);
    date.setDate(date.getDate() - i);
    const dateStr = date.toISOString().split('T')[0];
    dates.push(date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' }));
    
    const found = facultyDashboardData.materialUploadTrend.find(item => item.date === dateStr);
    counts.push(found ? parseInt(found.count) : 0);
  }

  new Chart(materialUploadTrendCtx, {
    type: 'line',
    data: {
      labels: dates,
      datasets: [{
        label: 'Materials Uploaded',
        data: counts,
        borderColor: '#8b5cf6',
        backgroundColor: 'rgba(139, 92, 246, 0.2)',
        borderWidth: 3,
        fill: true,
        tension: 0.4,
        pointRadius: 4,
        pointBackgroundColor: '#8b5cf6',
        pointBorderColor: '#fff',
        pointBorderWidth: 2,
        pointHoverRadius: 6
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: true,
      plugins: {
        legend: {
          display: false
        },
        tooltip: {
          backgroundColor: 'rgba(0, 0, 0, 0.8)',
          padding: 12,
          cornerRadius: 8
        },
        datalabels: {
          display: false
        }
      },
      scales: {
        y: {
          beginAtZero: true,
          ticks: {
            stepSize: 1
          },
          grid: {
            color: 'rgba(0, 0, 0, 0.05)'
          }
        },
        x: {
          grid: {
            display: false
          },
          ticks: {
            maxRotation: 45,
            minRotation: 45
          }
        }
      }
    }
  });
}

// 5. Subject-wise Student Performance (Bar Chart)
const subjectPerformanceCtx = document.getElementById('subjectPerformanceChart');
if (subjectPerformanceCtx && facultyDashboardData.subjectPerformance && facultyDashboardData.subjectPerformance.length > 0) {
  new Chart(subjectPerformanceCtx, {
    type: 'bar',
    data: {
      labels: facultyDashboardData.subjectPerformance.map(item => item.sub_name),
      datasets: [{
        label: 'Average Percentage',
        data: facultyDashboardData.subjectPerformance.map(item => parseFloat(item.avg_percentage).toFixed(2)),
        backgroundColor: colorSchemes.success,
        borderColor: colorSchemes.success,
        borderWidth: 2,
        borderRadius: 6,
        borderSkipped: false
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: true,
      plugins: {
        legend: {
          display: false
        },
        tooltip: {
          backgroundColor: 'rgba(0, 0, 0, 0.8)',
          padding: 12,
          cornerRadius: 8,
          callbacks: {
            label: function(context) {
              return 'Average: ' + context.parsed.y + '%';
            }
          }
        },
        datalabels: {
          display: false
        }
      },
      scales: {
        y: {
          beginAtZero: true,
          max: 100,
          grid: {
            color: 'rgba(0, 0, 0, 0.05)'
          }
        },
        x: {
          grid: {
            display: false
          }
        }
      }
    }
  });
} else {
  if (subjectPerformanceCtx) {
    subjectPerformanceCtx.parentElement.innerHTML += '<p style="text-align:center;color:#6b7280;padding:20px;">No performance data available</p>';
  }
}

// Handle Student Leave Request Actions
function handleStudentLeave(leaveId, action) {
  if (!confirm(`Are you sure you want to ${action === 'Approved' ? 'approve' : 'reject'} this leave request?`)) {
    return;
  }

  fetch('process_student_leave.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
    },
    body: `leave_id=${leaveId}&action=${action}`
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      alert(`Leave request ${action.toLowerCase()} successfully!`);
      location.reload();
    } else {
      alert('Error: ' + (data.message || 'Failed to process leave request'));
    }
  })
  .catch(error => {
    console.error('Error:', error);
    alert('An error occurred while processing the leave request');
  });
}

// Handle Material Approval
function approveMaterial(materialId, action) {
  if (!confirm(`Are you sure you want to ${action === 'Approved' ? 'approve' : 'reject'} this material?`)) {
    return;
  }

  fetch('process_material_approval.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
    },
    body: `material_id=${materialId}&action=${action}`
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      alert(`Material ${action.toLowerCase()} successfully!`);
      location.reload();
    } else {
      alert('Error: ' + (data.message || 'Failed to process material'));
    }
  })
  .catch(error => {
    console.error('Error:', error);
    alert('An error occurred while processing the material');
  });
}

// Handle Edit Marks
function editMarks(marksId) {
  // Redirect to marks edit page or open modal
  window.location.href = 'edit_marks.php?id=' + marksId;
}

// Auto-refresh dashboard every 5 minutes
setInterval(function() {
  location.reload();
}, 300000);