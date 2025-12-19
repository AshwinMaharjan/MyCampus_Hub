document.addEventListener("DOMContentLoaded", function() {
    let deleteId = 0;

    // ===== Approval Modal =====
    window.openApprovalModal = function(id, status, remarks){
        document.getElementById('modal_material_id').value = id;
        document.getElementById('modal_approval_status').value = status;
        document.getElementById('modal_remarks').value = remarks;
        document.getElementById('approvalModal').style.display = 'flex';
    }

    window.closeApprovalModal = function(){
        document.getElementById('approvalModal').style.display = 'none';
    }

    // ===== Delete Modal =====
    window.openDeleteModal = function(id){
        deleteId = id;
        document.getElementById('deleteModal').style.display = 'flex';
    }

    window.closeDeleteModal = function(){
        document.getElementById('deleteModal').style.display = 'none';
    }

    // Confirm delete action
    const confirmBtn = document.getElementById('confirmDeleteBtn');
    if(confirmBtn){
        confirmBtn.addEventListener('click', function(){
            window.location.href = 'study_material.php?delete=' + deleteId;
        });
    }

    // ===== Notifications =====
    const notifications = document.querySelectorAll('.notification');
    notifications.forEach(function(notif){
        // Auto-hide after 3 seconds
        setTimeout(() => {
            notif.style.display = 'none';
        }, 3000);

        // Close button
        const closeBtn = notif.querySelector('.close-btn');
        if(closeBtn){
            closeBtn.addEventListener('click', function(){
                notif.style.display = 'none';
            });
        }
    });

    // ===== Optional: Escape key closes modals =====
    document.addEventListener('keydown', function(e){
        if(e.key === "Escape"){
            closeApprovalModal();
            closeDeleteModal();
        }
    });

    // ===== Optional: Click outside modal closes it =====
    const modalOverlays = document.querySelectorAll('.modal-overlay');
    modalOverlays.forEach(function(overlay){
        overlay.addEventListener('click', function(e){
            if(e.target === overlay){
                overlay.style.display = 'none';
            }
        });
    });
});
