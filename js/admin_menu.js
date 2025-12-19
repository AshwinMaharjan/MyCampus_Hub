document.querySelectorAll('.dropdown-toggle').forEach(button => {
    button.addEventListener('click', function (e) {
      e.preventDefault();
      const dropdown = this.nextElementSibling;
  
      document.querySelectorAll('.dropdown-content').forEach(menu => {
        if (menu !== dropdown) menu.style.display = 'none';
      });
  
      dropdown.style.display = (dropdown.style.display === 'block') ? 'none' : 'block';
    });
  });
  
  document.addEventListener('click', function (e) {
    if (!e.target.closest('.dropdown')) {
      document.querySelectorAll('.dropdown-content').forEach(menu => {
        menu.style.display = 'none';
      });
    }
  });
