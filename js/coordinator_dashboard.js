// Chart.js Global Configuration
Chart.defaults.font.family = 'system-ui, -apple-system, sans-serif';
Chart.defaults.color = '#4b5563';

// Debug: Log dashboard data
console.log('Coordinator Dashboard Data:', coordinatorDashboardData);

// Color Schemes
const colorSchemes = {
  primary: ['#3b82f6', '#2563eb', '#1d4ed8', '#1e40af', '#1e3a8a'],
  success: ['#10b981', '#059669', '#047857', '#065f46', '#064e3b'],
  warm: ['#f59e0b', '#d97706', '#b45309', '#92400e', '#78350f'],
  cool: ['#06b6d4', '#0891b2', '#0e7490', '#155e75', '#164e63'],
  mixed: ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899']
};

// 1. Attendance Trend (Line Chart - Last 30 Days)
const attendanceTrendCtx = document.getElementById('attendanceTrendChart');
if (attendanceTrendCtx && coordinatorDashboardData.attendanceTrend) {
  // Fill missing dates for last 30 days
  const dates = [];
  const percentages = [];
  const today = new Date();
  
  for (let i = 29; i >= 0; i--) {
    const date = new Date(today);
    date.setDate(date.getDate() - i);
    const dateStr = date.toISOString().split('T')[0];
    dates.push(date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' }));
    
    const found = coordinatorDashboardData.attendanceTrend.find(item => item.date === dateStr);
    percentages.push(found ? parseFloat(found.percentage) : null);
  }

  new Chart(attendanceTrendCtx, {
    type: 'line',
    data: {
      labels: dates,
      datasets: [{
        label: 'Attendance %',
        data: percentages,
        borderColor: '#3b82f6',
        backgroundColor: 'rgba(59, 130, 246, 0.1)',
        borderWidth: 3,
        fill: true,
        tension: 0.4,
        pointRadius: 4,
        pointBackgroundColor: '#3b82f6',
        pointBorderColor: '#fff',
        pointBorderWidth: 2,
        pointHoverRadius: 7,
        pointHoverBackgroundColor: '#1e3a8a',
        spanGaps: true
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
          padding: 15,
          cornerRadius: 10,
          titleFont: {
            size: 14,
            weight: 'bold'
          },
          bodyFont: {
            size: 13
          },
          callbacks: {
            label: function(context) {
              if (context.parsed.y === null) {
                return 'No classes';
              }
              let status = '';
              if (context.parsed.y >= 75) status = ' ✅ Good';
              else if (context.parsed.y >= 60) status = ' ⚠️ Average';
              else status = ' ❌ Poor';
              return 'Attendance: ' + context.parsed.y.toFixed(1) + '%' + status;
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
            },
            font: {
              size: 12,
              weight: 'bold'
            }
          },
          grid: {
            color: 'rgba(0, 0, 0, 0.05)',
            drawBorder: false
          }
        },
        x: {
          grid: {
            display: false
          },
          ticks: {
            maxRotation: 45,
            minRotation: 45,
            font: {
              size: 11,
              weight: 'bold'
            }
          }
        }
      }
    }
  });
}

// 2. Subject Load Distribution (Pie Chart)
const subjectLoadCtx = document.getElementById('subjectLoadChart');
if (subjectLoadCtx && coordinatorDashboardData.subjectLoad && coordinatorDashboardData.subjectLoad.length > 0) {
  new Chart(subjectLoadCtx, {
    type: 'pie',
    data: {
      labels: coordinatorDashboardData.subjectLoad.map(item => item.faculty_name || 'Unassigned'),
      datasets: [{
        data: coordinatorDashboardData.subjectLoad.map(item => parseInt(item.subject_count)),
        backgroundColor: colorSchemes.mixed,
        borderColor: '#fff',
        borderWidth: 3,
        hoverOffset: 15
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
              size: 12,
              weight: 'bold'
            },
            usePointStyle: true,
            pointStyle: 'circle'
          }
        },
        tooltip: {
          backgroundColor: 'rgba(0, 0, 0, 0.8)',
          padding: 15,
          cornerRadius: 10,
          titleFont: {
            size: 14,
            weight: 'bold'
          },
          bodyFont: {
            size: 13
          },
          callbacks: {
            label: function(context) {
              const total = context.dataset.data.reduce((a, b) => a + b, 0);
              const percentage = ((context.parsed / total) * 100).toFixed(1);
              return context.label + ': ' + context.parsed + ' subjects (' + percentage + '%)';
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
            return value;
          }
        }
      }
    },
    plugins: [ChartDataLabels]
  });
} else {
  if (subjectLoadCtx) {
    subjectLoadCtx.parentElement.innerHTML += '<div class="empty-state"><i class="fas fa-chart-pie"></i><p>No subject load data available</p></div>';
  }
}

// 3. Marks Performance (Subject-wise Bar Chart)
const marksPerformanceCtx = document.getElementById('marksPerformanceChart');
if (marksPerformanceCtx && coordinatorDashboardData.marksPerformance && coordinatorDashboardData.marksPerformance.length > 0) {
  const data = coordinatorDashboardData.marksPerformance.map(item => parseFloat(item.avg_percentage).toFixed(2));
  
  new Chart(marksPerformanceCtx, {
    type: 'bar',
    data: {
      labels: coordinatorDashboardData.marksPerformance.map(item => item.sub_name),
      datasets: [{
        label: 'Average Percentage',
        data: data,
        backgroundColor: data.map(value => {
          if (value >= 80) return '#10b981';
          if (value >= 70) return '#3b82f6';
          if (value >= 60) return '#f59e0b';
          return '#ef4444';
        }),
        borderColor: data.map(value => {
          if (value >= 80) return '#059669';
          if (value >= 70) return '#2563eb';
          if (value >= 60) return '#d97706';
          return '#dc2626';
        }),
        borderWidth: 2,
        borderRadius: 8,
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
          padding: 15,
          cornerRadius: 10,
          titleFont: {
            size: 14,
            weight: 'bold'
          },
          bodyFont: {
            size: 13
          },
          callbacks: {
            label: function(context) {
              return 'Average: ' + context.parsed.y + '%';
            },
            afterLabel: function(context) {
              const value = context.parsed.y;
              if (value >= 80) return '✅ Excellent Performance';
              if (value >= 70) return '👍 Good Performance';
              if (value >= 60) return '⚠️ Average Performance';
              return '❌ Needs Attention';
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
            },
            font: {
              size: 12,
              weight: 'bold'
            }
          },
          grid: {
            color: 'rgba(0, 0, 0, 0.05)',
            drawBorder: false
          }
        },
        x: {
          grid: {
            display: false
          },
          ticks: {
            font: {
              size: 11,
              weight: 'bold'
            }
          }
        }
      }
    }
  });
} else {
  if (marksPerformanceCtx) {
    marksPerformanceCtx.parentElement.innerHTML += '<div class="empty-state"><i class="fas fa-chart-bar"></i><p>No marks data available</p></div>';
  }
}

// 4. Study Material Upload Trend (Line Chart)
const materialUploadCtx = document.getElementById('materialUploadChart');
if (materialUploadCtx && coordinatorDashboardData.materialUpload) {
  // Fill missing dates for last 30 days
  const dates = [];
  const counts = [];
  const today = new Date();
  
  for (let i = 29; i >= 0; i--) {
    const date = new Date(today);
    date.setDate(date.getDate() - i);
    const dateStr = date.toISOString().split('T')[0];
    dates.push(date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' }));
    
    const found = coordinatorDashboardData.materialUpload.find(item => item.date === dateStr);
    counts.push(found ? parseInt(found.count) : 0);
  }

  new Chart(materialUploadCtx, {
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
          padding: 15,
          cornerRadius: 10,
          titleFont: {
            size: 14,
            weight: 'bold'
          },
          bodyFont: {
            size: 13
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
            stepSize: 1,
            font: {
              size: 12,
              weight: 'bold'
            }
          },
          grid: {
            color: 'rgba(0, 0, 0, 0.05)',
            drawBorder: false
          }
        },
        x: {
          grid: {
            display: false
          },
          ticks: {
            maxRotation: 45,
            minRotation: 45,
            font: {
              size: 11,
              weight: 'bold'
            }
          }
        }
      }
    }
  });
}

// 5. Student Leave Pattern (Bar Chart)
const leavePatternCtx = document.getElementById('leavePatternChart');
if (leavePatternCtx && coordinatorDashboardData.leavePattern) {
  // Fill missing dates for last 30 days
  const dates = [];
  const counts = [];
  const today = new Date();
  
  for (let i = 29; i >= 0; i--) {
    const date = new Date(today);
    date.setDate(date.getDate() - i);
    const dateStr = date.toISOString().split('T')[0];
    dates.push(date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' }));
    
    const found = coordinatorDashboardData.leavePattern.find(item => item.date === dateStr);
    counts.push(found ? parseInt(found.count) : 0);
  }

  new Chart(leavePatternCtx, {
    type: 'bar',
    data: {
      labels: dates,
      datasets: [{
        label: 'Leave Requests',
        data: counts,
        backgroundColor: counts.map(value => {
          if (value === 0) return '#d1fae5';
          if (value <= 2) return '#fde68a';
          if (value <= 5) return '#fdba74';
          return '#fca5a5';
        }),
        borderColor: counts.map(value => {
          if (value === 0) return '#10b981';
          if (value <= 2) return '#f59e0b';
          if (value <= 5) return '#f97316';
          return '#ef4444';
        }),
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
          padding: 15,
          cornerRadius: 10,
          titleFont: {
            size: 14,
            weight: 'bold'
          },
          bodyFont: {
            size: 13
          },
          callbacks: {
            label: function(context) {
              let status = '';
              if (context.parsed.y === 0) status = ' ✅ No Leaves';
              else if (context.parsed.y <= 2) status = ' 📋 Normal';
              else if (context.parsed.y <= 5) status = ' ⚠️ High';
              else status = ' 🚨 Very High';
              return 'Requests: ' + context.parsed.y + status;
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
            stepSize: 1,
            font: {
              size: 12,
              weight: 'bold'
            }
          },
          grid: {
            color: 'rgba(0, 0, 0, 0.05)',
            drawBorder: false
          }
        },
        x: {
          grid: {
            display: false
          },
          ticks: {
            maxRotation: 45,
            minRotation: 45,
            font: {
              size: 10,
              weight: 'bold'
            }
          }
        }
      }
    }
  });
}

// Auto-refresh dashboard every 5 minutes
setInterval(function() {
  location.reload();
}, 300000);