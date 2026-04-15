// Chart.js Global Configuration
Chart.defaults.font.family = 'system-ui, -apple-system, sans-serif';
Chart.defaults.color = '#4b5563';

// Debug: Log dashboard data
console.log('Dashboard Data:', dashboardData);

// Color Schemes
const colorSchemes = {
  primary: ['#667eea', '#764ba2', '#f093fb', '#4facfe', '#43e97b'],
  success: ['#10b981', '#059669', '#047857', '#065f46', '#064e3b'],
  warm: ['#f59e0b', '#d97706', '#b45309', '#92400e', '#78350f'],
  cool: ['#3b82f6', '#2563eb', '#1d4ed8', '#1e40af', '#1e3a8a'],
  gradient: ['#667eea', '#764ba2', '#f093fb', '#4facfe', '#43e97b', '#f59e0b']
};

// 1. Student Enrollment by Course (Bar Chart)
const enrollmentCtx = document.getElementById('enrollmentChart');
if (enrollmentCtx && dashboardData.enrollment) {
  new Chart(enrollmentCtx, {
    type: 'bar',
    data: {
      labels: dashboardData.enrollment.map(item => item.course_name),
      datasets: [{
        label: 'Students Enrolled',
        data: dashboardData.enrollment.map(item => item.student_count),
        backgroundColor: colorSchemes.primary,
        borderColor: colorSchemes.primary,
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
              return 'Students: ' + context.parsed.y;
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
          }
        }
      }
    }
  });
}

// 2. Leave Requests Over Time (Line Chart)
const leaveTimeCtx = document.getElementById('leaveTimeChart');
if (leaveTimeCtx && dashboardData.leaveTime) {
  // Fill missing dates
  const dates = [];
  const counts = [];
  const today = new Date();
  
  for (let i = 29; i >= 0; i--) {
    const date = new Date(today);
    date.setDate(date.getDate() - i);
    const dateStr = date.toISOString().split('T')[0];
    dates.push(date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' }));
    
    const found = dashboardData.leaveTime.find(item => item.date === dateStr);
    counts.push(found ? parseInt(found.count) : 0);
  }

  new Chart(leaveTimeCtx, {
    type: 'line',
    data: {
      labels: dates,
      datasets: [{
        label: 'Leave Requests',
        data: counts,
        borderColor: '#f59e0b',
        backgroundColor: 'rgba(245, 158, 11, 0.1)',
        borderWidth: 3,
        fill: true,
        tension: 0.4,
        pointRadius: 4,
        pointBackgroundColor: '#f59e0b',
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

// 3. User Composition (Doughnut Chart)
const userCompCtx = document.getElementById('userCompositionChart');
if (userCompCtx && dashboardData.userComp && dashboardData.userComp.length > 0) {
    const labels = dashboardData.userComp.map(item => item.label);
    const data = dashboardData.userComp.map(item => item.count);

    new Chart(userCompCtx, {
        type: 'doughnut',
        data: {
            labels: labels,
            datasets: [{
                data: data,
                backgroundColor: ['#667eea', '#10b981', '#f59e0b'],
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
                    labels: { padding: 15, font: { size: 12 } }
                },
                tooltip: {
                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                    padding: 12,
                    cornerRadius: 8,
                    callbacks: {
                        label: function(context) {
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = ((context.parsed / total) * 100).toFixed(1);
                            return context.label + ': ' + context.parsed + ' users (' + percentage + '%)';
                        }
                    }
                },
                datalabels: {
                    color: '#fff',
                    font: { weight: 'bold', size: 14 },
                    formatter: function(value, context) {
                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                        const percentage = ((value / total) * 100).toFixed(1);
                        return percentage + '%';
                    }
                }
            }
        },
        plugins: [ChartDataLabels]
    });
}


// 4. Study Material Upload Trend (Area Chart)
const materialTrendCtx = document.getElementById('materialTrendChart');
if (materialTrendCtx && dashboardData.materialTrend) {
  // Fill missing dates for last 7 days
  const dates = [];
  const counts = [];
  const today = new Date();
  
  for (let i = 6; i >= 0; i--) {
    const date = new Date(today);
    date.setDate(date.getDate() - i);
    const dateStr = date.toISOString().split('T')[0];
    dates.push(date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' }));
    
    const found = dashboardData.materialTrend.find(item => item.date === dateStr);
    counts.push(found ? parseInt(found.count) : 0);
  }

  new Chart(materialTrendCtx, {
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
        pointRadius: 5,
        pointBackgroundColor: '#8b5cf6',
        pointBorderColor: '#fff',
        pointBorderWidth: 2,
        pointHoverRadius: 7
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
          }
        }
      }
    }
  });
}

// 5. Subject-wise Average Marks (Bar Chart)
const subjectAvgCtx = document.getElementById('subjectAvgChart');
if (subjectAvgCtx && dashboardData.subjectAvg) {
  new Chart(subjectAvgCtx, {
    type: 'bar',
    data: {
      labels: dashboardData.subjectAvg.map(item => item.sub_name),
      datasets: [{
        label: 'Average Percentage',
        data: dashboardData.subjectAvg.map(item => parseFloat(item.avg_percentage).toFixed(2)),
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
              return 'Average: ' + context.parsed.x + '%';
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
          max: 100,
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
}

// 6. Grade Distribution (Doughnut Chart)
const gradeDistCtx = document.getElementById('gradeDistChart');
if (gradeDistCtx && dashboardData.gradeDist) {
  const gradeColors = {
    'A+': '#10b981',
    'A': '#059669',
    'B+': '#3b82f6',
    'B': '#2563eb',
    'C+': '#f59e0b',
    'C': '#d97706',
    'D': '#ef4444',
    'F': '#dc2626'
  };

  // Convert object to arrays
  let labels = [];
  let data = [];
  let colors = [];
  
  for (let grade in dashboardData.gradeDist) {
    if (dashboardData.gradeDist[grade] > 0) {
      labels.push(grade);
      data.push(dashboardData.gradeDist[grade]);
      colors.push(gradeColors[grade] || '#6b7280');
    }
  }

  if (data.length > 0) {
    new Chart(gradeDistCtx, {
      type: 'doughnut',
      data: {
        labels: labels,
        datasets: [{
          data: data,
          backgroundColor: colors,
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
                const percentage = ((context.parsed / total) * 100).toFixed(1);
                return context.label + ': ' + context.parsed + ' students (' + percentage + '%)';
              }
            }
          },
          datalabels: {
            color: '#fff',
            font: {
              weight: 'bold',
              size: 14
            },
            formatter: function(value, context) {
              const total = context.dataset.data.reduce((a, b) => a + b, 0);
              const percentage = ((value / total) * 100).toFixed(1);
              return percentage + '%';
            }
          }
        }
      },
      plugins: [ChartDataLabels]
    });
  } else {
    gradeDistCtx.parentElement.innerHTML += '<p style="text-align:center;color:#6b7280;padding:20px;">No grade data available</p>';
  }
}

// 7. Subject Count per Course (Horizontal Bar Chart)
const subjectPerCourseCtx = document.getElementById('subjectPerCourseChart');
if (subjectPerCourseCtx && dashboardData.subjectPerCourse && dashboardData.subjectPerCourse.length > 0) {
  new Chart(subjectPerCourseCtx, {
    type: 'bar',
    data: {
      labels: dashboardData.subjectPerCourse.map(item => item.course_name),
      datasets: [{
        label: 'Number of Subjects',
        data: dashboardData.subjectPerCourse.map(item => parseInt(item.subject_count)),
        backgroundColor: colorSchemes.cool,
        borderColor: colorSchemes.cool,
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
              return 'Subjects: ' + context.parsed.x;
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
          ticks: {
            stepSize: 1
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
  if (subjectPerCourseCtx) {
    subjectPerCourseCtx.parentElement.innerHTML += '<p style="text-align:center;color:#6b7280;padding:20px;">No subject data available</p>';
  }
}

// Handle Leave Request Actions
function handleLeave(leaveId, action) {
  if (!confirm(`Are you sure you want to ${action === 'approved' ? 'accept' : 'reject'} this leave request?`)) {
    return;
  }

  // Send AJAX request to process leave
  fetch('process_leave.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
    },
    body: `leave_id=${leaveId}&action=${action}`
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      alert(`Leave request ${action} successfully!`);
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

// Auto-refresh dashboard every 5 minutes
setInterval(function() {
  location.reload();
}, 300000);