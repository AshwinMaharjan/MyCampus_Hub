// Chart.js Global Configuration
Chart.defaults.font.family = 'system-ui, -apple-system, sans-serif';
Chart.defaults.color = '#4b5563';

// Debug: Log dashboard data
console.log('Student Dashboard Data:', studentDashboardData);

// Color Schemes
const colorSchemes = {
  primary: ['#667eea', '#764ba2', '#f093fb', '#4facfe', '#43e97b'],
  success: ['#10b981', '#059669', '#047857', '#065f46', '#064e3b'],
  warm: ['#f59e0b', '#d97706', '#b45309', '#92400e', '#78350f'],
  cool: ['#3b82f6', '#2563eb', '#1d4ed8', '#1e40af', '#1e3a8a'],
  gradient: ['#667eea', '#764ba2', '#8b5cf6', '#ec4899', '#f59e0b']
};

// 1. Attendance Trend (Line Chart - Last 30 Days)
const attendanceTrendCtx = document.getElementById('attendanceTrendChart');
if (attendanceTrendCtx && studentDashboardData.attendanceTrend) {
  // Fill missing dates for last 30 days
  const dates = [];
  const percentages = [];
  const today = new Date();
  
  for (let i = 29; i >= 0; i--) {
    const date = new Date(today);
    date.setDate(date.getDate() - i);
    const dateStr = date.toISOString().split('T')[0];
    dates.push(date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' }));
    
    const found = studentDashboardData.attendanceTrend.find(item => item.date === dateStr);
    percentages.push(found ? parseFloat(found.percentage) : null);
  }

  new Chart(attendanceTrendCtx, {
    type: 'line',
    data: {
      labels: dates,
      datasets: [{
        label: 'Attendance %',
        data: percentages,
        borderColor: '#667eea',
        backgroundColor: 'rgba(102, 126, 234, 0.1)',
        borderWidth: 3,
        fill: true,
        tension: 0.4,
        pointRadius: 5,
        pointBackgroundColor: '#667eea',
        pointBorderColor: '#fff',
        pointBorderWidth: 3,
        pointHoverRadius: 8,
        pointHoverBackgroundColor: '#764ba2',
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
                return 'No class';
              }
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

// 2. Marks Overview by Subject (Bar Chart)
const marksOverviewCtx = document.getElementById('marksOverviewChart');
if (marksOverviewCtx && studentDashboardData.marksOverview && studentDashboardData.marksOverview.length > 0) {
  const data = studentDashboardData.marksOverview.map(item => parseFloat(item.avg_percentage).toFixed(2));
  
  new Chart(marksOverviewCtx, {
    type: 'bar',
    data: {
      labels: studentDashboardData.marksOverview.map(item => item.sub_name),
      datasets: [{
        label: 'Average Percentage',
        data: data,
        backgroundColor: data.map(value => {
          if (value >= 90) return '#10b981';
          if (value >= 80) return '#3b82f6';
          if (value >= 70) return '#8b5cf6';
          if (value >= 60) return '#f59e0b';
          return '#ef4444';
        }),
        borderColor: data.map(value => {
          if (value >= 90) return '#059669';
          if (value >= 80) return '#2563eb';
          if (value >= 70) return '#7c3aed';
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
              if (value >= 90) return '🌟 Excellent';
              if (value >= 80) return '👍 Very Good';
              if (value >= 70) return '✓ Good';
              if (value >= 60) return '⚠ Average';
              return '📉 Need Improvement';
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
  if (marksOverviewCtx) {
    marksOverviewCtx.parentElement.innerHTML += '<div class="empty-state"><i class="fas fa-chart-bar"></i><p>No marks data available yet. Keep learning!</p></div>';
  }
}

// 3. Grade Distribution (Doughnut Chart)
const gradeDistributionCtx = document.getElementById('gradeDistributionChart');
if (gradeDistributionCtx && studentDashboardData.gradeDistribution) {
  const gradeColors = {
    'A+': '#10b981',
    'A': '#059669',
    'B+': '#3b82f6',
    'B': '#2563eb',
    'C+': '#8b5cf6',
    'C': '#f59e0b',
    'D': '#ef4444',
    'F': '#dc2626'
  };

  // Convert object to arrays and filter out zeros
  let labels = [];
  let data = [];
  let colors = [];
  
  for (let grade in studentDashboardData.gradeDistribution) {
    if (studentDashboardData.gradeDistribution[grade] > 0) {
      labels.push(grade);
      data.push(studentDashboardData.gradeDistribution[grade]);
      colors.push(gradeColors[grade] || '#6b7280');
    }
  }

  if (data.length > 0) {
    new Chart(gradeDistributionCtx, {
      type: 'doughnut',
      data: {
        labels: labels,
        datasets: [{
          data: data,
          backgroundColor: colors,
          borderColor: '#fff',
          borderWidth: 4,
          hoverOffset: 15
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
          legend: {
            position: 'right',
            labels: {
              padding: 20,
              font: {
                size: 13,
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
                return context.label + ': ' + context.parsed + ' exams (' + percentage + '%)';
              }
            }
          },
          datalabels: {
            color: '#fff',
            font: {
              weight: 'bold',
              size: 16
            },
            formatter: function(value, context) {
              const total = context.dataset.data.reduce((a, b) => a + b, 0);
              const percentage = ((value / total) * 100).toFixed(0);
              return percentage + '%';
            }
          }
        }
      },
      plugins: [ChartDataLabels]
    });
  } else {
    gradeDistributionCtx.parentElement.innerHTML += '<div class="empty-state"><i class="fas fa-chart-pie"></i><p>No grade data available yet. Start taking exams!</p></div>';
  }
}

// Add motivational messages based on performance
function addMotivationalMessage() {
  const avgMarks = parseFloat(document.querySelector('.stat-content h3').textContent);
  const container = document.querySelector('.dashboard-container');
  
  if (avgMarks >= 90) {
    showNotification('🌟 Outstanding Performance! Keep up the excellent work!', 'success');
  } else if (avgMarks >= 80) {
    showNotification('👏 Great job! You\'re doing very well!', 'success');
  } else if (avgMarks >= 70) {
    showNotification('👍 Good progress! Keep pushing forward!', 'info');
  } else if (avgMarks >= 60) {
    showNotification('💪 You can do better! Stay focused!', 'warning');
  } else if (avgMarks > 0) {
    showNotification('📚 Time to level up! Work harder!', 'warning');
  }
}

function showNotification(message, type) {
  const notification = document.createElement('div');
  notification.className = `notification notification-${type}`;
  notification.innerHTML = `
    <i class="fas fa-info-circle"></i>
    <span>${message}</span>
  `;
  notification.style.cssText = `
    position: fixed;
    top: 20px;
    right: 20px;
    background: white;
    padding: 15px 20px;
    border-radius: 10px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
    z-index: 1000;
    display: flex;
    align-items: center;
    gap: 10px;
    animation: slideIn 0.5s ease;
    max-width: 300px;
  `;
  
  const style = document.createElement('style');
  style.textContent = `
    @keyframes slideIn {
      from {
        transform: translateX(400px);
        opacity: 0;
      }
      to {
        transform: translateX(0);
        opacity: 1;
      }
    }
    .notification-success i { color: #10b981; }
    .notification-info i { color: #3b82f6; }
    .notification-warning i { color: #f59e0b; }
  `;
  document.head.appendChild(style);
  
  document.body.appendChild(notification);
  
  setTimeout(() => {
    notification.style.animation = 'slideOut 0.5s ease';
    setTimeout(() => notification.remove(), 500);
  }, 5000);
}

// Show motivational message on page load
window.addEventListener('load', () => {
  setTimeout(addMotivationalMessage, 1000);
});

// Auto-refresh dashboard every 5 minutes
setInterval(function() {
  location.reload();
}, 300000);